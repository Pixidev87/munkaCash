<?php

namespace App\Http\Controllers;

use App\Services\ReminderApprovalStatus;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderDeliveryTiming;
use App\Services\ReminderPreview;
use App\Services\ReminderTestMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReminderPreviewController extends Controller
{
    private const int APPROVAL_MINUTES = 30;

    public function show(Request $request, string $organization, string $invoice, ReminderPreview $preview, ReminderApprovalStatus $status, ReminderTestMailer $mailer, ReminderDeliveryControls $controls, ReminderDeliveryTiming $timing): Response
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $selected = $company->invoices()->with('customer')->findOrFail($invoice);
        $draft = $preview->prepare($company, $selected);
        $latest = $selected->reminderApprovals()->with(['approver', 'testDelivery'])->latest('id')->first();
        $canApprove = $user->can('approveReminder', $company);
        $delivery = $latest?->testDelivery;

        return Inertia::render('organizations/reminder-preview', [
            'organizationId' => (string) $company->id,
            'customerId' => (string) $selected->customer_id,
            'invoiceId' => (string) $selected->id,
            'number' => $selected->invoice_number,
            'dueDate' => $selected->due_date->toDateString(),
            'gross' => $selected->gross_amount,
            'outstanding' => $selected->outstanding_amount,
            'checkedAt' => $selected->status_checked_at?->toISOString(),
            'blockers' => $draft['blockers'],
            'message' => $draft['message'],
            'canApprove' => $canApprove,
            'fingerprint' => $canApprove ? $draft['fingerprint'] : null,
            'approvalMinutes' => self::APPROVAL_MINUTES,
            'testDeliveryEnabled' => $mailer->enabled(),
            'deliveryBlocker' => $controls->blocker($company, $selected),
            'lastDeliveryAttemptAt' => $timing->lastAttempt($selected)?->toISOString(),
            'testRecipient' => $draft['message'] !== null && $mailer->accepts($draft['message']['recipient']),
            'testDelivery' => $delivery === null ? null : [
                'status' => $delivery->status->value,
                'reason' => $delivery->reason,
                'availableAt' => $delivery->available_at?->toISOString(),
            ],
            'approval' => $latest === null ? null : [
                'id' => (string) $latest->id,
                'status' => $status->evaluate($company, $latest, $draft['fingerprint']),
                'approvedBy' => $latest->approver?->name,
                'approvedAt' => $latest->approved_at->toISOString(),
                'expiresAt' => $latest->expires_at->toISOString(),
            ],
        ]);
    }

    public function approve(Request $request, string $organization, string $invoice, ReminderPreview $preview, ReminderApprovalStatus $status): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $organization, $invoice, $preview, $status): void {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            $selected = $company->invoices()->lockForUpdate()->findOrFail($invoice);
            Gate::forUser($user)->authorize('approveReminder', $company);

            $data = $request->validate([
                'fingerprint' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
                'previous_approval_id' => ['present', 'nullable', 'integer', 'min:1'],
            ]);

            $customer = $company->customers()->lockForUpdate()->findOrFail($selected->customer_id);
            $selected->setRelation('customer', $customer);
            $fingerprint = $preview->prepare($company, $selected)['fingerprint'];
            if ($fingerprint === null || ! hash_equals($fingerprint, $data['fingerprint']) || $selected->outstanding_amount === null) {
                $this->changedPreview();
            }

            $latest = $selected->reminderApprovals()->with('approver')->latest('id')->first();
            if ($latest !== null && $status->evaluate($company, $latest, $fingerprint) === 'active') {
                return;
            }

            $previous = $data['previous_approval_id'] === null ? null : (int) $data['previous_approval_id'];
            if ($latest?->id !== $previous) {
                $this->changedPreview();
            }

            $approvedAt = now();
            $selected->reminderApprovals()->create([
                'approved_by' => $user->id,
                'fingerprint' => $fingerprint,
                'template_version' => ReminderPreview::TEMPLATE_VERSION,
                'invoice_number' => $selected->invoice_number,
                'outstanding_amount' => $selected->outstanding_amount,
                'currency' => $selected->currency,
                'approved_at' => $approvedAt,
                'expires_at' => $approvedAt->copy()->addMinutes(self::APPROVAL_MINUTES),
            ]);
        }, attempts: 3);

        return to_route('organizations.invoices.reminder-preview', [$organization, $invoice]);
    }

    private function changedPreview(): never
    {
        throw ValidationException::withMessages([
            'fingerprint' => 'Az előnézet vagy a jóváhagyás közben változott. Nézd át a friss állapotot, majd hagyd jóvá újra.',
        ]);
    }
}
