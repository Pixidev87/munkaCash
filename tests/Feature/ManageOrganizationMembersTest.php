<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageOrganizationMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_revoke_only_the_target_company_membership(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $member->organizations()->attach([$company->id, $other->id], ['role' => 'member', 'status' => 'active']);

        $list = route('organizations.members.index', $company);
        $remove = route('organizations.members.destroy', [$company, $member]);
        $invoices = route('organizations.invoices.index', $company);

        $this->actingAs($member)->get($invoices)->assertOk();
        $this->get($list)->assertForbidden();
        $this->delete($remove)->assertForbidden();

        $this->actingAs($owner)->get(route('organizations.show', $company))
            ->assertInertia(fn (Assert $page) => $page->where('canManageMembers', true));
        $this->get($list)->assertInertia(fn (Assert $page) => $page
            ->component('organizations/members')
            ->has('members.data', 1)
            ->where('members.data.0', ['id' => (string) $member->id, 'name' => $member->name, 'email' => $member->email]));
        $this->delete($remove, ['confirm' => false])->assertSessionHasErrorsIn('member-'.$member->id, 'confirm');
        $this->assertDatabaseHas('organization_user', ['organization_id' => $company->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->delete($remove, ['confirm' => true])->assertRedirect($list);
        $this->delete($remove, ['confirm' => true])->assertRedirect($list);
        $this->get($list)->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $this->assertModelExists($member);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $company->id, 'user_id' => $member->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $other->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get($invoices)->assertNotFound();
        $this->post(route('organizations.customers.store', $company), ['display_name' => 'Nem menthető'])->assertNotFound();
        $this->get(route('organizations.show', $other))->assertInertia(fn (Assert $page) => $page->where('canManageMembers', false));
    }

    public function test_membership_management_excludes_other_owners_pending_members_and_closed_companies(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $owner = User::factory()->create();
        $anotherOwner = User::factory()->create();
        $foreign = User::factory()->create();
        $pending = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $anotherOwner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $foreign->organizations()->attach($other, ['role' => 'member', 'status' => 'active']);
        $pending->organizations()->attach($company, ['role' => 'member', 'status' => 'pending']);

        $list = route('organizations.members.index', $company);
        $this->get($list)->assertRedirect(route('login'));
        $this->actingAs($owner)->get($list)->assertInertia(fn (Assert $page) => $page->has('members.data', 0));

        foreach ([$anotherOwner, $owner, $foreign, $pending] as $target) {
            $this->delete(route('organizations.members.destroy', [$company, $target]), ['confirm' => true])
                ->assertNotFound();
        }

        $this->delete(route('organizations.members.destroy', [$other, $foreign]), ['confirm' => true])->assertNotFound();

        $company->forceFill(['closeout_started_at' => now(), 'export_until' => now()->addDays(30)])->save();
        $this->get($list)->assertForbidden();
        $this->delete(route('organizations.members.destroy', [$company, $pending]), ['confirm' => true])->assertConflict();

        $this->assertDatabaseHas('organization_user', ['organization_id' => $company->id, 'user_id' => $anotherOwner->id, 'role' => 'owner', 'status' => 'active']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $company->id, 'user_id' => $pending->id, 'status' => 'pending']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $other->id, 'user_id' => $foreign->id, 'status' => 'active']);
    }
}
