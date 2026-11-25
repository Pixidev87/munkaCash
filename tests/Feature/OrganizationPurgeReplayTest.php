<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationPurgeReplayTest extends TestCase
{
    use RefreshDatabase;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.maintenance.driver' => 'array']);
        $this->archive = tempnam(sys_get_temp_dir(), 'munka-purges-');
        $this->freezeSecond();
    }

    protected function tearDown(): void
    {
        unlink($this->archive);

        parent::tearDown();
    }

    public function test_replay_checks_then_removes_the_restored_company_and_is_repeatable(): void
    {
        $company = Organization::factory()->create(['created_at' => now()->subMonths(2)]);
        $customer = $company->customers()->create(['display_name' => 'Ügyfél']);
        $owner = User::factory()->create();
        $otherCompany = Organization::factory()->create();
        $owner->organizations()->attach([$company->id, $otherCompany->id], ['role' => 'owner', 'status' => 'active']);

        $purgedAt = now()->subWeek();
        $this->exportLedger([$company->id => $purgedAt, $otherCompany->id + 1 => $purgedAt]);

        $this->artisan('organizations:purges:replay', ['archive' => $this->archive])
            ->expectsOutput('Restored organizations to delete: 1.')
            ->assertSuccessful();
        $this->assertModelExists($company);
        $this->assertDatabaseCount('organization_purges', 0);

        $arguments = ['archive' => $this->archive, '--apply' => true];
        $this->artisan('organizations:purges:replay', $arguments)->assertFailed();
        $this->assertModelExists($customer);

        app()->maintenanceMode()->activate([]);
        $this->artisan('organizations:purges:replay', $arguments)
            ->expectsOutput('Restored organizations deleted: 1.')
            ->assertSuccessful();
        $this->assertModelMissing($company);
        $this->assertModelMissing($customer);
        $this->assertModelExists($owner);
        $this->assertModelExists($otherCompany);
        $this->assertDatabaseHas('organization_user', ['user_id' => $owner->id, 'organization_id' => $otherCompany->id]);
        $this->assertDatabaseHas('organization_purges', ['organization_id' => $company->id, 'purged_at' => $purgedAt]);
        $this->assertDatabaseHas('organization_purges', ['organization_id' => $otherCompany->id + 1, 'purged_at' => $purgedAt]);

        $this->artisan('organizations:purges:replay', $arguments)
            ->expectsOutput('Restored organizations deleted: 0.')
            ->assertSuccessful();
        $this->assertDatabaseCount('organization_purges', 2);
    }

    public function test_replay_rejects_tampering_and_rolls_back_if_an_id_has_been_reused(): void
    {
        $original = Organization::factory()->create(['created_at' => now()->subMonths(2)]);
        $newer = Organization::factory()->create();
        $this->exportLedger([$original->id => now()->subWeek(), $newer->id => now()->subWeek()]);
        app()->maintenanceMode()->activate([]);

        $arguments = ['archive' => $this->archive, '--apply' => true];
        $this->artisan('organizations:purges:replay', $arguments)
            ->expectsOutput("Cannot verify the identity of restored organization {$newer->id}.")
            ->assertFailed();
        $this->assertModelExists($original);
        $this->assertModelExists($newer);
        $this->assertDatabaseCount('organization_purges', 0);

        file_put_contents($this->archive, 'invalid archive');
        $this->artisan('organizations:purges:replay', $arguments)
            ->expectsOutput('The deletion ledger archive is invalid or cannot be authenticated.')
            ->assertExitCode(Command::INVALID);
        $this->assertModelExists($original);
        $this->assertDatabaseCount('organization_purges', 0);
    }

    private function exportLedger(array $purges): void
    {
        foreach ($purges as $id => $purgedAt) {
            DB::table('organization_purges')->insert(['organization_id' => $id, 'purged_at' => $purgedAt]);
        }

        $this->assertSame(Command::SUCCESS, Artisan::call('organizations:purges:export'));
        file_put_contents($this->archive, Artisan::output());

        // The restored fixture represents a snapshot taken before these deletions.
        DB::table('organization_purges')->delete();
    }
}
