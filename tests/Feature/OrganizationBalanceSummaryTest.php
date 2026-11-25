<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationBalanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_checked_and_disputed_balances_and_ignores_other_companies(): void
    {
        $company = Organization::factory()->create();
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
                'outstanding' => '0', 'disputed' => '0', 'unchecked' => 0,
            ]));

        $this->invoices($company, [['status' => 'open', 'outstanding_amount' => 120_000, 'status_checked_at' => now()]]);
        $this->post(route('organizations.closeout', $company), ['confirm' => true])->assertRedirect($url);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('balanceSummary.outstanding', '120000'));
        $this->travel(30)->days();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('balanceSummary', null));
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
