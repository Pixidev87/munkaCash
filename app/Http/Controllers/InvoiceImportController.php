<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\DiscardInvoiceImportRequest;
use App\Http\Requests\Invoices\DownloadInvoiceTemplateRequest;
use App\Http\Requests\Invoices\FilterInvoiceImportsRequest;
use App\Http\Requests\Invoices\UploadInvoiceCsvRequest;
use App\Models\Customer;
use App\Models\InvoiceImport;
use App\Services\InvoiceCsvImport;
use App\Services\InvoiceCsvParser;
use App\Services\SpreadsheetCsv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceImportController extends Controller
{
    public function index(FilterInvoiceImportsRequest $request, string $organization): Response
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $status = $request->validated('status');
        $available = InvoiceImport::query()->availableTo($company->id, $user->id);
        $completed = (clone $available)->whereNull('rows')->selectRaw('COUNT(*)');
        $counts = (clone $available)->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectSub($completed, 'completed')
            ->first();
        $completedCount = (int) ($counts->completed ?? 0);
        $imports = $available
            ->when($status === 'pending', fn ($query) => $query->whereNotNull('rows'))
            ->when($status === 'completed', fn ($query) => $query->whereNull('rows'))
            ->select(['id', 'created_at', 'expires_at', 'created_count', 'unchanged_count'])
            ->latest('created_at')
            ->latest('id')
            ->simplePaginate(20)
            ->appends(['status' => $status])
            ->through(static fn (InvoiceImport $import): array => [
                'id' => $import->id,
                'createdAt' => $import->created_at?->toISOString(),
                'expiresAt' => $import->expires_at->toISOString(),
                'created' => $import->created_count,
                'unchanged' => $import->unchanged_count,
            ]);

        return Inertia::render('organizations/invoice-import-history', [
            'organizationId' => (string) $company->id,
            'status' => $status,
            'counts' => [
                'completed' => $completedCount,
                'pending' => (int) ($counts->total ?? 0) - $completedCount,
            ],
            'imports' => $imports,
        ]);
    }

    public function create(Request $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);

        return Inertia::render('organizations/invoice-import', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'customers' => $company->customers()
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (Customer $customer): array => [
                    'id' => (string) $customer->id,
                    'display_name' => $customer->display_name,
                ]),
            'maxRows' => InvoiceCsvParser::MAX_ROWS,
            'maxSizeKiB' => InvoiceCsvParser::MAX_BYTES_KIB,
        ]);
    }

    public function template(DownloadInvoiceTemplateRequest $request, string $organization): StreamedResponse
    {
        $request->user()->findActiveOrganization($organization);
        $customerId = (int) ($request->validated('customer_id') ?? 1);

        return response()->streamDownload(
            static function () use ($customerId): void {
                echo InvoiceCsvParser::HEADER."\r\n{$customerId};00042;2026-12-01;HUF;120000\r\n";
            },
            'munkacash-szamlaimport.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function store(UploadInvoiceCsvRequest $request, string $organization, InvoiceCsvParser $parser): RedirectResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);

        $file = $request->file('csv');
        abort_unless($file instanceof UploadedFile, 422);

        $contents = $file->get();
        if ($contents === false) {
            throw ValidationException::withMessages(['csv' => 'A feltöltött fájl nem olvasható. Töltsd fel újra.']);
        }

        $batch = InvoiceImport::create([
            'organization_id' => $company->id,
            'user_id' => $user->id,
            'rows' => $parser->parse($contents),
            'expires_at' => now()->addMinutes(30),
        ]);

        return to_route('organizations.invoice-imports.show', [$organization, $batch]);
    }

    public function show(Request $request, string $organization, string $import, InvoiceCsvImport $service): Response
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $batch = InvoiceImport::query()->availableTo($company->id, $user->id)->findOrFail($import);
        $rows = $batch->rows === null ? [] : $service->preview($company, $batch->rows);
        $counts = array_count_values(array_column($rows, 'status'));

        return Inertia::render('organizations/invoice-import-preview', [
            'organizationId' => $organization,
            'importId' => $batch->id,
            'expiresAt' => $batch->expires_at->toISOString(),
            'rows' => array_map(static fn (array $row): array => [
                ...$row,
                'data' => $row['data'] === null ? null : [
                    ...$row['data'],
                    'customer_id' => (string) $row['data']['customer_id'],
                ],
            ], $rows),
            'previewCounts' => $batch->rows === null ? null : [
                'new' => $counts['new'] ?? 0,
                'unchanged' => $counts['unchanged'] ?? 0,
                'invalid' => $counts['invalid'] ?? 0,
                'conflict' => $counts['conflict'] ?? 0,
            ],
            'canImport' => $service->canImport($rows),
            'summary' => $batch->rows === null ? [
                'confirmedAt' => $batch->confirmed_at?->toISOString(),
                'hasTrackedInvoices' => $company->invoices()->where('invoice_import_id', $batch->id)->exists(),
                'created' => $batch->created_count,
                'unchanged' => $batch->unchanged_count,
            ] : null,
        ]);
    }

    public function receipt(Request $request, string $organization, string $import): StreamedResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $batch = InvoiceImport::query()->availableTo($company->id, $user->id)
            ->whereNull('rows')->findOrFail($import);

        return SpreadsheetCsv::download('munkacash-importeredmeny.csv', [
            'uploaded_at_utc', 'confirmed_at_utc', 'expires_at_utc', 'created_count', 'unchanged_count',
        ], [[
            $batch->created_at?->toISOString(),
            $batch->confirmed_at?->toISOString(),
            $batch->expires_at->toISOString(),
            $batch->created_count,
            $batch->unchanged_count,
        ]]);
    }

    public function errorReport(Request $request, string $organization, string $import, InvoiceCsvImport $service): StreamedResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $batch = InvoiceImport::query()->availableTo($company->id, $user->id)->findOrFail($import);
        abort_if($batch->rows === null, 404);

        $errors = [];
        foreach ($service->preview($company, $batch->rows) as $row) {
            foreach ($row['errors'] as $issue) {
                $errors[] = [$row['line'], $issue['column'], $issue['code'], $issue['message']];
            }
        }
        abort_if($errors === [], 404);

        return SpreadsheetCsv::download('munkacash-importhibak.csv', ['line', 'column', 'code', 'message'], $errors);
    }

    public function confirm(Request $request, string $organization, string $import, InvoiceCsvImport $service): RedirectResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);

        try {
            DB::transaction(function () use ($company, $user, $import, $service): void {
                $batch = InvoiceImport::query()->availableTo($company->id, $user->id)->lockForUpdate()->findOrFail($import);
                if ($batch->rows === null) {
                    return;
                }

                $rows = $service->preview($company, $batch->rows);
                if (! $service->canImport($rows)) {
                    throw ValidationException::withMessages(['import' => 'Az import hibás vagy ütköző sorokat tartalmaz. Javítsd a fájlt, majd töltsd fel újra.']);
                }

                $confirmedAt = now()->toImmutable();
                $batch->update([
                    ...$service->import($company, $batch, $rows),
                    'rows' => null,
                    'confirmed_at' => $confirmedAt,
                    'expires_at' => $confirmedAt->addDays(30),
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['import' => 'A számlalista közben változott. Nézd át a friss előnézetet, majd erősítsd meg újra az importot.']);
        }

        return to_route('organizations.invoice-imports.show', [$organization, $import]);
    }

    public function destroy(DiscardInvoiceImportRequest $request, string $organization, string $import): RedirectResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $batch = InvoiceImport::query()->availableTo($company->id, $user->id)->lockForUpdate()->findOrFail($import);
        abort_if($batch->rows === null, 409, 'A már megerősített import nem vethető el.');
        $batch->deleteOrFail();

        return to_route('organizations.invoice-imports.index', $company);
    }
}
