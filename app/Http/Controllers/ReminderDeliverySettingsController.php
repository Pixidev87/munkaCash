<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminders\UpdateContactRestrictionRequest;
use App\Http\Requests\Reminders\UpdateReminderSettingRequest;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReminderDeliverySettingsController extends Controller
{
    public function organization(UpdateReminderSettingRequest $request, string $organization, ReminderTestQueue $queue): RedirectResponse
    {
        $user = $request->user();
        $enabled = $request->enabled();

        DB::transaction(function () use ($user, $organization, $enabled, $queue): void {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            Gate::forUser($user)->authorize('approveReminder', $company);

            $company->forceFill(['reminders_enabled' => $enabled])->save();

            if (! $enabled) {
                $queue->blockPending($company, ReminderDeliveryControls::ORGANIZATION_PAUSED);
            }
        }, attempts: 3);

        return to_route('organizations.show', $organization);
    }

    public function customer(UpdateContactRestrictionRequest $request, string $organization, string $customer, ReminderTestQueue $queue): RedirectResponse
    {
        $user = $request->user();
        $blocked = $request->isBlocked();

        DB::transaction(function () use ($user, $organization, $customer, $blocked, $queue): void {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            $contact = $company->customers()->lockForUpdate()->findOrFail($customer);
            Gate::forUser($user)->authorize('approveReminder', $company);

            if ($contact->do_not_contact !== $blocked) {
                $contact->forceFill([
                    'do_not_contact' => $blocked,
                    'do_not_contact_updated_at' => now(),
                    'do_not_contact_updated_by' => $user->id,
                ])->save();
            }

            if ($blocked) {
                $queue->blockPending($company, ReminderDeliveryControls::CUSTOMER_BLOCKED, $contact);
            }
        }, attempts: 3);

        return to_route('organizations.customers.billing-contact.edit', [$organization, $customer]);
    }
}
