<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\FilterAccessLogRequest;
use App\Models\OrganizationAccessEvent;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationAccessLogController extends Controller
{
    public function index(FilterAccessLogRequest $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('manageMembers', $company);

        $action = $request->validated('action');
        $events = OrganizationAccessEvent::query()
            ->where('organization_id', $company->id)
            ->where('occurred_at', '>', now()->subDays(OrganizationAccessEvent::RETENTION_DAYS))
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->with(['actor:id,name', 'subject:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->simplePaginate(25)
            ->appends(['action' => $action])
            ->through(static fn (OrganizationAccessEvent $event): array => [
                'id' => (string) $event->id,
                'action' => $event->action->value,
                'actor' => $event->actor?->name,
                'subject' => $event->subject?->name,
                'occurredAt' => $event->occurred_at->toISOString(),
            ]);

        return Inertia::render('organizations/access-log', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'filters' => ['action' => $action],
            'events' => $events,
        ]);
    }
}
