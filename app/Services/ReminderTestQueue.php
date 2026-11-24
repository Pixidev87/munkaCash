<?php

namespace App\Services;

use App\Enums\ReminderTestDeliveryStatus as Status;
use App\Jobs\SendReminderTest;
use App\Models\ReminderApproval;
use App\Models\ReminderTestDelivery;

class ReminderTestQueue
{
    public const string NAME = 'reminder-tests';

    public function dispatchPending(): void
    {
        // An interrupted SMTP attempt may already have reached Mailpit.
        ReminderTestDelivery::query()->where('status', Status::Sending)
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->update(['status' => Status::Unknown, 'reason' => 'transport_unconfirmed']);

        ReminderTestDelivery::query()->where('status', Status::Pending)
            ->whereIn('reminder_approval_id', ReminderApproval::query()->select('id')->where('expires_at', '<=', now()))
            ->update(['status' => Status::Blocked, 'reason' => 'approval_expired']);

        ReminderTestDelivery::query()->where('status', Status::Pending)
            ->orderBy('id')
            ->eachById(static function (ReminderTestDelivery $delivery): void {
                SendReminderTest::dispatch($delivery->id)->onQueue(self::NAME);
            });
    }
}
