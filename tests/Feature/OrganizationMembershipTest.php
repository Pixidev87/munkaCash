<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_hold_different_roles_in_different_organizations(): void
    {
        $user = User::factory()->create();
        $colleague = User::factory()->create();
        $first = Organization::factory()->create(['name' => 'Északi Minta Kft.']);
        $second = Organization::factory()->create(['name' => 'Déli Minta Kft.']);

        $user->organizations()->attach($first->id, ['role' => 'owner', 'status' => 'active']);
        $user->organizations()->attach($second->id, ['role' => 'member', 'status' => 'active']);
        $colleague->organizations()->attach($first->id, ['status' => 'active']);

        $this->assertSame('owner', $user->organizations()->findOrFail($first->id)->pivot->role);
        $this->assertSame('member', $user->organizations()->findOrFail($second->id)->pivot->role);
        $this->assertEqualsCanonicalizing(
            [$user->id, $colleague->id],
            $first->users()->pluck('users.id')->all(),
        );
    }

    public function test_active_organizations_exclude_nonmembers_and_inactive_memberships(): void
    {
        $user = User::factory()->create();
        $active = Organization::factory()->create();
        $pending = Organization::factory()->create();
        $inactive = Organization::factory()->create();
        Organization::factory()->create();

        $user->organizations()->attach($active->id, ['status' => 'active']);
        $user->organizations()->attach($pending->id);
        $user->organizations()->attach($inactive->id, ['status' => 'inactive']);

        $this->assertSame('pending', $user->organizations()->findOrFail($pending->id)->pivot->status);
        $this->assertSame([$active->id], $user->activeOrganizations()->pluck('organizations.id')->all());

        $user->organizations()->updateExistingPivot($active->id, ['status' => 'inactive']);

        $this->assertFalse($user->activeOrganizations()->exists());
    }

    public function test_the_same_user_cannot_join_an_organization_twice(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $user->organizations()->attach($organization->id);

        $this->expectException(UniqueConstraintViolationException::class);

        $user->organizations()->attach($organization->id);
    }

    public function test_deleting_an_organization_removes_its_memberships_and_preserves_users(): void
    {
        $user = User::factory()->create();
        $removed = Organization::factory()->create();
        $retained = Organization::factory()->create();
        $user->organizations()->attach([$removed->id, $retained->id]);

        $removed->delete();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('organization_user', ['organization_id' => $removed->id]);
        $this->assertSame([$retained->id], $user->organizations()->pluck('organizations.id')->all());
    }
}
