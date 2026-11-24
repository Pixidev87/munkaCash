<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;

/**
 * @phpstan-import-type CsvRow from InvoiceCsvParser
 * @phpstan-import-type InvoiceData from InvoiceCsvParser
 *
 * @phpstan-type PreviewRow array{line: int, data: InvoiceData|null, errors: list<string>, status: 'invalid'|'conflict'|'unchanged'|'new'}
 */
class InvoiceCsvImport
{
    /**
     * @param  list<CsvRow>  $rows
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

        $customers = $company->customers()->whereKey(array_unique($customerIds))->pluck('id')->flip();

        return array_map(function (array $row) use ($company, $numbers, $customers): array {
            $data = $row['data'];
            if ($data === null) {
                return [...$row, 'status' => 'invalid'];
            }

            $duplicateLines = $numbers[mb_strtolower($data['invoice_number'])];
            if (count($duplicateLines) > 1) {
                return [...$row, 'status' => 'conflict', 'errors' => [
                    'invoice_number: ismétlődő számlaszám a fájlban ('.implode(', ', $duplicateLines).').',
                ]];
            }

            if (! $customers->has($data['customer_id'])) {
                return [...$row, 'status' => 'invalid', 'errors' => [
                    'customer_id: ez az ügyfél nem található a kiválasztott cégnél.',
                ]];
            }

            $existing = $company->invoices()->where('invoice_number', $data['invoice_number'])->first();
            if ($existing === null) {
                return [...$row, 'status' => 'new'];
            }

            if ($this->matches($existing, $data)) {
                return [...$row, 'status' => 'unchanged'];
            }

            return [...$row, 'status' => 'conflict', 'errors' => [
                'invoice_number: a meglévő számla ügyfele, határideje vagy bruttó összege eltér.',
            ]];
        }, $rows);
    }

    /** @param InvoiceData $data */
    private function matches(Invoice $invoice, array $data): bool
    {
        return $invoice->customer_id === $data['customer_id']
            && $invoice->due_date->toDateString() === $data['due_date']
            && $invoice->gross_amount === $data['gross_amount'];
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
    public function import(Organization $company, array $rows): array
    {
        $created = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'new' && $row['data'] !== null) {
                $company->invoices()->make($row['data'])->forceFill(['source' => 'csv'])->save();
                $created++;
            }
        }

        return ['created_count' => $created, 'unchanged_count' => count($rows) - $created];
    }
}
