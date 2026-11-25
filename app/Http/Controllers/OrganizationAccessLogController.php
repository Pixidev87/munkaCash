<?php

namespace App\Http\Controllers;

use App\Models\OrganizationAccessEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationAccessLogController extends Controller
{
    public function index(Request $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('manageMembers', $company);

        $events = OrganizationAccessEvent::query()
            ->where('organization_id', $company->id)
            ->where('occurred_at', '>', now()->subDays(OrganizationAccessEvent::RETENTION_DAYS))
            ->with(['actor:id,name', 'subject:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->simplePaginate(25)
            ->through(static fn (OrganizationAccessEvent $event): array => [
                'id' => (string) $event->id,
                'action' => $event->action->value,
                'actor' => $event->actor?->name,
                'subject' => $event->subject?->name,
                'occurredAt' => $event->occurred_at->toISOString(),
            ]);

        return Inertia::render('organizations/access-log', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'events' => $events,
        ]);
    }
}
