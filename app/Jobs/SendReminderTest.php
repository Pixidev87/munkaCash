<?php

namespace App\Jobs;

use App\Enums\ReminderTestDeliveryStatus as Status;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderApproval;
use App\Models\ReminderTestDelivery;
use App\Services\ReminderApprovalStatus;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderDeliveryTiming;
use App\Services\ReminderPreview;
use App\Services\ReminderTestMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendReminderTest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(ReminderPreview $preview, ReminderApprovalStatus $status, ReminderTestMailer $mailer, ReminderDeliveryControls $controls, ReminderDeliveryTiming $timing): void
    {
        $claimed = ReminderTestDelivery::query()->whereKey($this->deliveryId)
            ->where('status', Status::Pending)
            ->where(static fn (Builder $query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->update(['status' => Status::Sending]);

        if ($claimed === 0) {
            return;
        }

        try {
            $delivery = ReminderTestDelivery::query()->find($this->deliveryId);
            $approval = $delivery === null ? null : ReminderApproval::query()->find($delivery->reminder_approval_id);
            $originalInvoice = $approval === null ? null : Invoice::query()->find($approval->invoice_id);
            if ($originalInvoice === null) {
                $this->finish(Status::Blocked, 'missing_record');

                return;
            }

            DB::transaction(function () use ($originalInvoice, $preview, $status, $mailer, $controls, $timing): void {
                $company = Organization::query()->lockForUpdate()->find($originalInvoice->organization_id);
                $invoice = $company?->invoices()->lockForUpdate()->find($originalInvoice->id);
                $customer = $invoice === null ? null : $company->customers()->lockForUpdate()->find($invoice->customer_id);
                $approval = $invoice?->reminderApprovals()->lockForUpdate()->latest('id')->first();
                $delivery = ReminderTestDelivery::query()->lockForUpdate()->find($this->deliveryId);
                if ($delivery?->status !== Status::Sending) {
                    return;
                }

                if ($company === null || $invoice === null || $customer === null || $approval === null || $approval->id !== $delivery->reminder_approval_id) {
                    $this->finish(Status::Blocked, 'approval_changed');

                    return;
                }

                $invoice->setRelation('customer', $customer);
                $blocker = $controls->blocker($company, $invoice);
                if ($blocker !== null) {
                    $this->finish(Status::Blocked, $blocker);

                    return;
                }

                $draft = $preview->prepare($company, $invoice);
                $approvalStatus = $status->evaluate($company, $approval, $draft['fingerprint']);
                if ($approvalStatus !== 'active' || $draft['message'] === null) {
                    $this->finish(Status::Blocked, match ($approvalStatus) {
                        'expired' => 'approval_expired',
                        'revoked' => ReminderDeliveryControls::APPROVAL_REVOKED,
                        default => 'approval_changed',
                    });

                    return;
                }

                $schedule = $timing->schedule($invoice, $approval);
                if ($schedule['at'] === null) {
                    $this->finish(Status::Blocked, $schedule['reason']);

                    return;
                }

                if ($schedule['at']->gt(now())) {
                    $delivery->forceFill(['status' => Status::Pending, 'available_at' => $schedule['at']])->save();

                    return;
                }

                $message = $draft['message'];
                if (! $mailer->accepts($message['recipient'])) {
                    $this->finish(Status::Blocked, 'test_address_required');

                    return;
                }

                if ($mailer->send($delivery->id, $message['recipient'], $message['subject'], $message['body'])) {
                    $this->finish(Status::Accepted);

                    return;
                }

                $this->finish(Status::Unknown, 'transport_unconfirmed');
            });
        } catch (Throwable $exception) {
            // A transport error can occur after SMTP accepted the message.
            $this->finish(Status::Unknown, 'transport_unconfirmed');
            logger()->warning('Reminder test delivery could not be confirmed.', [
                'delivery_id' => $this->deliveryId,
                'exception_type' => $exception::class,
            ]);
        }
    }

    private function finish(Status $status, ?string $reason = null): void
    {
        ReminderTestDelivery::query()->whereKey($this->deliveryId)
            ->where('status', Status::Sending)
            ->update(['status' => $status, 'reason' => $reason]);
    }
}
