<?php

namespace App\Services;

use App\Enums\ReminderTestDeliveryStatus as Status;
use App\Models\Invoice;
use App\Models\ReminderApproval;
use App\Models\ReminderTestDelivery;
use Carbon\CarbonImmutable;

class ReminderDeliveryTiming
{
    private const int OPEN_HOUR = 8;

    private const int CLOSE_HOUR = 18;

    private const int COOL_DOWN_DAYS = 7;

    public function __construct(private readonly HungarianWorkCalendar $calendar) {}

    public function lastAttempt(Invoice $invoice): ?CarbonImmutable
    {
        return ReminderTestDelivery::query()
            ->whereIn('reminder_approval_id', $invoice->reminderApprovals()->select('id'))
            ->whereIn('status', [Status::Accepted, Status::Unknown])
            ->latest('updated_at')
            ->first()?->updated_at->toImmutable()->utc();
    }

    /** @return array{at: CarbonImmutable|null, reason: string|null} */
    public function schedule(Invoice $invoice, ReminderApproval $approval): array
    {
        $now = now()->toImmutable()->utc();
        $expires = $approval->expires_at->toImmutable()->utc();
        $cooldown = $this->lastAttempt($invoice)?->addDays(self::COOL_DOWN_DAYS);

        if ($cooldown !== null && $cooldown->gte($expires)) {
            return ['at' => null, 'reason' => 'cooldown'];
        }

        $candidate = ($cooldown !== null && $cooldown->gt($now) ? $cooldown : $now)
            ->setTimezone(HungarianWorkCalendar::TIMEZONE);

        while ($candidate->lt($expires)) {
            $workday = $this->calendar->isWorkday($candidate);
            if ($workday === null) {
                return ['at' => null, 'reason' => 'calendar_unavailable'];
            }

            if ($workday && $candidate->hour < self::CLOSE_HOUR) {
                $opening = $candidate->setTime(self::OPEN_HOUR, 0);
                $slot = $candidate->lt($opening) ? $opening : $candidate;
                if ($slot->lt($expires)) {
                    return ['at' => $slot->utc(), 'reason' => null];
                }
            }

            $candidate = $candidate->addDay()->setTime(self::OPEN_HOUR, 0);
        }

        return ['at' => null, 'reason' => 'outside_window'];
    }
}
