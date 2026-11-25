<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoices\FilterInvoicesRequest;
use App\Http\Requests\Invoices\ResetInvoiceBalanceRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceBalanceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceDisputeRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\HungarianWorkCalendar;
use App\Services\InvoiceListQuery;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(FilterInvoicesRequest $request, string $organization, InvoiceListQuery $invoiceList, HungarianWorkCalendar $calendar): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $filters = $request->criteria();
        [$sortColumn, $sortDirection] = match ($filters['sort'] ?? null) {
            'due_asc' => ['due_date', 'asc'],
            'due_desc' => ['due_date', 'desc'],
            'outstanding_desc' => ['outstanding_amount', 'desc'],
            default => ['id', 'desc'],
        };
        $matching = $invoiceList->matching($company, $filters);
        $totals = $invoiceList->totals($matching);
        $invoices = $matching
            ->with(['customer:id,display_name', 'checker:id,name'])
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'id', fn ($query) => $query->latest('id'))
            ->simplePaginate(25)
            ->appends($filters)
            ->through(fn (Invoice $invoice): array => [
                'id' => (string) $invoice->id,
                'number' => $invoice->invoice_number,
                'customer' => $invoice->customer->display_name,
                'customerId' => (string) $invoice->customer_id,
                'dueDate' => $invoice->due_date->toDateString(),
                'effectiveDueDate' => $calendar->effectiveDeadline($invoice->due_date)?->toDateString(),
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
            'totals' => $totals,
            'filters' => [
                'invoiceNumber' => $filters['invoice_number'] ?? '',
                'status' => $filters['status'] ?? '',
                'source' => $filters['source'] ?? '',
                'customerId' => (string) ($filters['customer_id'] ?? ''),
                'disputed' => isset($filters['disputed']),
                'overdue' => isset($filters['overdue']),
                'awaitingBalance' => isset($filters['awaiting_balance']),
                'dueFrom' => $filters['due_from'] ?? '',
                'dueTo' => $filters['due_to'] ?? '',
                'sort' => $filters['sort'] ?? '',
                'billingUnconfirmed' => isset($filters['billing_unconfirmed']),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request, string $organization): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        try {
            $company->invoices()->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Ezzel a számlaszámmal már létezik számla ennél a cégnél.',
            ])->errorBag('invoice');
        }

        return to_route('organizations.invoices.index', $organization);
    }

    public function updateBalance(UpdateInvoiceBalanceRequest $request, ReminderTestQueue $queue): RedirectResponse
    {
        $user = $request->user();
        $selected = $request->invoice();
        $outstanding = (int) $request->validated('outstanding_amount');
        $checkedAt = now();
        $updated = $selected->newQuery()
            ->whereKey($selected->id)
            ->where('revision', $request->validated('revision'))
            ->increment('revision', 1, [
                'outstanding_amount' => $outstanding,
                'status' => InvoiceStatus::fromCheckedBalance($selected->gross_amount, $outstanding)->value,
                'status_source' => 'manual',
                'status_checked_at' => $checkedAt,
                'checked_by' => $user->id,
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'outstanding_amount' => 'A számla közben változott. Ellenőrizd a friss egyenleget, majd próbáld újra.',
            ])->errorBag($request->errorBagName());
        }

        $selected->balanceEvents()->create([
            'actor_id' => $user->id,
            'outstanding_amount' => $outstanding,
            'occurred_at' => $checkedAt,
        ]);
        $queue->blockPendingForInvoice($selected, ReminderDeliveryControls::BALANCE_RECHECKED);

        return back();
    }

    public function resetBalance(ResetInvoiceBalanceRequest $request, string $organization, string $invoice, ReminderTestQueue $queue): RedirectResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $selected = $company->invoices()->lockForUpdate()->findOrFail($invoice);

        if ($selected->revision !== $request->revision()) {
            throw ValidationException::withMessages([
                'revision' => 'A számla közben változott. Ellenőrizd a friss állapotot, majd próbáld újra.',
            ])->errorBag($request->errorBagName());
        }

        if ($selected->outstanding_amount !== null || $selected->status !== InvoiceStatus::Unchecked) {
            $selected->increment('revision', 1, [
                'outstanding_amount' => null,
                'status' => InvoiceStatus::Unchecked->value,
                'status_source' => null,
                'status_checked_at' => null,
                'checked_by' => null,
            ]);
            $selected->balanceEvents()->create([
                'actor_id' => $user->id,
                'outstanding_amount' => null,
                'occurred_at' => now(),
            ]);
            $queue->blockPendingForInvoice($selected, ReminderDeliveryControls::BALANCE_UNVERIFIED);
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
            $queue->blockPendingForInvoice($selected, ReminderDeliveryControls::INVOICE_DISPUTED);
        }

        return back();
    }
}
