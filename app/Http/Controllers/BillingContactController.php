<?php

namespace App\Http\Controllers;

use App\Enums\BillingContactAction;
use App\Http\Requests\Customers\ConfirmBillingContactRequest;
use App\Http\Requests\Customers\RevokeBillingContactConfirmationRequest;
use App\Http\Requests\Customers\UpdateBillingContactRequest;
use App\Models\Customer;
use App\Models\CustomerBillingContactEvent;
use App\Models\User;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BillingContactController extends Controller
{
    public function edit(Request $request, string $organization, string $customer): Response
    {
        $user = $request->user();
        $company = $user->findActiveOrganization($organization);
        $contact = $company->customers()->findOrFail($customer);

        $events = $contact->billingContactEvents()
            ->where('occurred_at', '>', now()->subDays(CustomerBillingContactEvent::RETENTION_DAYS))
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static fn (CustomerBillingContactEvent $event): array => [
                'id' => (string) $event->id,
                'action' => $event->action->value,
                'actor' => $event->actor?->name,
                'occurredAt' => $event->occurred_at->toISOString(),
            ]);

        return Inertia::render('organizations/billing-contact', [
            'organizationId' => $organization,
            'canManageReminders' => $user->can('approveReminder', $company),
            'billingContactEvents' => $events,
            'customer' => [
                'id' => (string) $contact->id,
                'name' => $contact->display_name,
                'billingEmail' => $contact->billing_email,
                'confirmed' => $contact->billing_email_confirmed_at !== null,
                'revision' => $contact->billing_email_revision,
                'doNotContact' => $contact->do_not_contact,
            ],
        ]);
    }

    public function update(
        UpdateBillingContactRequest $request,
        string $organization,
        string $customer,
        ReminderTestQueue $queue,
    ): RedirectResponse {
        $company = $request->user()->findActiveOrganization($organization);
        $contact = $company->customers()->findOrFail($customer);
        $email = $request->validated('billing_email');
        $revision = (int) $request->validated('revision');

        if ($contact->billing_email_revision !== $revision) {
            $this->changedContact('address');
        }

        if ($contact->billing_email !== $email) {
            $updated = Customer::query()->whereKey($contact->id)
                ->where('billing_email_revision', $revision)
                ->increment('billing_email_revision', 1, [
                    'billing_email' => $email,
                    'billing_email_confirmed_at' => null,
                    'billing_email_confirmed_by' => null,
                ]);

            if ($updated === 0) {
                $this->changedContact('address');
            }

            $this->recordEvent($contact, $request->user(), BillingContactAction::AddressChanged);
            $queue->blockPending($company, ReminderDeliveryControls::BILLING_CONTACT_CHANGED, $contact);
        }

        return to_route('organizations.customers.billing-contact.edit', [$organization, $customer]);
    }

    public function confirm(ConfirmBillingContactRequest $request, string $organization, string $customer): RedirectResponse
    {
        $contact = $this->customer($request, $organization, $customer);
        $revision = (int) $request->validated('revision');

        if ($contact->billing_email_revision !== $revision) {
            $this->changedContact('confirmation');
        }

        if ($contact->billing_email === null) {
            throw ValidationException::withMessages([
                'billing_email' => 'A megerősítéshez először ments egy pénzügyi e-mail-címet.',
            ])->errorBag('confirmation');
        }

        if ($contact->billing_email_confirmed_at === null) {
            $updated = Customer::query()->whereKey($contact->id)
                ->where('billing_email_revision', $revision)
                ->increment('billing_email_revision', 1, [
                    'billing_email_confirmed_at' => now(),
                    'billing_email_confirmed_by' => $request->user()->id,
                ]);

            if ($updated === 0) {
                $this->changedContact('confirmation');
            }

            $this->recordEvent($contact, $request->user(), BillingContactAction::Confirmed);
        }

        return to_route('organizations.customers.billing-contact.edit', [$organization, $customer]);
    }

    public function revoke(
        RevokeBillingContactConfirmationRequest $request,
        string $organization,
        string $customer,
        ReminderTestQueue $queue,
    ): RedirectResponse {
        $company = $request->user()->findActiveOrganization($organization);
        $contact = $company->customers()->findOrFail($customer);
        $revision = (int) $request->validated('revision');

        if ($contact->billing_email_revision !== $revision) {
            $this->changedContact('revocation');
        }

        if ($contact->billing_email_confirmed_at !== null) {
            $updated = Customer::query()->whereKey($contact->id)
                ->where('billing_email_revision', $revision)
                ->increment('billing_email_revision', 1, [
                    'billing_email_confirmed_at' => null,
                    'billing_email_confirmed_by' => null,
                ]);

            if ($updated === 0) {
                $this->changedContact('revocation');
            }

            $this->recordEvent($contact, $request->user(), BillingContactAction::ConfirmationRevoked);
            $queue->blockPending($company, ReminderDeliveryControls::BILLING_CONFIRMATION_REVOKED, $contact);
        }

        return to_route('organizations.customers.billing-contact.edit', [$organization, $customer]);
    }

    private function recordEvent(Customer $contact, User $actor, BillingContactAction $action): void
    {
        $contact->billingContactEvents()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'occurred_at' => now(),
        ]);
    }

    private function customer(Request $request, string $organization, string $customer): Customer
    {
        return $request->user()->findActiveOrganization($organization)->customers()->findOrFail($customer);
    }

    private function changedContact(string $bag): never
    {
        throw ValidationException::withMessages([
            'revision' => 'A kapcsolattartó adatai közben változtak. Ellenőrizd a jelenlegi címet, majd próbáld újra.',
        ])->errorBag($bag);
    }
}
