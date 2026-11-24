<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_member_can_register_a_customer_and_an_unchecked_invoice(): void
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);

        $this->actingAs($user)->post(route('organizations.customers.store', $company), [
            'display_name' => 'Minta Ügyfél Kft.',
        ])->assertRedirect();
        $customer = $company->customers()->sole();

        $this->post(route('organizations.invoices.store', $company), [
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ])->assertRedirect(route('organizations.invoices.index', $company));

        $this->assertDatabaseHas('invoices', [
            'organization_id' => $company->id,
            'invoice_number' => '00042',
            'gross_amount' => 120_000,
            'currency' => 'HUF',
            'status' => 'unchecked',
            'outstanding_amount' => null,
            'checked_by' => null,
        ]);
        $this->get(route('organizations.invoices.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/invoices')
                ->where('invoices.data.0.number', '00042')
                ->where('invoices.data.0.dueDate', '2026-12-01')
                ->where('invoices.data.0.outstanding', null)
                ->where('customers.0.name', 'Minta Ügyfél Kft.'));
    }

    public function test_review_distinguishes_open_partial_and_paid_balances_and_records_the_checker(): void
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);
        $invoice = $this->createInvoice($company);
        $url = route('organizations.invoices.balance.update', [$company, $invoice]);

        $this->actingAs($user)->freezeTime();

        foreach ([120_000 => 'open', 80_000 => 'partially_paid', 0 => 'paid'] as $amount => $status) {
            $revision = $invoice->refresh()->revision;

            $this->patch($url, [
                'outstanding_amount' => (string) $amount,
                'revision' => $revision,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('invoices', [
                'id' => $invoice->id,
                'gross_amount' => 120_000,
                'outstanding_amount' => $amount,
                'status' => $status,
                'status_source' => 'manual',
                'status_checked_at' => now()->toDateTimeString(),
                'checked_by' => $user->id,
                'revision' => $revision + 1,
            ]);
        }
    }

    public function test_invalid_and_stale_balance_updates_do_not_overwrite_the_latest_review(): void
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);
        $invoice = $this->createInvoice($company);
        $url = route('organizations.invoices.balance.update', [$company, $invoice]);
        $this->actingAs($user);

        foreach (['', -1, 120_001, 80.5] as $amount) {
            $this->patch($url, ['outstanding_amount' => $amount, 'revision' => 0])
                ->assertSessionHasErrorsIn('balance-'.$invoice->id, 'outstanding_amount');
        }

        $this->patch($url, ['outstanding_amount' => 80_000, 'revision' => 0])
            ->assertSessionHasNoErrors();
        $this->patch($url, ['outstanding_amount' => 20_000, 'revision' => 0])
            ->assertSessionHasErrorsIn('balance-'.$invoice->id, 'outstanding_amount');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'outstanding_amount' => 80_000,
            'revision' => 1,
        ]);
    }

    public function test_invoices_and_customers_are_scoped_to_the_selected_active_organization(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company->id, ['status' => 'active']);
        $user->organizations()->attach($other->id, ['status' => 'pending']);
        $foreignInvoice = $this->createInvoice($other);
        $payload = [
            'customer_id' => $foreignInvoice->customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ];

        $this->actingAs($user)->get(route('organizations.invoices.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 0)
                ->has('customers', 0));
        $this->get(route('organizations.invoices.index', $other))->assertNotFound();
        $this->post(route('organizations.customers.store', $other), ['display_name' => 'Tiltott'])
            ->assertNotFound();
        $this->post(route('organizations.invoices.store', $other), $payload)->assertNotFound();
        $this->post(route('organizations.invoices.store', $company), $payload)
            ->assertSessionHasErrorsIn('invoice', 'customer_id');
        $this->patch(route('organizations.invoices.balance.update', [$company, $foreignInvoice]), [
            'outstanding_amount' => 0,
            'revision' => 0,
        ])->assertNotFound();

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('invoices', ['id' => $foreignInvoice->id, 'outstanding_amount' => null]);
    }

    public function test_duplicate_numbers_are_rejected_within_a_company_but_allowed_across_companies(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach([$first->id, $second->id], ['status' => 'active']);
        $firstInvoice = $this->createInvoice($first);
        $secondCustomer = $second->customers()->create(['display_name' => 'Másik ügyfél']);
        $payload = [
            'customer_id' => $firstInvoice->customer->id,
            'invoice_number' => $firstInvoice->invoice_number,
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ];

        $this->actingAs($user)->post(route('organizations.invoices.store', $first), $payload)
            ->assertSessionHasErrorsIn('invoice', 'invoice_number');

        $payload['customer_id'] = $secondCustomer->id;
        $this->post(route('organizations.invoices.store', $second), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_the_database_rejects_a_customer_from_another_organization(): void
    {
        $company = Organization::factory()->create();
        $foreign = Organization::factory()->create()->customers()->create(['display_name' => 'Másik ügyfél']);

        $this->expectException(QueryException::class);

        $company->invoices()->create([
            'customer_id' => $foreign->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
    }

    private function createInvoice(Organization $company): Invoice
    {
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);

        return $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
    }
}
