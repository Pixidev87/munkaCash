<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationCloseoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_closeout_is_owner_confirmed_and_repeated_requests_do_not_extend_the_window(): void
    {
        $this->freezeSecond();
        $company = Organization::factory()->create(['reminders_enabled' => true]);
        $owner = $this->attach($company, 'owner');
        $member = $this->attach($company, 'member');
        $url = route('organizations.closeout', $company);
        $show = route('organizations.show', $company);

        $this->actingAs($member)->post($url, ['confirm' => '1'])->assertForbidden();
        $this->actingAs($owner)->post($url)->assertSessionHasErrors('confirm');
        $this->assertNull($company->refresh()->closeout_started_at);

        $this->post($url, ['confirm' => '1'])->assertRedirect($show);
        $started = now();
        $until = $started->copy()->addDays(30);

        $this->assertDatabaseHas('organizations', [
            'id' => $company->id,
            'closeout_started_by' => $owner->id,
            'closeout_started_at' => $started->toDateTimeString(),
            'export_until' => $until->toDateTimeString(),
            'reminders_enabled' => false,
        ]);
        $this->travel(1)->day();
        $this->post($url, ['confirm' => true])->assertRedirect($show);
        $this->get($show)->assertInertia(fn (Assert $page) => $page
            ->where('closeout.startedAt', $started->toISOString())
            ->where('closeout.exportUntil', $until->toISOString())
            ->where('canStartCloseout', false)
            ->where('canExportData', true)
            ->where('canManageReminders', false));
    }

    public function test_closeout_blocks_delivery_and_company_writes_while_preserving_its_data(): void
    {
        $company = Organization::factory()->create(['reminders_enabled' => true]);
        $owner = $this->attach($company, 'owner');
        $member = $this->attach($company, 'member');
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
        $approval = $invoice->reminderApprovals()->create([
            'approved_by' => $owner->id,
            'fingerprint' => str_repeat('a', 64),
            'invoice_number' => '00042',
            'currency' => 'HUF',
            'outstanding_amount' => 80_000,
            'template_version' => 'v1',
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);
        $this->actingAs($owner)->post(route('organizations.closeout', $company), ['confirm' => '1'])->assertRedirect();

        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $delivery->id, 'status' => 'blocked', 'reason' => 'organization_closing',
        ]);

        foreach ([
            ['POST', 'customers.store', [$company]],
            ['POST', 'invoices.store', [$company]],
            ['PATCH', 'invoices.balance.update', [$company, $invoice]],
            ['DELETE', 'invoices.balance.destroy', [$company, $invoice]],
            ['PATCH', 'invoices.deadline.update', [$company, $invoice]],
            ['PATCH', 'invoices.dispute.update', [$company, $invoice]],
            ['POST', 'invoice-imports.store', [$company]],
            ['DELETE', 'invoice-imports.destroy', [$company, (string) Str::uuid()]],
            ['POST', 'invoice-imports.confirm', [$company, (string) Str::uuid()]],
            ['PATCH', 'customers.billing-contact.update', [$company, $customer]],
            ['POST', 'customers.billing-contact.confirm', [$company, $customer]],
            ['PATCH', 'customers.contact-restriction', [$company, $customer]],
            ['PATCH', 'reminder-delivery-settings', [$company]],
            ['POST', 'invoices.reminder-approval', [$company, $invoice]],
            ['POST', 'invoices.reminder-test-delivery', [$company, $invoice]],
        ] as [$method, $route, $parameters]) {
            $this->call($method, route('organizations.'.$route, $parameters))->assertConflict();
        }

        $this->assertModelExists($customer);
        $this->assertModelExists($invoice);
        $this->get(route('organizations.exports.invoices', $company))->assertOk()->assertDownload();

        $this->actingAs($member)->get(route('organizations.invoices.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organizationReadOnly', true)
                ->where('invoices.data.0.number', '00042'));
        $this->get(route('organizations.exports.invoices', $company))->assertForbidden();

        $other = Organization::factory()->create();
        $this->attach($other, 'owner', $owner);
        $this->actingAs($owner)->post(route('organizations.customers.store', $other), ['display_name' => 'Másik ügyfél'])
            ->assertSessionHasNoErrors();
        $this->get(route('organizations.invoices.index', $other))
            ->assertInertia(fn (Assert $page) => $page->where('organizationReadOnly', false));
    }

    public function test_export_and_operational_reads_end_at_the_deadline_but_company_status_remains_visible(): void
    {
        $this->freezeSecond();
        $company = Organization::factory()->create();
        $owner = $this->attach($company, 'owner');
        $this->actingAs($owner)->post(route('organizations.closeout', $company), ['confirm' => true])->assertRedirect();

        $until = $company->refresh()->export_until;
        $this->travelTo($until->copy()->subSecond());
        $this->get(route('organizations.exports.customers', $company))->assertOk();
        $this->travelTo($until);
        $this->get(route('organizations.exports.customers', $company))->assertGone();
        $this->get(route('organizations.exports.invoices', $company))->assertGone();
        $this->get(route('organizations.invoices.index', $company))->assertGone();
        $this->get(route('organizations.reminder-deliveries.index', $company))->assertGone();
        $this->get(route('organizations.show', $company))->assertInertia(fn (Assert $page) => $page
            ->where('closeout.exportAvailable', false)
            ->where('canExportData', false));
        $this->assertModelExists($company);
    }

    private function attach(Organization $company, string $role, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $user->organizations()->attach($company, ['status' => 'active', 'role' => $role]);

        return $user;
    }
}
