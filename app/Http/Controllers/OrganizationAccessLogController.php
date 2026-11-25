<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\FilterAccessLogRequest;
use App\Models\OrganizationAccessEvent;
use App\Services\HungarianWorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationAccessLogController extends Controller
{
    public function index(FilterAccessLogRequest $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('manageMembers', $company);

        $filters = $request->criteria();
        $events = OrganizationAccessEvent::query()
            ->where('organization_id', $company->id)
            ->where('occurred_at', '>', now()->subDays(OrganizationAccessEvent::RETENTION_DAYS))
            ->when(isset($filters['action']), fn ($query) => $query->where('action', $filters['action']))
            ->when(isset($filters['from']), fn ($query) => $query->where(
                'occurred_at', '>=',
                CarbonImmutable::parse($filters['from'], HungarianWorkCalendar::TIMEZONE)->startOfDay()->utc(),
            ))
            ->when(isset($filters['to']), fn ($query) => $query->where(
                'occurred_at', '<=',
                CarbonImmutable::parse($filters['to'], HungarianWorkCalendar::TIMEZONE)->endOfDay()->utc(),
            ))
            ->with(['actor:id,name', 'subject:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->simplePaginate(25)
            ->appends($filters)
            ->through(static fn (OrganizationAccessEvent $event): array => [
                'id' => (string) $event->id,
                'action' => $event->action->value,
                'actor' => $event->actor?->name,
                'subject' => $event->subject?->name,
                'occurredAt' => $event->occurred_at->toISOString(),
            ]);

        return Inertia::render('organizations/access-log', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'filters' => ['action' => $filters['action'] ?? null, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null],
            'events' => $events,
        ]);
    }
}
