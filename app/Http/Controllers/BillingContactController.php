<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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

        return Inertia::render('organizations/billing-contact', [
            'organizationId' => $organization,
            'canManageReminders' => $user->can('approveReminder', $company),
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

    public function update(Request $request, string $organization, string $customer): RedirectResponse
    {
        $contact = $this->customer($request, $organization, $customer);
        $data = $request->validateWithBag('address', [
            'billing_email' => ['present', 'nullable', 'email:rfc', 'max:254'],
            'revision' => ['required', 'integer', 'min:0'],
        ], [
            'billing_email.email' => 'Adj meg érvényes e-mail-címet.',
            'billing_email.max' => 'Az e-mail-cím legfeljebb 254 karakter lehet.',
        ]);
        $revision = (int) $data['revision'];

        if ($contact->billing_email_revision !== $revision) {
            $this->changedContact('address');
        }

        if ($contact->billing_email !== $data['billing_email']) {
            $updated = Customer::query()->whereKey($contact->id)
                ->where('billing_email_revision', $revision)
                ->increment('billing_email_revision', 1, [
                    'billing_email' => $data['billing_email'],
                    'billing_email_confirmed_at' => null,
                    'billing_email_confirmed_by' => null,
                ]);

            if ($updated === 0) {
                $this->changedContact('address');
            }
        }

        return to_route('organizations.customers.billing-contact.edit', [$organization, $customer]);
    }

    public function confirm(Request $request, string $organization, string $customer): RedirectResponse
    {
        $contact = $this->customer($request, $organization, $customer);
        $data = $request->validateWithBag('confirmation', [
            'revision' => ['required', 'integer', 'min:0'],
        ]);
        $revision = (int) $data['revision'];

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
        }

        return to_route('organizations.customers.billing-contact.edit', [$organization, $customer]);
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
