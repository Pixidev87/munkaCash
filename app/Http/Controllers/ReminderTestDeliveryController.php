<?php

namespace App\Http\Controllers;

use App\Enums\ReminderTestDeliveryStatus;
use App\Http\Requests\Reminders\QueueReminderTestRequest;
use App\Services\ReminderApprovalStatus;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderDeliveryTiming;
use App\Services\ReminderPreview;
use App\Services\ReminderTestMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReminderTestDeliveryController extends Controller
{
    public function store(QueueReminderTestRequest $request, string $organization, string $invoice, ReminderPreview $preview, ReminderApprovalStatus $status, ReminderTestMailer $mailer, ReminderDeliveryControls $controls, ReminderDeliveryTiming $timing): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $organization, $invoice, $preview, $status, $mailer, $controls, $timing): void {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            $selected = $company->invoices()->lockForUpdate()->findOrFail($invoice);
            Gate::forUser($user)->authorize('approveReminder', $company);

            $blocker = $controls->blocker($company, $selected);
            if ($blocker !== null) {
                throw ValidationException::withMessages([
                    'delivery' => match ($blocker) {
                        ReminderDeliveryControls::ORGANIZATION_PAUSED => 'A cég emlékeztetői szünetelnek.',
                        ReminderDeliveryControls::INVOICE_DISPUTED => 'A számla vitatott.',
                        default => 'Az ügyfél emlékeztetői tiltva vannak.',
                    },
                ]);
            }

            $approval = $selected->reminderApprovals()->with('approver')->latest('id')->first();
            $draft = $preview->prepare($company, $selected);
            if ($approval === null
                || $approval->id !== $request->approvalId()
                || $status->evaluate($company, $approval, $draft['fingerprint']) !== 'active'
                || $draft['message'] === null) {
                throw ValidationException::withMessages(['delivery' => 'Friss, érvényes jóváhagyás szükséges a tesztküldéshez.']);
            }

            if (! $mailer->accepts($draft['message']['recipient'])) {
                throw ValidationException::withMessages(['delivery' => 'A tesztküldéshez .test végződésű cím szükséges.']);
            }

            if ($approval->testDelivery()->exists()) {
                return;
            }

            $schedule = $timing->schedule($selected, $approval);
            if ($schedule['at'] === null) {
                throw ValidationException::withMessages(['delivery' => match ($schedule['reason']) {
                    'cooldown' => 'A számlához a legutóbbi küldési kísérlettől számítva hét napnak kell eltelnie.',
                    'calendar_unavailable' => 'Ehhez az évhez még nincs ellenőrzött munkanaptár.',
                    default => 'A következő küldési ablak a jóváhagyás lejárta után nyílik.',
                }]);
            }

            $approval->testDelivery()->create([
                'status' => ReminderTestDeliveryStatus::Pending,
                'available_at' => $schedule['at'],
            ]);
        }, attempts: 3);

        return to_route('organizations.invoices.reminder-preview', [$organization, $invoice]);
    }
}
