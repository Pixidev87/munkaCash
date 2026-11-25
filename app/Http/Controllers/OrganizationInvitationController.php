<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationAccessAction;
use App\Http\Requests\Organizations\OrganizationInvitationEmailRequest;
use App\Models\Organization;
use App\Models\OrganizationAccessEvent;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrganizationInvitationController extends Controller
{
    public function store(OrganizationInvitationEmailRequest $request, string $organization): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        $token = bin2hex(random_bytes(32));
        $now = now();
        OrganizationInvitation::query()->updateOrCreate([
            'organization_id' => $company->id,
            'email_digest' => OrganizationInvitation::digestEmail($request->email()),
        ], [
            'token_digest' => hash('sha256', $token),
            'issued_at' => $now,
            'expires_at' => $now->copy()->addDay(),
        ]);

        OrganizationAccessEvent::record($company, $request->user(), OrganizationAccessAction::InvitationIssued);

        Inertia::flash('invitationPath', route('organization-invitations.show', $token, absolute: false));

        return to_route('organizations.members.index', $company);
    }

    public function destroy(OrganizationInvitationEmailRequest $request, string $organization): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        $cancelled = OrganizationInvitation::query()
            ->where('organization_id', $company->id)
            ->where('email_digest', OrganizationInvitation::digestEmail($request->email()))
            ->where('expires_at', '>', now())
            ->delete();

        if ($cancelled > 0) {
            OrganizationAccessEvent::record($company, $request->user(), OrganizationAccessAction::InvitationCancelled);
        }

        Inertia::flash('invitationCancellation', $cancelled > 0 ? 'cancelled' : 'not_found');

        return to_route('organizations.members.index', $company);
    }

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->activeInvitation($token, $request->user()->email)->firstOrFail();
        $company = Organization::query()->findOrFail($invitation->organization_id);
        abort_if($company->isClosing(), 404);

        $response = Inertia::render('organizations/accept-invitation', [
            'companyName' => $company->name,
            'token' => $token,
        ])->toResponse($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        $invitation = $this->activeInvitation($token, $user->email)->firstOrFail();

        DB::transaction(function () use ($user, $invitation, $token): void {
            $company = Organization::query()->lockForUpdate()->findOrFail($invitation->organization_id);
            abort_if($company->isClosing(), 404);
            $recipient = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($recipient->hasVerifiedEmail(), 404);
            $current = $this->activeInvitation($token, $recipient->email)->lockForUpdate()->firstOrFail();

            $membership = DB::table('organization_user')
                ->where('organization_id', $company->id)
                ->where('user_id', $recipient->id)
                ->lockForUpdate()
                ->first(['role', 'status', 'updated_at']);

            if ($membership?->role === 'owner') {
                abort_unless($membership->status === 'active', 404);
            } else {
                abort_if(
                    $membership?->status === 'inactive'
                    && ($membership->updated_at === null || $current->issued_at->lte($membership->updated_at)),
                    404,
                );
                $company->users()->syncWithoutDetaching([
                    $recipient->id => ['role' => 'member', 'status' => 'active'],
                ]);
            }
            $current->deleteOrFail();
            OrganizationAccessEvent::record($company, $recipient, OrganizationAccessAction::InvitationAccepted);
        }, attempts: 3);

        return to_route('organizations.show', $invitation->organization_id);
    }

    /** @return Builder<OrganizationInvitation> */
    private function activeInvitation(string $token, string $email): Builder
    {
        return OrganizationInvitation::query()
            ->where('token_digest', hash('sha256', $token))
            ->where('email_digest', OrganizationInvitation::digestEmail($email))
            ->where('expires_at', '>', now());
    }
}
