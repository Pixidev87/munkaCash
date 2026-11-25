<?php

namespace Tests\Feature;

use App\Enums\OrganizationAccessAction as Action;
use App\Models\Organization;
use App\Models\OrganizationAccessEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function ownerOf(Organization $company): User
    {
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);

        return $owner;
    }
}
