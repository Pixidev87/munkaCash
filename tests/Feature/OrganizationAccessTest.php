<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_only_the_users_active_organizations(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create(['name' => 'Alfa Minta Kft.']);
        $second = Organization::factory()->create(['name' => 'Delta Minta Kft.']);
        $pending = Organization::factory()->create();
        $inactive = Organization::factory()->create();
        Organization::factory()->create();

        $user->organizations()->attach([$second->id, $first->id], ['status' => 'active']);
        $user->organizations()->attach($pending->id);
        $user->organizations()->attach($inactive->id, ['status' => 'inactive']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('organizations', [
                    ['id' => (string) $first->id, 'name' => $first->name],
                    ['id' => (string) $second->id, 'name' => $second->name],
                ]));
    }

    public function test_dashboard_is_empty_without_active_memberships(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $user->organizations()->attach($organization->id);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('organizations', 0));
    }

    public function test_an_active_member_can_open_an_organization_until_access_is_revoked(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $user->organizations()->attach($organization->id, ['status' => 'active']);
        $url = route('organizations.show', $organization->id);

        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/show')
                ->where('name', $organization->name));

        $user->organizations()->updateExistingPivot($organization->id, ['status' => 'inactive']);

        $this->get($url)->assertNotFound();
    }

    public function test_pending_members_and_other_organizations_members_cannot_open_the_page(): void
    {
        $user = User::factory()->create();
        $own = Organization::factory()->create();
        $pending = Organization::factory()->create();
        $other = Organization::factory()->create();

        $user->organizations()->attach($own->id, ['status' => 'active']);
        $user->organizations()->attach($pending->id);

        $this->actingAs($user)->get(route('organizations.show', $pending->id))->assertNotFound();
        $this->get(route('organizations.show', $other->id))->assertNotFound();
    }

    public function test_the_organization_page_requires_login_and_verified_email(): void
    {
        $user = User::factory()->unverified()->create();
        $organization = Organization::factory()->create();
        $user->organizations()->attach($organization->id, ['status' => 'active']);
        $url = route('organizations.show', $organization->id);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($user)->get($url)->assertRedirect(route('verification.notice'));
    }
}
