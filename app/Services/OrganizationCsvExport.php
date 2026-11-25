<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderTestDelivery;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationCsvExport
{
    public function customers(Organization $organization): StreamedResponse
    {
        $rows = $organization->customers()->lazyById()
            ->map(fn (Customer $customer): array => [
                $customer->id,
                $customer->display_name,
                $customer->billing_email,
                $customer->billing_email_confirmed_at?->toISOString(),
                $customer->do_not_contact ? 1 : 0,
            ]);

        return SpreadsheetCsv::download('munkacash-ugyfelek-'.$organization->id.'.csv', [
            'customer_id', 'display_name', 'billing_email', 'billing_email_confirmed_at_utc', 'do_not_contact',
        ], $rows);
    }

    public function invoices(Organization $organization): StreamedResponse
    {
        return $this->invoiceRows($organization->invoices()->getQuery(), 'munkacash-szamlak-'.$organization->id.'.csv');
    }

    /** @param  Builder<Invoice>  $matching */
    public function filteredInvoices(Organization $organization, Builder $matching): StreamedResponse
    {
        return $this->invoiceRows($matching, 'munkacash-szurt-szamlak-'.$organization->id.'.csv');
    }

    /** @param  Builder<ReminderTestDelivery>  $matching */
    public function reminderDeliveries(Organization $organization, Builder $matching): StreamedResponse
    {
        $rows = $matching
            ->select([
                'reminder_test_deliveries.id', 'reminder_approvals.invoice_number',
                'reminder_test_deliveries.status', 'reminder_test_deliveries.reason',
                'reminder_test_deliveries.created_at', 'reminder_test_deliveries.available_at', 'reminder_test_deliveries.updated_at',
            ])
            ->lazyById(1000, 'reminder_test_deliveries.id', 'id')
            ->map(static fn (ReminderTestDelivery $delivery): array => [
                (string) $delivery->getAttribute('invoice_number'),
                $delivery->status->value,
                $delivery->reason,
                $delivery->created_at->toISOString(),
                $delivery->available_at?->toISOString(),
                $delivery->updated_at->toISOString(),
            ]);

        return SpreadsheetCsv::download('munkacash-tesztkuldesek-'.$organization->id.'.csv', [
            'invoice_number', 'status', 'reason', 'queued_at_utc', 'available_at_utc', 'updated_at_utc',
        ], $rows);
    }

    /** @param  Builder<Invoice>  $invoices */
    private function invoiceRows(Builder $invoices, string $filename): StreamedResponse
    {
        $rows = $invoices->lazyById()
            ->map(fn (Invoice $invoice): array => [
                $invoice->id,
                $invoice->customer_id,
                $invoice->invoice_number,
                $invoice->due_date->toDateString(),
                $invoice->currency,
                $invoice->gross_amount,
                $invoice->outstanding_amount,
                $invoice->status->value,
                $invoice->status_checked_at?->toISOString(),
                $invoice->is_disputed ? 1 : 0,
            ]);

        return SpreadsheetCsv::download($filename, [
            'invoice_id', 'customer_id', 'invoice_number', 'due_date', 'currency',
            'gross_amount', 'outstanding_amount', 'status', 'status_checked_at_utc', 'is_disputed',
        ], $rows);
    }
}
