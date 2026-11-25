<?php

namespace Tests\Feature;

use App\Services\ReminderTestMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
