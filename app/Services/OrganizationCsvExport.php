<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationCsvExport
{
    public function customers(Organization $organization): StreamedResponse
    {
        $rows = $organization->customers()->lazyById()
            ->map(fn (Customer $customer): array => [
                $customer->id,
                $this->spreadsheetText($customer->display_name),
                $this->spreadsheetText($customer->billing_email),
                $customer->billing_email_confirmed_at?->toISOString(),
                $customer->do_not_contact ? 1 : 0,
            ]);

        return $this->download('ugyfelek', $organization, [
            'customer_id', 'display_name', 'billing_email', 'billing_email_confirmed_at_utc', 'do_not_contact',
        ], $rows);
    }

    public function invoices(Organization $organization): StreamedResponse
    {
        $rows = $organization->invoices()->lazyById()
            ->map(fn (Invoice $invoice): array => [
                $invoice->id,
                $invoice->customer_id,
                $this->spreadsheetText($invoice->invoice_number),
                $invoice->due_date->toDateString(),
                $invoice->currency,
                $invoice->gross_amount,
                $invoice->outstanding_amount,
                $invoice->status->value,
                $invoice->status_checked_at?->toISOString(),
            ]);

        return $this->download('szamlak', $organization, [
            'invoice_id', 'customer_id', 'invoice_number', 'due_date', 'currency',
            'gross_amount', 'outstanding_amount', 'status', 'status_checked_at_utc',
        ], $rows);
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<list<int|string|null>>  $rows
     */
    private function download(string $type, Organization $organization, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($header, $rows): void {
                $output = fopen('php://output', 'wb');

                if ($output === false) {
                    throw new \RuntimeException('Unable to open the CSV response stream.');
                }

                try {
                    fwrite($output, "\xEF\xBB\xBF");
                    fputcsv($output, $header, ';', '"', '', "\r\n");

                    foreach ($rows as $row) {
                        fputcsv($output, $row, ';', '"', '', "\r\n");
                    }
                } finally {
                    fclose($output);
                }
            },
            'munkacash-'.$type.'-'.$organization->id.'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function spreadsheetText(?string $value): ?string
    {
        if ($value !== null && preg_match('/\A(?:\p{Cc}|[\p{Z}\p{Cf}\s]*[=+@\-＝＋＠－])/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
