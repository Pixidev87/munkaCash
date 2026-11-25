<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReminderPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_address_is_invalidated_when_changed_and_stale_confirmation_is_rejected(): void
    {
        [$company, $user, $invoice] = $this->makeInvoice();
        $customer = $invoice->customer;
        $params = [$company, $customer];
        $update = route('organizations.customers.billing-contact.update', $params);
        $confirm = route('organizations.customers.billing-contact.confirm', $params);
        $edit = route('organizations.customers.billing-contact.edit', $params);

        $this->actingAs($user)->patch($update, [
            'billing_email' => 'billing@example.test',
            'revision' => 0,
        ])->assertRedirect($edit);

        $this->get($edit)->assertInertia(fn (Assert $page) => $page
            ->component('organizations/billing-contact')
            ->where('customer.billingEmail', 'billing@example.test')
            ->where('customer.confirmed', false));

        $this->post($confirm, ['revision' => 1])->assertRedirect($edit);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'billing_email_confirmed_by' => $user->id,
            'billing_email_revision' => 2,
        ]);

        $this->patch($update, ['billing_email' => 'billing@example.test', 'revision' => 2])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($customer->refresh()->billing_email_confirmed_at);

        $this->patch($update, ['billing_email' => 'new@example.test', 'revision' => 2])
            ->assertSessionHasNoErrors();
        $this->post($confirm, ['revision' => 2])->assertSessionHasErrorsIn('confirmation', 'revision');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'billing_email' => 'new@example.test',
            'billing_email_confirmed_at' => null,
            'billing_email_confirmed_by' => null,
            'billing_email_revision' => 3,
        ]);
    }

    public function test_contact_rejects_invalid_email_and_can_be_removed(): void
    {
        [$company, $user, $invoice] = $this->makeInvoice();
        $customer = $invoice->customer;
        $update = route('organizations.customers.billing-contact.update', [$company, $customer]);

        $this->actingAs($user)->patch($update, ['billing_email' => 'invalid', 'revision' => 0])
            ->assertSessionHasErrorsIn('address', 'billing_email');

        $customer->forceFill([
            'billing_email' => 'billing@example.test',
            'billing_email_confirmed_at' => now(),
            'billing_email_confirmed_by' => $user->id,
        ])->save();

        $this->patch($update, ['billing_email' => '', 'revision' => 0])->assertSessionHasNoErrors();
        $this->post(route('organizations.customers.billing-contact.confirm', [$company, $customer]), ['revision' => 1])
            ->assertSessionHasErrorsIn('confirmation', 'billing_email');
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'billing_email' => null,
            'billing_email_confirmed_by' => null,
            'billing_email_confirmed_at' => null,
        ]);
    }

    public function test_preview_uses_the_verified_partial_balance_and_does_not_dispatch_mail(): void
    {
        Mail::fake();
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-12-01 23:30:00', 'UTC'));
        [$company, $user, $invoice] = $this->makeInvoice();
        $invoice->forceFill(['invoice_number' => "00042\nMinta"])->save();
        $this->checkBalance($invoice, 80_000);
        $this->confirmAddress($invoice);

        $this->actingAs($user)
            ->get(route('organizations.invoices.reminder-preview', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/reminder-preview')
                ->where('dueDate', '2026-12-01')
                ->where('outstanding', 80_000)
                ->where('checkedAt', now()->toISOString())
                ->has('blockers', 0)
                ->where('message.recipient', 'billing@example.test')
                ->where('message.subject', 'Fizetési emlékeztető')
                ->where('message.body', fn (string $body): bool => str_contains($body, '00042 Minta')
                    && str_contains($body, "80 000\u{00A0}Ft")
                    && ! str_contains($body, '120 000')
                    && str_contains($body, 'Adatkezelő: Minta Vállalkozás Kft.')));

        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_preview_explains_missing_balance_and_address_and_excludes_paid_or_not_due_invoices(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 12:00:00', 'Europe/Budapest'));
        [$company, $user, $invoice] = $this->makeInvoice();
        $url = route('organizations.invoices.reminder-preview', [$company, $invoice]);

        $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('blockers', 3)
            ->where('message', null));

        $this->checkBalance($invoice, 80_000);
        $invoice->customer->forceFill(['billing_email' => 'billing@example.test'])->save();

        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('blockers', 2)
            ->where('message', null));

        $this->confirmAddress($invoice);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('blockers', ['A számla fizetési határideje még nem járt le.'])
            ->where('message', null));

        $this->travel(1)->day();
        $this->checkBalance($invoice, 0);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('blockers', ['A számla teljesen rendezett.'])
            ->where('message', null));
    }

    public function test_preview_waits_until_after_the_adjusted_deadline_in_budapest(): void
    {
        $this->travelTo(Carbon::parse('2026-12-28 22:59:59', 'UTC'));
        [$company, $user, $invoice] = $this->makeInvoice();
        $invoice->forceFill(['due_date' => '2026-12-24'])->save();
        $this->checkBalance($invoice, 80_000);
        $this->confirmAddress($invoice);
        $url = route('organizations.invoices.reminder-preview', [$company, $invoice]);

        $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('dueDate', '2026-12-24')
            ->where('effectiveDueDate', '2026-12-28')
            ->where('blockers', ['A számla fizetési határideje még nem járt le.'])
            ->where('message', null));

        $this->travel(1)->second();
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('effectiveDueDate', '2026-12-28')
            ->has('blockers', 0)
            ->where('message.recipient', 'billing@example.test'));

        $invoice->forceFill(['due_date' => '2027-01-02'])->save();
        config(['reminder-calendar.2027' => null]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('effectiveDueDate', null)
            ->where('blockers', ['A fizetési határidőhöz még nincs ellenőrzött munkanaptár.'])
            ->where('message', null));
    }

    public function test_another_company_cannot_access_the_preview_or_change_the_recipient(): void
    {
        [$company, $user, $invoice] = $this->makeInvoice();
        [$other, , $foreign] = $this->makeInvoice();

        $this->actingAs($user)
            ->get(route('organizations.invoices.reminder-preview', [$other, $foreign]))->assertNotFound();
        $this->get(route('organizations.invoices.reminder-preview', [$company, $foreign]))->assertNotFound();
        $params = [$company, $foreign->customer];
        $this->get(route('organizations.customers.billing-contact.edit', $params))->assertNotFound();
        $this->patch(route('organizations.customers.billing-contact.update', $params), [
            'billing_email' => 'billing@example.test',
            'revision' => 0,
        ])->assertNotFound();
        $this->post(route('organizations.customers.billing-contact.confirm', $params), ['revision' => 0])
            ->assertNotFound();

        $user->organizations()->updateExistingPivot($company->id, ['status' => 'inactive']);
        $this->get(route('organizations.invoices.reminder-preview', [$company, $invoice]))->assertNotFound();
        $this->assertNull($foreign->customer->refresh()->billing_email);
    }

    /** @return array{Organization, User, Invoice} */
    private function makeInvoice(): array
    {
        $company = Organization::factory()->create(['name' => 'Minta Vállalkozás Kft.']);
        $user = User::factory()->create();
        $user->organizations()->attach($company, ['status' => 'active']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél Kft.']);
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);

        return [$company, $user, $invoice];
    }

    private function checkBalance(Invoice $invoice, int $outstanding): void
    {
        $invoice->forceFill([
            'outstanding_amount' => $outstanding,
            'status' => $outstanding === 0 ? 'paid' : 'partially_paid',
            'status_checked_at' => now(),
        ])->save();
    }

    private function confirmAddress(Invoice $invoice): void
    {
        $invoice->customer->forceFill([
            'billing_email' => 'billing@example.test',
            'billing_email_confirmed_at' => now(),
        ])->save();
    }
}
