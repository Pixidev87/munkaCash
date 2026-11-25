<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Organization;

class OrganizationBalanceSummary
{
    public function __construct(private readonly OverdueInvoices $overdueInvoices) {}

    /** @return array{outstanding: string, overdue: string, disputed: string, unchecked: int, overdueWithoutConfirmedAddress: int} */
    public function forOrganization(Organization $company): array
    {
        $openStatuses = [InvoiceStatus::Open->value, InvoiceStatus::PartiallyPaid->value];
        $balances = $company->invoices()->toBase()
            ->where('currency', 'HUF')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_checked_at IS NOT NULL AND status IN (?, ?) AND is_disputed = ? THEN outstanding_amount END), 0) AS outstanding',
                [...$openStatuses, false],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_checked_at IS NOT NULL AND status IN (?, ?) AND is_disputed = ? THEN outstanding_amount END), 0) AS disputed',
                [...$openStatuses, true],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status_checked_at IS NULL OR outstanding_amount IS NULL THEN 1 END) AS unchecked',
            )
            ->first();

        $unconfirmedCustomers = $company->customers()->withoutConfirmedBillingAddress()->select('id')->toBase();
        $overdue = $this->overdueInvoices->queryFor($company)
            ->leftJoinSub($unconfirmedCustomers, 'unconfirmed_customers', 'invoices.customer_id', '=', 'unconfirmed_customers.id')
            ->selectRaw('COALESCE(SUM(invoices.outstanding_amount), 0) AS total, COUNT(unconfirmed_customers.id) AS without_confirmed_address')
            ->first();

        return [
            'overdue' => (string) ($overdue->total ?? 0),
            'overdueWithoutConfirmedAddress' => (int) ($overdue->without_confirmed_address ?? 0),
            'outstanding' => (string) ($balances->outstanding ?? 0),
            'disputed' => (string) ($balances->disputed ?? 0),
            'unchecked' => (int) ($balances->unchecked ?? 0),
        ];
    }
}
