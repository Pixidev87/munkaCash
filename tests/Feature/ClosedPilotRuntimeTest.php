<?php

namespace Tests\Feature;

use App\Services\ClosedPilotHeartbeat;
use App\Services\ReminderTestMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClosedPilotRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_pilot_demo_and_test_delivery_keep_the_existing_safety_boundaries(): void
    {
        $this->app->detectEnvironment(fn () => 'closed-pilot');
        $mailer = app(ReminderTestMailer::class);
        $this->assertTrue($mailer->accepts('demo@munkacash.test'));
        $this->assertFalse($mailer->accepts('demo@example.com'));
        $this->get('/')->assertRedirect(route('login'));
        $this->artisan('munkacash:demo')->assertSuccessful();
        $this->assertDatabaseCount('organizations', 2);

        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse($mailer->accepts('demo@munkacash.test'));
    }

    public function test_operational_check_fails_on_missing_heartbeat_or_delayed_jobs(): void
    {
        $this->app->detectEnvironment(fn () => 'closed-pilot');
        $this->freezeSecond();
        $this->artisan('munkacash:pilot:check')->assertFailed();

        $heartbeat = app(ClosedPilotHeartbeat::class);
        $heartbeat->mark(ClosedPilotHeartbeat::SCHEDULER);
        $heartbeat->mark(ClosedPilotHeartbeat::WORKER);
        $this->artisan('munkacash:pilot:check')->assertSuccessful();

        DB::table('jobs')->insert([
            'queue' => 'reminder-tests',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->subMinutes(2)->timestamp,
            'created_at' => now()->subMinutes(2)->timestamp,
        ]);
        $this->artisan('munkacash:pilot:check')->assertFailed();
        DB::table('jobs')->delete();
        $this->travel(3)->minutes();
        $this->artisan('munkacash:pilot:check')->assertFailed();
    }
}
