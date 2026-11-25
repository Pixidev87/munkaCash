<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminders\RevokeReminderApprovalRequest;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReminderApprovalRevocationController extends Controller
{
    public function destroy(
        RevokeReminderApprovalRequest $request,
        string $organization,
        string $invoice,
        ReminderTestQueue $queue,
    ): RedirectResponse {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $selected = $company->invoices()->findOrFail($invoice);
        Gate::forUser($user)->authorize('approveReminder', $company);

        $approval = $selected->reminderApprovals()->latest('id')->first();
        if ($approval === null || $approval->id !== $request->approvalId()) {
            throw ValidationException::withMessages([
                'approval_id' => 'A jóváhagyás közben megváltozott. Ellenőrizd a friss állapotot.',
            ])->errorBag('approvalRevocation');
        }

        if ($approval->revoked_at === null && $approval->expires_at->isFuture()) {
            $approval->forceFill(['revoked_at' => now(), 'revoked_by' => $user->id])->save();
            $queue->blockPendingForApproval($approval, ReminderDeliveryControls::APPROVAL_REVOKED);
        }

        return to_route('organizations.invoices.reminder-preview', [$organization, $invoice]);
    }
}
