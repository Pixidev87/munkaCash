<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationBalanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_checked_and_disputed_balances_and_ignores_other_companies(): void
    {
        $company = Organization::factory()->create();
        $this->travelTo(Carbon::parse('2026-12-02 10:00:00', 'Europe/Budapest'));
        $other = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);

        $this->invoices($company, [
            ['status' => 'partially_paid', 'outstanding_amount' => 80_000, 'status_checked_at' => now()],
            ['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()],
            ['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now(), 'is_disputed' => true],
            ['status' => 'paid', 'outstanding_amount' => 0, 'status_checked_at' => now()],
            [],
            ['is_disputed' => true],
        ]);
        $this->invoices($other, [
            ['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()],
            [],
        ]);

        $this->actingAs($member)->get(route('organizations.show', $company))
            ->assertInertia(fn (Assert $page) => $page->where('balanceSummary', [
                'outstanding' => '200000',
                'overdue' => '200000',
                'overdueWithoutConfirmedAddress' => 2,
                'disputed' => '120000',
                'unchecked' => 2,
            ]));
        $this->get(route('organizations.show', $other))->assertNotFound();
    }

    public function test_an_empty_company_has_zero_balances_and_closeout_expiry_hides_the_summary(): void
    {
        $this->freezeSecond();
        $company = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $url = route('organizations.show', $company);

        $this->actingAs($owner)->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('balanceSummary', [
                'outstanding' => '0', 'overdue' => '0', 'overdueWithoutConfirmedAddress' => 0, 'disputed' => '0', 'unchecked' => 0,
            ]));

        $this->invoices($company, [['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()]]);
        $this->post(route('organizations.closeout', $company), ['confirm' => true])->assertRedirect($url);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('balanceSummary.outstanding', '120000'));
        $this->travel(30)->days();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('balanceSummary', null));
    }

    public function test_overdue_summary_and_filtered_list_share_the_holiday_cutoff(): void
    {
        $this->travelTo(Carbon::parse('2026-12-28 22:59:59', 'UTC'));
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);
        $this->invoices($company, [
            ['due_date' => '2026-12-24', 'status' => 'partially_paid', 'outstanding_amount' => 80_000, 'status_checked_at' => now()],
            ['due_date' => '2026-12-23', 'status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now(), 'is_disputed' => true],
            ['due_date' => '2026-12-23', 'status' => 'paid', 'outstanding_amount' => 0, 'status_checked_at' => now()],
            ['due_date' => '2026-12-23'],
            ['due_date' => '2025-12-18', 'status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()],
        ]);
        $show = route('organizations.show', $company);
        $list = route('organizations.invoices.index', [$company, 'overdue' => 1]);

        $this->actingAs($member)->get($show)
            ->assertInertia(fn (Assert $page) => $page->where('balanceSummary.overdue', '0'));
        $this->get($list)->assertInertia(fn (Assert $page) => $page
            ->where('filters.overdue', true)
            ->has('invoices.data', 0));

        $this->travel(1)->second();
        $this->get($show)->assertInertia(fn (Assert $page) => $page->where('balanceSummary.overdue', '80000'));
        $this->get($list)->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'DEMO-0'));
    }

    public function test_balance_review_filter_matches_the_company_summary_and_can_combine_with_other_filters(): void
    {
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $this->invoices(Organization::factory()->create(), [[]]);
        $this->invoices($company, [
            [],
            ['status' => 'open', 'outstanding_amount' => 120_000],
            ['status_checked_at' => now(), 'is_disputed' => true],
            ['status' => 'paid', 'outstanding_amount' => 0, 'status_checked_at' => now()],
        ]);
        $params = ['organization' => $company, 'awaiting_balance' => 1];

        $this->actingAs($member)->get(route('organizations.show', $company))
            ->assertInertia(fn (Assert $page) => $page->where('balanceSummary.unchecked', 3));
        $this->get(route('organizations.invoices.index', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.awaitingBalance', true)
                ->has('invoices.data', 3)
                ->where('invoices.data.0.number', 'DEMO-2')
                ->where('invoices.data.2.number', 'DEMO-0'));
        $this->get(route('organizations.invoices.index', [...$params, 'disputed' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.number', 'DEMO-2'));
        $this->getJson(route('organizations.invoices.index', [...$params, 'awaiting_balance' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('awaiting_balance');
    }

    public function test_billing_address_filter_includes_missing_and_unconfirmed_addresses_and_combines_with_overdue(): void
    {
        $this->travelTo(Carbon::parse('2026-12-02 10:00:00', 'Europe/Budapest'));
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $checked = ['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()];
        $this->invoices($other, [$checked]);
        $this->invoices($company, [$checked, [...$checked, 'due_date' => '2026-12-03'], $checked, $checked]);
        $unconfirmed = $company->customers()->create(['display_name' => 'Megerősítésre váró ügyfél']);
        $unconfirmed->forceFill(['billing_email' => 'waiting@example.test'])->save();
        $confirmed = $company->customers()->create(['display_name' => 'Megerősített ügyfél']);
        $confirmed->forceFill(['billing_email' => 'confirmed@example.test', 'billing_email_confirmed_at' => now()])->save();
        $company->invoices()->where('invoice_number', 'DEMO-2')->update(['customer_id' => $unconfirmed->id]);
        $company->invoices()->where('invoice_number', 'DEMO-3')->update(['customer_id' => $confirmed->id]);

        $params = ['organization' => $company, 'billing_unconfirmed' => 1];
        $this->actingAs($member)->get(route('organizations.invoices.index', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.billingUnconfirmed', true)
                ->has('invoices.data', 3));
        $this->get(route('organizations.show', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('balanceSummary.overdue', '360000')
                ->where('balanceSummary.overdueWithoutConfirmedAddress', 2));
        $this->get(route('organizations.invoices.index', [...$params, 'overdue' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 2)
                ->where('invoices.data.0.number', 'DEMO-2')
                ->where('invoices.data.1.number', 'DEMO-0'));
        $unconfirmed->forceFill(['billing_email_confirmed_at' => now()])->save();
        $this->get(route('organizations.show', $company))
            ->assertInertia(fn (Assert $page) => $page->where('balanceSummary.overdueWithoutConfirmedAddress', 1));
        $this->getJson(route('organizations.invoices.index', [...$params, 'billing_unconfirmed' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('billing_unconfirmed');
    }

    public function test_invoice_list_totals_cover_all_filtered_pages_only_for_the_selected_company(): void
    {
        $company = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);
        $open = ['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()];
        $this->invoices($company, [
            ...array_fill(0, 26, $open),
            [...$open, 'is_disputed' => true],
            ['status' => 'open'],
            ['status' => 'paid', 'outstanding_amount' => 0, 'status_checked_at' => now()],
        ]);
        $this->invoices(Organization::factory()->create(), [$open]);

        $params = ['organization' => $company, 'status' => 'open'];
        $this->actingAs($member)->get(route('organizations.invoices.index', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 25)
                ->where('totals', ['count' => 28, 'unchecked' => 1, 'outstanding' => '3120000']));
        $this->get(route('organizations.invoices.index', [...$params, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 3)
                ->where('totals.outstanding', '3120000'));
    }

    private function invoices(Organization $company, array $rows): void
    {
        $customer = $company->customers()->create(['display_name' => 'Minta ügyfél']);
        foreach ($rows as $index => $attributes) {
            $company->invoices()->create([
                'customer_id' => $customer->id,
                'invoice_number' => 'DEMO-'.$index,
                'due_date' => '2026-12-01',
                'gross_amount' => 120_000,
            ])->forceFill($attributes)->save();
        }
    }
}
