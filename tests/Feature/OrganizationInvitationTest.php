<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_verified_invitee_can_use_a_link_once(): void
    {
        Notification::fake();
        [$company, $owner] = $this->companyWithOwner();
        $other = Organization::factory()->create();
        $recipient = User::factory()->unverified()->create();
        $outsider = User::factory()->create();

        $path = $this->issue($company, $owner, strtoupper($recipient->email));
        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $company->id,
            'email_digest' => OrganizationInvitation::digestEmail($recipient->email),
            'token_digest' => hash('sha256', basename($path)),
        ]);

        $this->actingAs($outsider)->get($path)->assertNotFound();
        $this->post($path)->assertNotFound();
        $this->actingAs($recipient)->get($path)->assertRedirect(route('verification.notice'));
        $this->post($path)->assertRedirect(route('verification.notice'));
        $this->assertFalse($recipient->organizations()->exists());

        $recipient->markEmailAsVerified();
        $preview = $this->actingAs($recipient)->get($path)->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->where('companyName', $company->name));
        $this->assertTrue($preview->headers->hasCacheControlDirective('no-store'));
        $this->assertFalse($recipient->organizations()->exists());

        $this->post($path)->assertRedirect(route('organizations.show', $company));
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $company->id, 'user_id' => $recipient->id, 'role' => 'member', 'status' => 'active',
        ]);
        $this->get(route('organizations.invoices.index', $company))->assertOk();
        $this->get(route('organizations.invoices.index', $other))->assertNotFound();
        $this->post($path)->assertNotFound();
        $this->assertDatabaseCount('organization_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_reissue_expiration_and_closeout_invalidate_links(): void
    {
        $this->freezeSecond();
        [$company, $owner] = $this->companyWithOwner();
        $recipient = User::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);
        $store = route('organizations.members.invitations.store', $company);

        $this->actingAs($member)->post($store, ['email' => $recipient->email])->assertForbidden();
        $this->actingAs($owner)->post($store, ['email' => 'invalid'])->assertSessionHasErrors('email');
        $old = $this->issue($company, $owner, $recipient->email);
        $new = $this->issue($company, $owner, $recipient->email);
        $this->assertNotSame($old, $new);
        $this->assertDatabaseCount('organization_invitations', 1);
        $this->actingAs($recipient)->post($old)->assertNotFound();

        $this->travel(24)->hours();
        $this->post($new)->assertNotFound();
        $this->artisan('model:prune', ['--model' => [OrganizationInvitation::class]])->assertSuccessful();
        $this->assertDatabaseCount('organization_invitations', 0);

        $fresh = $this->issue($company, $owner, $recipient->email);
        $this->post(route('organizations.closeout', $company), ['confirm' => true])->assertRedirect();
        $this->post($store, ['email' => $recipient->email])->assertConflict();
        $this->actingAs($recipient)->get($fresh)->assertNotFound();
        $this->post($fresh)->assertNotFound();
        $this->assertFalse($recipient->organizations()->exists());
    }

    public function test_revocation_cancels_previous_links_and_only_a_fresh_link_restores_membership(): void
    {
        $this->freezeSecond();
        [$company, $owner] = $this->companyWithOwner();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);
        $old = $this->issue($company, $owner, $member->email);
        $alias = $this->issue($company, $owner, 'previous@example.test');

        $this->travel(1)->second();
        $this->delete(route('organizations.members.destroy', [$company, $member]), ['confirm' => true])->assertRedirect();
        $this->assertDatabaseMissing('organization_invitations', ['token_digest' => hash('sha256', basename($old))]);
        $this->actingAs($member)->post($old)->assertNotFound();
        $currentEmail = $member->email;
        $member->forceFill(['email' => 'previous@example.test'])->save();
        $this->actingAs($member)->post($alias)->assertNotFound();
        $this->assertFalse($member->activeOrganizations()->exists());

        $member->forceFill(['email' => $currentEmail])->save();
        $this->travel(1)->second();
        $fresh = $this->issue($company, $owner, $member->email);
        $this->actingAs($member)->post($fresh)->assertRedirect(route('organizations.show', $company));
        $this->assertTrue($member->activeOrganizations()->exists());

        $ownerLink = $this->issue($company, $owner, $owner->email);
        $this->post($ownerLink)->assertRedirect(route('organizations.show', $company));
        $this->assertDatabaseHas('organization_user', ['organization_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner']);
    }

    /** @return array{Organization, User} */
    private function companyWithOwner(): array
    {
        $company = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);

        return [$company, $owner];
    }

    private function issue(Organization $company, User $owner, string $email): string
    {
        $this->actingAs($owner)->post(route('organizations.members.invitations.store', $company), ['email' => $email])
            ->assertRedirect(route('organizations.members.index', $company))
            ->assertInertiaFlash('invitationPath');

        return session()->get(SessionKey::FLASH_DATA)['invitationPath'];
    }
}
