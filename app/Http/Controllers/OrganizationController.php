<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $organizations = $request->user()->activeOrganizations()
            ->orderBy('organizations.name')
            ->orderBy('organizations.id')
            ->get(['organizations.id', 'organizations.name'])
            ->map(fn (Organization $organization): array => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
            ]);

        return Inertia::render('dashboard', [
            'organizations' => $organizations,
        ]);
    }

    public function show(Request $request, string $organization): Response
    {
        $selected = $request->user()->findActiveOrganization($organization);

        return Inertia::render('organizations/show', [
            'name' => $selected->name,
            'organizationId' => (string) $selected->id,
        ]);
    }
}
