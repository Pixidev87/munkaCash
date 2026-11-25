<?php

namespace App\Services;

use App\Enums\InvoiceSource;
use App\Models\Invoice;
use App\Models\InvoiceImport;
use App\Models\Organization;

/**
 * @phpstan-import-type StoredCsvRow from InvoiceCsvParser
 * @phpstan-import-type CsvIssue from InvoiceCsvParser
 * @phpstan-import-type InvoiceData from InvoiceCsvParser
 *
 * @phpstan-type PreviewRow array{line: int, data: InvoiceData|null, errors: list<CsvIssue>, customerName: string|null, status: 'invalid'|'conflict'|'unchanged'|'new'}
 */
class InvoiceCsvImport
{
    /**
     * @param  list<StoredCsvRow>  $rows
     * @return list<PreviewRow>
     */
    public function preview(Organization $company, array $rows): array
    {
        $numbers = [];
        $customerIds = [];
        foreach ($rows as $row) {
            if ($row['data'] !== null) {
                $key = mb_strtolower($row['data']['invoice_number']);
                $numbers[$key][] = $row['line'];
                $customerIds[] = $row['data']['customer_id'];
            }
        }

        $customers = $company->customers()->whereKey(array_unique($customerIds))->pluck('display_name', 'id');

        return array_map(function (array $row) use ($company, $numbers, $customers): array {
            $data = $row['data'];
            $row['customerName'] = $data === null ? null : $customers->get($data['customer_id']);
            $row['errors'] = array_map(static fn (array|string $error): array => is_string($error)
                ? ['code' => 'legacy_row_error', 'column' => null, 'message' => $error]
                : $error, $row['errors']);

            if ($data === null) {
                return [...$row, 'status' => 'invalid'];
            }

            $duplicateLines = $numbers[mb_strtolower($data['invoice_number'])];
            if (count($duplicateLines) > 1) {
                return [...$row, 'status' => 'conflict', 'errors' => [[
                    'code' => 'duplicate_invoice_number',
                    'column' => 'invoice_number',
                    'message' => 'Ismétlődő számlaszám ezekben a sorokban: '.implode(', ', $duplicateLines).'.',
                ]]];
            }

            if (! $customers->has($data['customer_id'])) {
                return [...$row, 'status' => 'invalid', 'errors' => [[
                    'code' => 'customer_not_found',
                    'column' => 'customer_id',
                    'message' => 'Ez az ügyfél nem található a kiválasztott cégnél.',
                ]]];
            }

            $existing = $company->invoices()->where('invoice_number', $data['invoice_number'])->first();
            if ($existing === null) {
                return [...$row, 'status' => 'new'];
            }

            $differences = $this->differences($existing, $data);

            return [...$row, 'status' => $differences === [] ? 'unchanged' : 'conflict', 'errors' => $differences];
        }, $rows);
    }

    /**
     * @param  InvoiceData  $data
     * @return list<CsvIssue>
     */
    private function differences(Invoice $invoice, array $data): array
    {
        $fields = [
            'customer_id' => $invoice->customer_id,
            'due_date' => $invoice->due_date->toDateString(),
            'gross_amount' => $invoice->gross_amount,
        ];

        $errors = [];
        foreach ($fields as $column => $value) {
            if ($value !== $data[$column]) {
                $errors[] = [
                    'code' => 'existing_invoice_mismatch',
                    'column' => $column,
                    'message' => 'A mező értéke eltér az azonos számlaszámú, már rögzített számláétól.',
                ];
            }
        }

        return $errors;
    }

    /** @param list<PreviewRow> $rows */
    public function canImport(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param  list<PreviewRow>  $rows
     * @return array{created_count: int, unchanged_count: int}
     */
    public function import(Organization $company, InvoiceImport $batch, array $rows): array
    {
        $created = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'new' && $row['data'] !== null) {
                $invoice = $company->invoices()->make($row['data']);
                $invoice->importBatch()->associate($batch);
                $invoice->forceFill(['source' => InvoiceSource::Csv->value])->save();
                $created++;
            }
        }

        return ['created_count' => $created, 'unchanged_count' => count($rows) - $created];
    }
}
