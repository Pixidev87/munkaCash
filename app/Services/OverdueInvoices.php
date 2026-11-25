<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Organization;
use Illuminate\Database\Query\Builder;

class OverdueInvoices
{
    public function __construct(private readonly HungarianWorkCalendar $calendar) {}

    public function queryFor(Organization $company): Builder
    {
        $today = today(HungarianWorkCalendar::TIMEZONE)->toImmutable();
        $ranges = $this->calendar->overdueRanges($today);

        return $company->invoices()->toBase()
            ->where('currency', 'HUF')
            ->where('is_disputed', false)
            ->whereIn('status', [InvoiceStatus::Open->value, InvoiceStatus::PartiallyPaid->value])
            ->whereNotNull('status_checked_at')
            ->where('outstanding_amount', '>', 0)
            ->where(function (Builder $dates) use ($ranges): void {
                if ($ranges === []) {
                    $dates->whereRaw('1 = 0');
                }

                foreach ($ranges as [$start, $end]) {
                    $dates->orWhere(fn (Builder $date) => $date->where('due_date', '>=', $start)->where('due_date', '<', $end));
                }
            });
    }
}
