<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\StartCloseoutRequest;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrganizationCloseoutController extends Controller
{
    public function store(StartCloseoutRequest $request, string $organization, ReminderTestQueue $queue): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $organization, $queue): void {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            Gate::forUser($user)->authorize('closeout', $company);

            if ($company->isClosing()) {
                return;
            }

            $now = now();
            $company->forceFill([
                'closeout_started_at' => $now,
                'closeout_started_by' => $user->id,
                'export_until' => $now->copy()->addDays(30),
                'reminders_enabled' => false,
            ])->save();
            $queue->blockPending($company, ReminderDeliveryControls::ORGANIZATION_CLOSING);
        }, attempts: 3);

        return to_route('organizations.show', $organization);
    }
}
