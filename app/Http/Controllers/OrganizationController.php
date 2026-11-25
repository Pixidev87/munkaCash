<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\OrganizationBalanceSummary;
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

    public function show(Request $request, string $organization, OrganizationBalanceSummary $summary): Response
    {
        $selected = $request->user()->findActiveOrganization($organization);
        $exportAvailable = $selected->exportAvailable();

        return Inertia::render('organizations/show', [
            'name' => $selected->name,
            'organizationId' => (string) $selected->id,
            'balanceSummary' => $exportAvailable ? $summary->forOrganization($selected) : null,
            'remindersEnabled' => $selected->reminders_enabled,
            'canViewAccessLog' => $exportAvailable && $request->user()->can('viewAccessLog', $selected),
            'canExportData' => $exportAvailable && $request->user()->can('exportData', $selected),
            'closeout' => $selected->isClosing() ? [
                'startedAt' => $selected->closeout_started_at?->toISOString(),
                'exportUntil' => $selected->export_until?->toISOString(),
                'exportAvailable' => $exportAvailable,
            ] : null,
            'canStartCloseout' => ! $selected->isClosing() && $request->user()->can('closeout', $selected),
            'canManageMembers' => $request->user()->can('manageMembers', $selected),
            'canManageReminders' => $request->user()->can('approveReminder', $selected),
        ]);
    }
}
