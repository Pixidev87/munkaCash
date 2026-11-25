<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ReminderTestDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReminderDeliveryLogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ReminderTestDelivery>
     */
    public function matching(Organization $company, array $filters): Builder
    {
        return ReminderTestDelivery::query()
            ->join('reminder_approvals', 'reminder_approvals.id', '=', 'reminder_test_deliveries.reminder_approval_id')
            ->whereIn('reminder_approvals.invoice_id', $company->invoices()->select('id'))
            ->when(isset($filters['invoice_number']), fn ($query) => $query->where('reminder_approvals.invoice_number', $filters['invoice_number']))
            ->when(isset($filters['status']), fn ($query) => $query->where('reminder_test_deliveries.status', $filters['status']))
            ->when(isset($filters['reason']), fn ($query) => $query->where('reminder_test_deliveries.reason', $filters['reason']))
            ->when(isset($filters['queued_from']), fn ($query) => $query->where(
                'reminder_test_deliveries.created_at', '>=',
                CarbonImmutable::parse($filters['queued_from'], HungarianWorkCalendar::TIMEZONE)->startOfDay()->utc(),
            ))
            ->when(isset($filters['queued_to']), fn ($query) => $query->where(
                'reminder_test_deliveries.created_at', '<=',
                CarbonImmutable::parse($filters['queued_to'], HungarianWorkCalendar::TIMEZONE)->endOfDay()->utc(),
            ));
    }
}
