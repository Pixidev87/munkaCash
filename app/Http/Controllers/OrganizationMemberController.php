<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\RevokeMemberAccessRequest;
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
        $members = $request->company()->users()
            ->wherePivot('role', 'member')
            ->wherePivotIn('status', ['active', 'inactive']);
        $selected = $members->findOrFail($member, ['users.id']);

        $members->wherePivot('status', 'active')->updateExistingPivot($selected->id, ['status' => 'inactive']);

        return to_route('organizations.members.index', $organization);
    }
}
