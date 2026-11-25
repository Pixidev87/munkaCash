<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoices\FilterInvoicesRequest;
use App\Http\Requests\Invoices\UpdateInvoiceDisputeRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\OverdueInvoices;
use App\Services\ReminderTestQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(FilterInvoicesRequest $request, string $organization, OverdueInvoices $overdueInvoices): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $filters = $request->safe()->only(['invoice_number', 'status', 'disputed', 'overdue', 'source', 'customer_id']);
        $invoices = $company->invoices()
            ->when(isset($filters['invoice_number']), fn ($query) => $query->where('invoice_number', $filters['invoice_number']))
            ->when(isset($filters['customer_id']), fn ($query) => $query->where('customer_id', (int) $filters['customer_id']))
            ->when(isset($filters['source']), fn ($query) => $query->where('source', $filters['source']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['disputed']), fn ($query) => $query->where('is_disputed', true))
            ->when(isset($filters['overdue']), fn ($query) => $query->whereIn('id', $overdueInvoices->queryFor($company)->select('id')))
            ->with(['customer:id,display_name', 'checker:id,name'])
            ->latest('id')
            ->simplePaginate(25)
            ->appends($filters)
            ->through(fn (Invoice $invoice): array => [
                'id' => (string) $invoice->id,
                'number' => $invoice->invoice_number,
                'customer' => $invoice->customer->display_name,
                'customerId' => (string) $invoice->customer_id,
                'dueDate' => $invoice->due_date->toDateString(),
                'gross' => $invoice->gross_amount,
                'outstanding' => $invoice->outstanding_amount,
                'status' => $invoice->status->value,
                'source' => InvoiceSource::tryFrom($invoice->source)?->value,
                'checkedAt' => $invoice->status_checked_at?->toISOString(),
                'checker' => $invoice->checker?->name,
                'revision' => $invoice->revision,
                'isDisputed' => $invoice->is_disputed,
            ]);

        $selectedCustomerId = $request->query('customer');

        return Inertia::render('organizations/invoices', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'customers' => $company->customers()
                ->orderBy('display_name')
                ->orderBy('id')
                ->get(['id', 'display_name'])
                ->map(fn (Customer $customer): array => [
                    'id' => (string) $customer->id,
                    'name' => $customer->display_name,
                ]),
            'selectedCustomerId' => is_string($selectedCustomerId) ? $selectedCustomerId : '',
            'invoices' => $invoices,
            'filters' => [
                'invoiceNumber' => $filters['invoice_number'] ?? '',
                'status' => $filters['status'] ?? '',
                'source' => $filters['source'] ?? '',
                'customerId' => (string) ($filters['customer_id'] ?? ''),
                'disputed' => isset($filters['disputed']),
                'overdue' => isset($filters['overdue']),
            ],
        ]);
    }

    public function store(Request $request, string $organization): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        $data = $request->validateWithBag('invoice', [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('organization_id', $company->id)],
            'invoice_number' => ['required', 'string', 'max:100'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'gross_amount' => ['required', 'integer', 'min:1', 'max:'.Invoice::MAX_GROSS_AMOUNT],
        ], [
            'customer_id.*' => 'Válassz ügyfelet a cég ügyfelei közül.',
            'invoice_number.*' => 'Add meg a számlaszámot, legfeljebb 100 karakterben.',
            'due_date.*' => 'Adj meg érvényes fizetési határidőt.',
            'gross_amount.*' => 'Adj meg pozitív egész forintösszeget, legfeljebb 12 számjeggyel.',
        ]);

        try {
            $company->invoices()->create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Ezzel a számlaszámmal már létezik számla ennél a cégnél.',
            ])->errorBag('invoice');
        }

        return to_route('organizations.invoices.index', $organization);
    }

    public function updateBalance(Request $request, string $organization, string $invoice): RedirectResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $selected = $company->invoices()->findOrFail($invoice);
        $bag = 'balance-'.$invoice;
        $data = $request->validateWithBag($bag, [
            'outstanding_amount' => ['required', 'integer', 'min:0', 'max:'.$selected->gross_amount],
            'revision' => ['required', 'integer', 'min:0'],
        ], [
            'outstanding_amount.*' => 'Adj meg 0 és a bruttó összeg közötti egész forintértéket.',
        ]);

        $outstanding = (int) $data['outstanding_amount'];
        $updated = $company->invoices()
            ->whereKey($selected->id)
            ->where('revision', $data['revision'])
            ->increment('revision', 1, [
                'outstanding_amount' => $outstanding,
                'status' => InvoiceStatus::fromCheckedBalance($selected->gross_amount, $outstanding)->value,
                'status_source' => 'manual',
                'status_checked_at' => now(),
                'checked_by' => $user->id,
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'outstanding_amount' => 'A számla közben változott. Ellenőrizd a friss egyenleget, majd próbáld újra.',
            ])->errorBag($bag);
        }

        return back();
    }

    public function updateDispute(UpdateInvoiceDisputeRequest $request, string $organization, string $invoice, ReminderTestQueue $queue): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        $selected = $company->invoices()->lockForUpdate()->findOrFail($invoice);

        if ($selected->revision !== $request->revision()) {
            throw ValidationException::withMessages([
                'revision' => 'A számla közben változott. Ellenőrizd a friss állapotot, majd próbáld újra.',
            ])->errorBag($request->errorBagName());
        }

        $disputed = $request->isDisputed();
        if ($selected->is_disputed !== $disputed) {
            $selected->increment('revision', 1, ['is_disputed' => $disputed]);
        }

        if ($disputed) {
            $queue->blockPendingForInvoice($selected);
        }

        return back();
    }
}
