<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationAccessAction;
use App\Http\Requests\Organizations\RevokeMemberAccessRequest;
use App\Models\OrganizationAccessEvent;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('manageMembers', $company);

        $members = $company->users()
            ->wherePivot('role', 'member')
            ->wherePivot('status', 'active')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->simplePaginate(25, ['users.id', 'users.name', 'users.email']);

        return Inertia::render('organizations/members', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'members' => [
                'data' => array_map(static fn (User $member): array => [
                    'id' => (string) $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ], $members->items()),
                'next_page_url' => $members->nextPageUrl(),
                'prev_page_url' => $members->previousPageUrl(),
            ],
        ]);
    }

    public function destroy(RevokeMemberAccessRequest $request, string $organization, string $member): RedirectResponse
    {
        $company = $request->company();
        $members = $company->users()
            ->wherePivot('role', 'member')
            ->wherePivotIn('status', ['active', 'inactive']);
        $selected = $members->lockForUpdate()->findOrFail($member, ['users.id', 'users.email']);

        $revoked = $members->wherePivot('status', 'active')->updateExistingPivot($selected->id, ['status' => 'inactive']);
        if ($revoked > 0) {
            OrganizationInvitation::query()
                ->where('organization_id', $company->id)
                ->where('email_digest', OrganizationInvitation::digestEmail($selected->email))
                ->delete();
            OrganizationAccessEvent::record($company, $request->user(), OrganizationAccessAction::MemberRevoked, $selected);
        }

        return to_route('organizations.members.index', $organization);
    }
}
