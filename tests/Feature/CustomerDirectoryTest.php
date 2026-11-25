<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_exposes_only_company_customers_and_contact_status(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active']);

        $missing = $company->customers()->create(['display_name' => 'Alfa']);
        $pending = $company->customers()->create(['display_name' => 'Beta']);
        $pending->forceFill(['billing_email' => 'pending@example.test'])->save();
        $confirmed = $company->customers()->create(['display_name' => 'Gamma']);
        $confirmed->forceFill([
            'billing_email' => 'confirmed@example.test',
            'billing_email_confirmed_at' => now(),
            'do_not_contact' => true,
        ])->save();
        $other->customers()->create(['display_name' => 'Külső ügyfél']);

        $url = route('organizations.customers.index', $company);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($member)->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('organizations/customers')
            ->where('customers.data', [
                ['id' => (string) $missing->id, 'name' => 'Alfa', 'billingContact' => 'missing', 'doNotContact' => false],
                ['id' => (string) $pending->id, 'name' => 'Beta', 'billingContact' => 'unconfirmed', 'doNotContact' => false],
                ['id' => (string) $confirmed->id, 'name' => 'Gamma', 'billingContact' => 'confirmed', 'doNotContact' => true],
            ]));
        $this->get($url.'?billing_contact=confirmed&blocked=1&name=Gam')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.billingContact', 'confirmed')
                ->where('filters.blocked', true)
                ->has('customers.data', 1)
                ->where('customers.data.0.id', (string) $confirmed->id));
        $this->get($url.'?billing_contact=unconfirmed')
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.id', (string) $pending->id));
        $this->from($url)->get($url.'?billing_contact=invalid')
            ->assertRedirect($url)->assertSessionHasErrorsIn('customerFilters', 'billing_contact');
        $this->get(route('organizations.customers.index', $other))->assertNotFound();
    }

    public function test_name_search_is_literal_and_company_scoped(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach([$company->id, $other->id], ['status' => 'active']);
        $company->customers()->create(['display_name' => 'Árvíz 10%_Minta']);
        $company->customers()->create(['display_name' => 'Árvíz 100AMinta']);
        $other->customers()->create(['display_name' => 'Külső 10%_Minta']);

        $list = route('organizations.customers.index', $company);
        $this->actingAs($member)->get($list.'?'.http_build_query(['name' => '10%_']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.name', '10%_')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Árvíz 10%_Minta'));
        $this->from($list)->get($list.'?name='.str_repeat('a', 256))
            ->assertRedirect($list)->assertSessionHasErrorsIn('customerFilters', 'name');
    }

    public function test_directory_remains_readable_only_until_the_closeout_deadline(): void
    {
        $this->freezeSecond();
        $company = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);

        $url = route('organizations.customers.index', $company);
        $this->actingAs($owner)->get($url)
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
        $this->post(route('organizations.closeout', $company), ['confirm' => true])->assertRedirect();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('organizationReadOnly', true));
        $this->travel(30)->days();
        $this->get($url)->assertGone();
    }
}
