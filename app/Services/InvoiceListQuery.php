<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

class InvoiceListQuery
{
    public function __construct(private readonly OverdueInvoices $overdueInvoices) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public function matching(Organization $company, array $filters): Builder
    {
        return $company->invoices()->getQuery()
            ->when(isset($filters['invoice_number']), fn ($query) => $query->where('invoice_number', $filters['invoice_number']))
            ->when(isset($filters['customer_id']), fn ($query) => $query->where('customer_id', (int) $filters['customer_id']))
            ->when(isset($filters['source']), fn ($query) => $query->where('source', $filters['source']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['disputed']), fn ($query) => $query->where('is_disputed', true))
            ->when(isset($filters['overdue']), fn ($query) => $query->whereIn('id', $this->overdueInvoices->queryFor($company)->select('id')))
            ->when(isset($filters['awaiting_balance']), fn ($query) => $query
                ->where('currency', 'HUF')
                ->where(fn (Builder $balance) => $balance->whereNull('status_checked_at')->orWhereNull('outstanding_amount')))
            ->when(isset($filters['billing_unconfirmed']), fn ($query) => $query
                ->whereIn('customer_id', $company->customers()->withoutConfirmedBillingAddress()->select('id')))
            ->when(isset($filters['due_from']), fn ($query) => $query->whereDate('due_date', '>=', $filters['due_from']))
            ->when(isset($filters['due_to']), fn ($query) => $query->whereDate('due_date', '<=', $filters['due_to']));
    }
}
