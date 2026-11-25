<?php

namespace Tests\Feature;

use App\Jobs\SendReminderTest;
use App\Models\InvoiceImport;
use App\Models\Organization;
use App\Models\User;
use App\Services\ReminderTestMailer;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_refuses_an_active_company_or_an_open_export_window(): void
    {
        $this->freezeSecond();
        $active = Organization::factory()->create();
        $closing = Organization::factory()->create([
            'closeout_started_at' => now()->subDays(30),
            'export_until' => now()->addSecond(),
        ]);

        $this->artisan('organizations:purge', ['organization' => '1.5', '--apply' => true])
            ->assertExitCode(Command::INVALID);
        $this->artisan('organizations:purge', ['organization' => 999, '--apply' => true])
            ->assertExitCode(Command::FAILURE);

        foreach ([$active, $closing] as $company) {
            $this->artisan('organizations:purge', ['organization' => $company->id, '--apply' => true])
                ->assertExitCode(Command::FAILURE);
            $this->assertModelExists($company);
        }

        $this->assertDatabaseCount('organization_purges', 0);
    }

    public function test_purge_removes_only_the_closed_companys_records_and_can_be_retried(): void
    {
        $this->freezeSecond();
        $owner = User::factory()->create();
        $company = Organization::factory()->create([
            'closeout_started_at' => now()->subDays(30),
            'export_until' => now(),
        ]);
        $other = Organization::factory()->create();
        $owner->organizations()->attach([$company->id, $other->id], ['role' => 'owner', 'status' => 'active']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);
        $otherCustomer = $other->customers()->create(['display_name' => 'Másik Ügyfél']);
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 100_000,
        ]);
        $approval = $invoice->reminderApprovals()->create([
            'approved_by' => $owner->id,
            'fingerprint' => str_repeat('a', 64),
            'invoice_number' => '00042',
            'currency' => 'HUF',
            'outstanding_amount' => 60_000,
            'template_version' => 'v1',
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);
        $delivery = $approval->testDelivery()->create(['status' => 'blocked', 'reason' => 'organization_closing']);
        $batch = InvoiceImport::create([
            'organization_id' => $company->id,
            'user_id' => $owner->id,
            'rows' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        $arguments = ['organization' => $company->id];
        $this->artisan('organizations:purge', $arguments)
            ->expectsOutput('Organization is eligible for deletion. Run with --apply to delete.')
            ->assertSuccessful();
        $this->assertModelExists($company);
        $this->assertDatabaseCount('organization_purges', 0);

        $this->artisan('organizations:purge', [...$arguments, '--apply' => true])
            ->expectsOutput('Organization data deleted.')
            ->assertSuccessful();

        foreach ([$company, $customer, $invoice, $approval, $delivery, $batch] as $record) {
            $this->assertModelMissing($record);
        }

        $this->assertDatabaseMissing('organization_user', ['organization_id' => $company->id]);
        $this->assertModelExists($owner);
        $this->assertModelExists($otherCustomer);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $other->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('organization_purges', ['organization_id' => $company->id, 'purged_at' => now()]);
        $this->actingAs($owner)->get(route('organizations.show', $company))->assertNotFound();
        $this->get(route('organizations.exports.customers', $company))->assertNotFound();

        $this->mock(ReminderTestMailer::class)->shouldNotReceive('send');
        app()->call([new SendReminderTest($delivery->id), 'handle']);

        $this->artisan('organizations:purge', [...$arguments, '--apply' => true])
            ->expectsOutput('Organization was already deleted.')
            ->assertSuccessful();
        $this->assertDatabaseCount('organization_purges', 1);
    }
}
