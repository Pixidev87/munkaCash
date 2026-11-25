<?php

namespace Tests\Feature;

use App\Enums\OrganizationAccessAction as Action;
use App\Models\Organization;
use App\Models\OrganizationAccessEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationAccessLogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_filter_matches_only_events_for_the_selected_company(): void
    {
        $company = Organization::factory()->create();
        $owner = $this->ownerOf($company);
        OrganizationAccessEvent::record($company, $owner, Action::CustomerExport);
        OrganizationAccessEvent::record($company, $owner, Action::InvoiceExport);
        OrganizationAccessEvent::record(Organization::factory()->create(), $owner, Action::InvoiceExport);

        $route = 'organizations.access-log';
        $this->actingAs($owner)->get(route($route, [$company, 'action' => 'invoices_export']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('filters.action', 'invoices_export')
                ->where('events.data.0.action', 'invoices_export'));
        $this->getJson(route($route, [$company, 'action' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('action');
    }

    public function test_date_filter_uses_local_day_boundaries_and_rejects_a_reversed_range(): void
    {
        $company = Organization::factory()->create();
        $owner = $this->ownerOf($company);
        foreach (['2026-11-24 22:59:59', '2026-11-24 23:00:00', '2026-11-25 22:59:59', '2026-11-25 23:00:00'] as $at) {
            $this->travelTo(Carbon::parse($at, 'UTC'));
            OrganizationAccessEvent::record($company, $owner, Action::CustomerExport);
        }

        $params = [$company, 'action' => 'customers_export', 'from' => '2026-11-25', 'to' => '2026-11-25'];
        $this->actingAs($owner)->get(route('organizations.access-log', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 2)
                ->where('events.data.0.occurredAt', '2026-11-25T22:59:59.000000Z')
                ->where('events.data.1.occurredAt', '2026-11-24T23:00:00.000000Z'));
        $this->getJson(route('organizations.access-log', [...$params, 'to' => '2026-11-24']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    private function ownerOf(Organization $company): User
    {
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);

        return $owner;
    }
}
