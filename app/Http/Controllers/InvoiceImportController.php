<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\UploadInvoiceCsvRequest;
use App\Models\Customer;
use App\Models\InvoiceImport;
use App\Services\InvoiceCsvImport;
use App\Services\InvoiceCsvParser;
use Illuminate\Database\Eloquent\Builder;
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

    public function template(Request $request, string $organization): StreamedResponse
    {
        $request->user()->findActiveOrganization($organization);

        return response()->streamDownload(
            static function (): void {
                echo InvoiceCsvParser::HEADER."\r\n1;00042;2026-12-01;HUF;120000\r\n";
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
        $batch = $this->availableBatches($company->id, $user->id)->findOrFail($import);
        $rows = $batch->rows === null ? [] : $service->preview($company, $batch->rows);

        return Inertia::render('organizations/invoice-import-preview', [
            'organizationId' => $organization,
            'importId' => $batch->id,
            'rows' => array_map(static fn (array $row): array => [
                ...$row,
                'data' => $row['data'] === null ? null : [
                    ...$row['data'],
                    'customer_id' => (string) $row['data']['customer_id'],
                ],
            ], $rows),
            'canImport' => $service->canImport($rows),
            'summary' => $batch->rows === null ? [
                'created' => $batch->created_count,
                'unchanged' => $batch->unchanged_count,
            ] : null,
        ]);
    }

    public function confirm(Request $request, string $organization, string $import, InvoiceCsvImport $service): RedirectResponse
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);

        try {
            DB::transaction(function () use ($company, $user, $import, $service): void {
                $batch = $this->availableBatches($company->id, $user->id)->lockForUpdate()->findOrFail($import);
                if ($batch->rows === null) {
                    return;
                }

                $rows = $service->preview($company, $batch->rows);
                if (! $service->canImport($rows)) {
                    throw ValidationException::withMessages(['import' => 'Az import hibás vagy ütköző sorokat tartalmaz. Javítsd a fájlt, majd töltsd fel újra.']);
                }

                $batch->update([
                    ...$service->import($company, $rows),
                    'rows' => null,
                    'expires_at' => now()->addDays(30),
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['import' => 'A számlalista közben változott. Nézd át a friss előnézetet, majd erősítsd meg újra az importot.']);
        }

        return to_route('organizations.invoice-imports.show', [$organization, $import]);
    }

    /** @return Builder<InvoiceImport> */
    private function availableBatches(int $organizationId, int $userId): Builder
    {
        return InvoiceImport::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('expires_at', '>', now());
    }
}
