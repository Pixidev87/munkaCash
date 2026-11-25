<?php

namespace Tests\Feature;

use App\Models\InvoiceImport;
use App\Models\User;
use App\Services\InvoiceCsvParser;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PilotDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_data_and_generated_csv_use_the_real_pilot_workflow(): void
    {
        $this->travelTo(Carbon::parse('2026-12-02 10:00:00', 'Europe/Budapest'));
        $this->assertSame(Command::SUCCESS, Artisan::call('munkacash:demo'));
        $output = Artisan::output();

        $owner = User::query()->where('email', 'hars-tulajdonos@munkacash.test')->sole();
        $member = User::query()->where('email', 'hars-munkatars@munkacash.test')->sole();
        $otherOwner = User::query()->where('email', 'duna-tulajdonos@munkacash.test')->sole();
        $company = $owner->activeOrganizations()->sole();
        $other = $otherOwner->activeOrganizations()->sole();

        $passwords = [];
        foreach ([$owner, $member, $otherOwner] as $user) {
            $this->assertSame(1, preg_match('/'.preg_quote($user->email, '/').'\s*\|\s*([A-Za-z0-9]{32})\s*\|/', $output, $match));
            $passwords[] = $match[1];
            $this->assertTrue(Hash::check($match[1], $user->password));
            $this->assertTrue($user->hasVerifiedEmail());
        }

        $this->assertCount(3, array_unique($passwords));
        $this->post(route('login.store'), ['email' => $owner->email, 'password' => $passwords[0]])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);

        $invoice = $company->invoices()->where('invoice_number', 'DEMO-001')->sole();
        $disputed = $company->invoices()->where('invoice_number', 'DEMO-002')->sole();
        $this->assertSame(120_000, $invoice->gross_amount);
        $this->assertSame(80_000, $invoice->outstanding_amount);
        $this->assertTrue($disputed->is_disputed);
        $this->assertFalse($company->reminders_enabled);
        $this->assertFalse($other->reminders_enabled);
        $this->assertDatabaseCount('reminder_approvals', 0);
        $this->assertDatabaseCount('reminder_test_deliveries', 0);

        $this->get(route('organizations.invoices.reminder-preview', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('outstanding', 80_000)
                ->where('testRecipient', true));
        $this->get(route('organizations.invoices.reminder-preview', [$company, $disputed]))
            ->assertInertia(fn (Assert $page) => $page->where('message', null));
        $this->get(route('organizations.invoices.index', $other))->assertNotFound();

        $this->assertSame(1, preg_match('/^\d+;DEMO-CSV-003;\d{4}-\d{2}-\d{2};HUF;65000$/m', $output, $csv));
        $this->post(route('organizations.invoice-imports.store', $company), [
            'csv' => UploadedFile::fake()->createWithContent('pilot.csv', InvoiceCsvParser::HEADER."\n".$csv[0]),
        ])->assertSessionHasNoErrors();
        $batch = InvoiceImport::query()->sole();
        $this->post(route('organizations.invoice-imports.confirm', [$company, $batch]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['organization_id' => $company->id, 'invoice_number' => 'DEMO-CSV-003', 'source' => 'csv']);

        $this->actingAs($otherOwner)->get(route('organizations.show', $company))->assertNotFound();
        $this->actingAs($member)->get(route('organizations.show', $company))->assertOk();
        $this->get(route('organizations.exports.invoices', $company))->assertForbidden();
        $member->organizations()->updateExistingPivot($company->id, ['status' => 'inactive']);
        $this->get(route('organizations.show', $company))->assertNotFound();
    }

    public function test_demo_command_refuses_nonlocal_environments_and_preserves_existing_accounts(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('munkacash:demo')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);

        $this->app->instance('env', 'testing');
        $existing = User::factory()->create(['email' => 'hars-munkatars@munkacash.test']);
        $this->artisan('munkacash:demo')->assertFailed();
        $this->assertSame($existing->password, $existing->refresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizations', 0);
    }
}
