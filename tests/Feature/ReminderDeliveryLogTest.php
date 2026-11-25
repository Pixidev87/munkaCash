<?php

namespace Tests\Feature;

use App\Enums\ReminderTestDeliveryStatus;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderTestDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReminderDeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_members_see_only_their_company_delivery_log_with_minimal_details(): void
    {
        $company = Organization::factory()->create();
        $otherCompany = Organization::factory()->create();
        $member = $this->memberOf($company);
        $invoice = $this->invoice($company, '00042');
        $this->freezeTime();
        $local = $this->delivery($invoice, ReminderTestDeliveryStatus::Unknown)->refresh();
        $this->delivery($this->invoice($otherCompany, '00042'), ReminderTestDeliveryStatus::Accepted);

        $this->actingAs($member)->get(route('organizations.reminder-deliveries.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/reminder-delivery-log')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0', [
                    'id' => $local->id,
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => '00042',
                    'status' => 'unknown',
                    'reason' => 'transport_unconfirmed',
                    'createdAt' => $local->created_at->toISOString(),
                    'updatedAt' => $local->updated_at->toISOString(),
                ]));
    }

    public function test_reason_status_and_exact_invoice_number_filter_together_and_are_validated(): void
    {
        $company = Organization::factory()->create();
        $invoice = $this->invoice($company, '00042');
        $match = $this->delivery($invoice, ReminderTestDeliveryStatus::Blocked, 'approval_revoked');
        $this->delivery($invoice, ReminderTestDeliveryStatus::Blocked, 'balance_rechecked');
        $this->delivery($this->invoice(Organization::factory()->create(), '00042'), ReminderTestDeliveryStatus::Blocked, 'approval_revoked');
        $this->delivery($invoice, ReminderTestDeliveryStatus::Accepted);
        $this->delivery($this->invoice($company, '42'), ReminderTestDeliveryStatus::Blocked, 'approval_revoked');
        $this->actingAs($this->memberOf($company));

        $this->get(route('organizations.reminder-deliveries.index', [
            'organization' => $company,
            'status' => 'blocked',
            'reason' => 'approval_revoked',
            'invoice_number' => '00042',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'blocked')
            ->where('filters.reason', 'approval_revoked')
            ->where('filters.invoiceNumber', '00042')
            ->has('deliveries.data', 1)
            ->where('deliveries.data.0.id', $match->id));

        $this->getJson(route('organizations.reminder-deliveries.index', [
            'organization' => $company,
            'status' => 'sent',
            'reason' => 'invalid reason',
            'invoice_number' => str_repeat('A', 101),
            'page' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['status', 'invoice_number', 'reason', 'page']);
    }

    public function test_pagination_preserves_filters_and_sorts_newest_first(): void
    {
        $company = Organization::factory()->create();
        $invoice = $this->invoice($company, '00042');
        $this->freezeTime();
        for ($index = 0; $index < 20; $index++) {
            $this->delivery($invoice, ReminderTestDeliveryStatus::Blocked, 'approval_revoked');
        }
        $this->travel(1)->minute();
        $newest = $this->delivery($invoice, ReminderTestDeliveryStatus::Blocked, 'approval_revoked');
        $baseQuery = ['organization' => $company, 'status' => 'blocked', 'reason' => 'approval_revoked', 'invoice_number' => '00042'];

        $this->actingAs($this->memberOf($company))->get(route('organizations.reminder-deliveries.index', $baseQuery))
            ->assertInertia(fn (Assert $page) => $page
                ->has('deliveries.data', 20)
                ->where('deliveries.data.0.id', $newest->id)
                ->where('deliveries.next_page_url', fn (string $url): bool => str_contains($url, 'status=blocked')
                    && str_contains($url, 'reason=approval_revoked')
                    && str_contains($url, 'invoice_number=00042')
                    && str_contains($url, 'page=2')));

        $this->get(route('organizations.reminder-deliveries.index', [...$baseQuery, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('deliveries.data', 1));
    }

    public function test_guests_and_users_without_an_active_membership_cannot_read_the_log(): void
    {
        $company = Organization::factory()->create();
        $owner = $this->memberOf($company);
        $url = route('organizations.reminder-deliveries.index', $company);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $owner->organizations()->updateExistingPivot($company->id, ['status' => 'inactive']);
        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    private function memberOf(Organization $company): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);

        return $user;
    }

    private function invoice(Organization $company, string $number): Invoice
    {
        $customer = $company->customers()->create(['display_name' => 'Minta Kft.']);

        return $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
    }

    private function delivery(Invoice $invoice, ReminderTestDeliveryStatus $status, ?string $reason = null): ReminderTestDelivery
    {
        $approval = $invoice->reminderApprovals()->create([
            'fingerprint' => str_repeat('a', 64),
            'template_version' => 'v1',
            'invoice_number' => $invoice->invoice_number,
            'outstanding_amount' => 80_000,
            'currency' => 'HUF',
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        return $approval->testDelivery()->create([
            'status' => $status,
            'reason' => $reason ?? ($status === ReminderTestDeliveryStatus::Unknown ? 'transport_unconfirmed' : null),
        ]);
    }
}
