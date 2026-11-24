<?php

namespace App\Http\Controllers;

use App\Enums\ReminderTestDeliveryStatus;
use App\Services\ReminderApprovalStatus;
use App\Services\ReminderPreview;
use App\Services\ReminderTestMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReminderTestDeliveryController extends Controller
{
    public function store(Request $request, string $organization, string $invoice, ReminderPreview $preview, ReminderApprovalStatus $status, ReminderTestMailer $mailer): RedirectResponse
    {
        abort_unless($mailer->enabled(), 404);
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $organization, $invoice, $preview, $status, $mailer): void {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            $selected = $company->invoices()->lockForUpdate()->findOrFail($invoice);
            Gate::forUser($user)->authorize('approveReminder', $company);

            $data = $request->validate(['approval_id' => ['required', 'integer', 'min:1']]);
            $approval = $selected->reminderApprovals()->with('approver')->latest('id')->first();
            $draft = $preview->prepare($company, $selected);
            if ($approval === null
                || $approval->id !== (int) $data['approval_id']
                || $status->evaluate($company, $approval, $draft['fingerprint']) !== 'active'
                || $draft['message'] === null) {
                throw ValidationException::withMessages(['delivery' => 'Friss, érvényes jóváhagyás szükséges a tesztküldéshez.']);
            }

            if (! $mailer->accepts($draft['message']['recipient'])) {
                throw ValidationException::withMessages(['delivery' => 'A tesztküldéshez .test végződésű cím szükséges.']);
            }

            $approval->testDelivery()->firstOrCreate([], ['status' => ReminderTestDeliveryStatus::Pending]);
        }, attempts: 3);

        return to_route('organizations.invoices.reminder-preview', [$organization, $invoice]);
    }
}
