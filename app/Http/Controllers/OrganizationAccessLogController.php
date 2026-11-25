<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\FilterAccessLogRequest;
use App\Models\OrganizationAccessEvent;
use App\Models\User;
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
        Gate::authorize('viewAccessLog', $company);

        $filters = $request->criteria();
        $recent = OrganizationAccessEvent::query()
            ->where('organization_id', $company->id)
            ->where('occurred_at', '>', now()->subDays(OrganizationAccessEvent::RETENTION_DAYS));
        $actors = User::query()->whereIn('id', (clone $recent)->select('actor_id'))
            ->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(static fn (User $actor): array => ['id' => (string) $actor->id, 'name' => $actor->name]);

        $events = $recent
            ->when(isset($filters['actor_id']), fn ($query) => $query->where('actor_id', $filters['actor_id']))
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
            'actors' => $actors,
            'filters' => ['action' => $filters['action'] ?? null, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'actorId' => isset($filters['actor_id']) ? (string) $filters['actor_id'] : null],
            'events' => $events,
        ]);
    }
}
