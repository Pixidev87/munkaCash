<?php

namespace App\Services;

use App\Enums\ReminderTestDeliveryStatus as Status;
use App\Jobs\SendReminderTest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderApproval;
use App\Models\ReminderTestDelivery;
use Illuminate\Database\Eloquent\Builder;

class ReminderTestQueue
{
    public const string NAME = 'reminder-tests';

    public function blockPending(Organization $company, string $reason, ?Customer $customer = null): void
    {
        $invoices = $company->invoices()->select('id');
        if ($customer !== null) {
            $invoices->where('customer_id', $customer->id);
        }

        $this->blockPendingForInvoices($invoices->getQuery(), $reason);
    }

    public function blockPendingForInvoice(Invoice $invoice, string $reason): void
    {
        $this->blockPendingForInvoices(Invoice::query()->whereKey($invoice->id)->select('id'), $reason);
    }

    /** @param Builder<Invoice> $invoices */
    private function blockPendingForInvoices(Builder $invoices, string $reason): void
    {
        $deliveries = ReminderTestDelivery::query()
            ->whereIn('reminder_approval_id', ReminderApproval::query()->select('id')->whereIn('invoice_id', $invoices));

        (clone $deliveries)->where('status', Status::Pending)
            ->update(['status' => Status::Blocked, 'reason' => $reason]);

        // A claimed job may have already reached SMTP before the worker stopped.
        $deliveries->where('status', Status::Sending)
            ->update(['status' => Status::Unknown, 'reason' => 'transport_unconfirmed']);
    }

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
            ->where(static fn (Builder $query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->eachById(static function (ReminderTestDelivery $delivery): void {
                SendReminderTest::dispatch($delivery->id)->onQueue(self::NAME);
            });
    }
}
