<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceBalanceEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_member_can_register_a_customer_and_an_unchecked_invoice(): void
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);

        $this->actingAs($user)->post(route('organizations.customers.store', $company), [
            'display_name' => 'Minta Ügyfél Kft.',
        ])->assertRedirect();
        $customer = $company->customers()->sole();

        $this->post(route('organizations.invoices.store', $company), [
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ])->assertRedirect(route('organizations.invoices.index', $company));

        $this->assertDatabaseHas('invoices', [
            'organization_id' => $company->id,
            'invoice_number' => '00042',
            'gross_amount' => 120_000,
            'currency' => 'HUF',
            'status' => 'unchecked',
            'outstanding_amount' => null,
            'checked_by' => null,
        ]);
        $this->get(route('organizations.invoices.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/invoices')
                ->where('invoices.data.0.number', '00042')
                ->where('invoices.data.0.dueDate', '2026-12-01')
                ->where('invoices.data.0.outstanding', null)
                ->where('customers.0.name', 'Minta Ügyfél Kft.'));
    }

    public function test_manual_invoice_validation_keeps_errors_in_the_invoice_form(): void
    {
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);

        $this->actingAs($member)->post(route('organizations.invoices.store', $company), [
            'customer_id' => [$customer->id],
            'invoice_number' => str_repeat('A', 101),
            'due_date' => '2026-02-30',
            'gross_amount' => Invoice::MAX_GROSS_AMOUNT + 1,
        ])->assertSessionHasErrorsIn('invoice', ['customer_id', 'invoice_number', 'due_date', 'gross_amount']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_review_distinguishes_open_partial_and_paid_balances_and_records_the_checker(): void
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);
        $invoice = $this->createInvoice($company);
        $url = route('organizations.invoices.balance.update', [$company, $invoice]);

        $this->actingAs($user)->freezeTime();

        foreach ([120_000 => 'open', 80_000 => 'partially_paid', 0 => 'paid'] as $amount => $status) {
            $revision = $invoice->refresh()->revision;

            $this->patch($url, [
                'outstanding_amount' => (string) $amount,
                'revision' => $revision,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('invoices', [
                'id' => $invoice->id,
                'gross_amount' => 120_000,
                'outstanding_amount' => $amount,
                'status' => $status,
                'status_source' => 'manual',
                'status_checked_at' => now()->toDateTimeString(),
                'checked_by' => $user->id,
                'revision' => $revision + 1,
            ]);
        }
    }

    public function test_invalid_and_stale_balance_updates_do_not_overwrite_the_latest_review(): void
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);
        $invoice = $this->createInvoice($company);
        $url = route('organizations.invoices.balance.update', [$company, $invoice]);
        $this->actingAs($user);

        foreach (['', -1, 120_001, 80.5] as $amount) {
            $this->patch($url, ['outstanding_amount' => $amount, 'revision' => 0])
                ->assertSessionHasErrorsIn('balance-'.$invoice->id, 'outstanding_amount');
        }

        $this->patch($url, ['outstanding_amount' => 80_000, 'revision' => -1])
            ->assertSessionHasErrorsIn('balance-'.$invoice->id, 'revision');

        $this->patch($url, ['outstanding_amount' => 80_000, 'revision' => 0])
            ->assertSessionHasNoErrors();
        $this->patch($url, ['outstanding_amount' => 20_000, 'revision' => 0])
            ->assertSessionHasErrorsIn('balance-'.$invoice->id, 'outstanding_amount');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'outstanding_amount' => 80_000,
            'revision' => 1,
        ]);
    }

    public function test_invoices_and_customers_are_scoped_to_the_selected_active_organization(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);
        $user->organizations()->attach($other->id, ['status' => 'pending']);
        $foreignInvoice = $this->createInvoice($other);
        $payload = [
            'customer_id' => $foreignInvoice->customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ];

        $this->actingAs($user)->get(route('organizations.invoices.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 0)
                ->has('customers', 0));
        $this->get(route('organizations.invoices.index', $other))->assertNotFound();
        $this->post(route('organizations.customers.store', $other), ['display_name' => 'Tiltott'])
            ->assertNotFound();
        $this->post(route('organizations.invoices.store', $other), $payload)->assertNotFound();
        $this->post(route('organizations.invoices.store', $company), $payload)
            ->assertSessionHasErrorsIn('invoice', 'customer_id');
        $this->patch(route('organizations.invoices.balance.update', [$company, $foreignInvoice]), [
            'outstanding_amount' => 0,
            'revision' => 0,
        ])->assertNotFound();

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('invoices', ['id' => $foreignInvoice->id, 'outstanding_amount' => null]);
    }

    public function test_duplicate_numbers_are_rejected_within_a_company_but_allowed_across_companies(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach([$first->id, $second->id], ['status' => 'active']);
        $firstInvoice = $this->createInvoice($first);
        $secondCustomer = $second->customers()->create(['display_name' => 'Másik ügyfél']);
        $payload = [
            'customer_id' => $firstInvoice->customer->id,
            'invoice_number' => $firstInvoice->invoice_number,
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ];

        $this->actingAs($user)->post(route('organizations.invoices.store', $first), $payload)
            ->assertSessionHasErrorsIn('invoice', 'invoice_number');

        $payload['customer_id'] = $secondCustomer->id;
        $this->post(route('organizations.invoices.store', $second), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_the_database_rejects_a_customer_from_another_organization(): void
    {
        $company = Organization::factory()->create();
        $foreign = Organization::factory()->create()->customers()->create(['display_name' => 'Másik ügyfél']);

        $this->expectException(QueryException::class);

        $company->invoices()->create([
            'customer_id' => $foreign->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
    }

    public function test_resetting_a_paid_balance_requires_confirmation_current_revision_and_company_scope(): void
    {
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $invoice = $this->createInvoice($company);
        $other = $this->createInvoice(Organization::factory()->create());
        $invoice->forceFill(['outstanding_amount' => 0, 'status' => 'paid', 'revision' => 2, 'is_disputed' => true])->save();
        $reset = route('organizations.invoices.balance.destroy', [$company, $invoice]);
        $bag = 'reset-balance-'.$invoice->id;

        $this->actingAs($member)->delete($reset, ['revision' => 2])->assertSessionHasErrorsIn($bag, 'confirm');
        $this->delete($reset, ['confirm' => true, 'revision' => 1])->assertSessionHasErrorsIn($bag, 'revision');
        $this->delete(route('organizations.invoices.balance.destroy', [$company, $other]), ['confirm' => true, 'revision' => 0])->assertNotFound();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'outstanding_amount' => 0]);

        $this->delete($reset, ['confirm' => true, 'revision' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id, 'status' => 'unchecked', 'outstanding_amount' => null, 'revision' => 3, 'is_disputed' => true,
        ]);
    }

    public function test_invoice_filters_combine_within_the_company_and_use_the_complete_invoice_number(): void
    {
        $company = Organization::factory()->create();
        $otherCompany = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $matching = $this->createInvoice($company);
        $matching->forceFill(['status' => 'open', 'is_disputed' => true, 'invoice_number' => '00042%_'])->save();
        $foreign = $this->createInvoice($otherCompany);
        $foreign->forceFill(['status' => 'open', 'is_disputed' => true, 'invoice_number' => '00042%_'])->save();
        $company->invoices()->create([
            'customer_id' => $matching->customer_id,
            'invoice_number' => '000424A',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ])->forceFill(['status' => 'open', 'is_disputed' => true])->save();
        $route = route('organizations.invoices.index', $company);

        $filters = ['invoice_number' => '00042%_', 'status' => 'open', 'disputed' => '1'];
        $this->actingAs($member)->get($route.'?'.http_build_query($filters))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.id', (string) $matching->id)
                ->where('filters', ['invoiceNumber' => '00042%_', 'status' => 'open', 'source' => '', 'customerId' => '', 'disputed' => true, 'overdue' => false]));
        $this->get($route.'?status=paid&disputed=1')
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 0));

        $this->get(route('organizations.invoices.index', $otherCompany).'?'.http_build_query($filters))->assertNotFound();
        $this->from($route)->get($route.'?status=invalid&disputed=2&page=0')
            ->assertRedirect($route)
            ->assertSessionHasErrorsIn('invoiceFilters', ['status', 'disputed', 'page']);
    }

    public function test_customer_filter_combines_and_rejects_customers_from_other_companies(): void
    {
        $company = Organization::factory()->create();
        $foreignCompany = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach([$company->id, $foreignCompany->id], ['status' => 'active']);
        $selected = $this->createInvoice($company);
        $selected->forceFill(['is_disputed' => true])->save();
        $foreign = $this->createInvoice($foreignCompany);
        $second = $company->customers()->create(['display_name' => 'Másik ügyfél']);
        $company->invoices()->create([
            'customer_id' => $second->id,
            'invoice_number' => '00043',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ])->forceFill(['is_disputed' => true])->save();

        $list = route('organizations.invoices.index', $company);
        $this->actingAs($member)->get($list.'?disputed=1&customer_id='.$selected->customer_id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.number', '00042')
                ->where('invoices.data.0.customerId', (string) $selected->customer_id)
                ->where('filters.customerId', (string) $selected->customer_id)
                ->where('selectedCustomerId', ''));
        $this->get($list.'?customer='.$selected->customer_id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 2)
                ->where('filters.customerId', '')
                ->where('selectedCustomerId', (string) $selected->customer_id));

        foreach ([$foreign->customer_id, 'invalid'] as $customerId) {
            $this->from($list)->get($list.'?customer_id='.$customerId)
                ->assertRedirect($list)
                ->assertSessionHasErrorsIn('invoiceFilters', 'customer_id');
        }
    }

    public function test_invoice_pagination_preserves_applied_filters(): void
    {
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);
        foreach (range(1, 26) as $number) {
            $company->invoices()->create([
                'customer_id' => $customer->id,
                'invoice_number' => 'DEMO-'.$number,
                'due_date' => '2026-12-01',
                'gross_amount' => 10_000,
            ])->forceFill(['is_disputed' => true])->save();
        }
        $this->createInvoice($company);
        $route = route('organizations.invoices.index', $company);
        $firstPage = $this->actingAs($member)->get($route.'?status=unchecked&disputed=1&customer_id='.$customer->id)
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 25));

        $next = $firstPage->inertiaProps('invoices.next_page_url');
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame(['status' => 'unchecked', 'disputed' => '1', 'customer_id' => (string) $customer->id, 'page' => '2'], $query);
        $this->get($next)->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'DEMO-1'));
    }

    public function test_balance_history_records_checks_and_resets_only_for_the_selected_invoice(): void
    {
        $this->freezeSecond();
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $invoice = $this->createInvoice($company);
        $otherCompany = Organization::factory()->create();
        $otherInvoice = $this->createInvoice($otherCompany);
        $balance = route('organizations.invoices.balance.update', [$company, $invoice]);
        $reset = route('organizations.invoices.balance.destroy', [$company, $invoice]);
        $history = route('organizations.invoices.balance-history', [$company, $invoice]);

        $this->actingAs($member)->get($history)
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
        $this->patch($balance, ['outstanding_amount' => 80_000, 'revision' => 0])->assertSessionHasNoErrors();
        $this->patch($balance, ['outstanding_amount' => 0, 'revision' => 1])->assertSessionHasNoErrors();
        $this->delete($reset, ['confirm' => true, 'revision' => 1])->assertSessionHasErrorsIn('reset-balance-'.$invoice->id, 'revision');
        $this->delete($reset, ['confirm' => true, 'revision' => 2])->assertSessionHasNoErrors();
        $this->delete($reset, ['confirm' => true, 'revision' => 3])->assertSessionHasNoErrors();

        $this->get($history)->assertInertia(fn (Assert $page) => $page
            ->component('organizations/invoice-balance-history')
            ->where('number', '00042')
            ->has('events.data', 3)
            ->where('events.data.0.outstanding', null)
            ->where('events.data.0.actor', $member->name)
            ->where('events.data.0.occurredAt', now()->toISOString())
            ->where('events.data.1.outstanding', 0)
            ->where('events.data.2.outstanding', 80_000));
        $this->get(route('organizations.invoices.balance-history', [$company, $otherInvoice]))->assertNotFound();
        $this->get(route('organizations.invoices.balance-history', [$otherCompany, $otherInvoice]))->assertNotFound();
    }

    public function test_balance_history_expires_and_is_removed_when_the_company_is_deleted(): void
    {
        $this->freezeSecond();
        $company = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $invoice = $this->createInvoice($company);
        $invoice->balanceEvents()->create(['actor_id' => $owner->id, 'outstanding_amount' => 80_000, 'occurred_at' => now()->subDays(90)]);
        $invoice->balanceEvents()->create(['actor_id' => $owner->id, 'outstanding_amount' => 0, 'occurred_at' => now()]);

        $history = route('organizations.invoices.balance-history', [$company, $invoice]);
        $this->actingAs($owner)->get($history)
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.outstanding', 0));
        $this->artisan('model:prune', ['--model' => [InvoiceBalanceEvent::class]])->assertSuccessful();
        $this->assertDatabaseCount('invoice_balance_events', 1);

        $this->post(route('organizations.closeout', $company), ['confirm' => true])->assertRedirect();
        $this->get($history)->assertOk();
        $this->travel(30)->days();
        $this->get($history)->assertGone();
        $this->artisan('organizations:purge', ['organization' => $company->id, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('invoice_balance_events', 0);
    }

    private function createInvoice(Organization $company): Invoice
    {
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);

        return $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
    }
}
