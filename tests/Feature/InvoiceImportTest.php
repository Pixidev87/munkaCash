<?php

namespace Tests\Feature;

use App\Models\InvoiceImport;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvoiceCsvParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_and_confirmation_is_idempotent(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        $this->actingAs($user);

        $this->get(route('organizations.invoice-imports.template', $company))
            ->assertDownload('munkacash-szamlaimport.csv')
            ->assertStreamedContent(InvoiceCsvParser::HEADER."\r\n1;00042;2026-12-01;HUF;120000\r\n");

        $batch = $this->upload($company, "{$customer};\"00042\";2026-12-01;HUF;120000");
        $this->assertDatabaseCount('invoices', 0);
        $this->get(route('organizations.invoice-imports.show', [$company, $batch]))
            ->assertInertia(fn (Assert $page) => $page->component('organizations/invoice-import-preview')
                ->where('canImport', true)
                ->where('rows.0.status', 'new')
                ->where('rows.0.data.invoice_number', '00042'));

        $this->confirm($company, $batch)->assertSessionHasNoErrors();
        $this->confirm($company, $batch)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', [
            'organization_id' => $company->id,
            'customer_id' => $customer,
            'invoice_number' => '00042',
            'source' => 'csv',
            'status' => 'unchecked',
            'outstanding_amount' => null,
            'gross_amount' => 120000,
        ]);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertNull($batch->fresh()->rows);
        $this->assertSame(1, $batch->fresh()->created_count);

        $repeat = $this->upload($company, "{$customer};00042;2026-12-01;HUF;120000");
        $this->confirm($company, $repeat)->assertSessionHasNoErrors();
        $this->assertSame(1, $repeat->fresh()->unchanged_count);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invalid_rows_and_duplicate_numbers_block_the_entire_batch(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        $this->actingAs($user);
        $batch = $this->upload($company, implode("\n", [
            "{$customer};00001;2026-12-01;HUF;15000",
            "{$customer};00002;2026-02-30;EUR;100.5",
            "{$customer};00003;2026-12-01;HUF;120000",
            "{$customer};00003;2026-12-01;HUF;130000",
            '',
            "{$customer};\"00004;2026-12-01;HUF;40000",
        ]));

        $this->get(route('organizations.invoice-imports.show', [$company, $batch]))
            ->assertInertia(fn (Assert $page) => $page->where('canImport', false)
                ->where('rows.0.status', 'new')
                ->where('rows.1.status', 'invalid')
                ->where('previewCounts', ['new' => 1, 'unchanged' => 0, 'invalid' => 2, 'conflict' => 2])
                ->where('rows.1.line', 3)
                ->where('rows.1.errors.0.code', 'invalid_value')
                ->where('rows.1.errors.0.column', 'due_date')
                ->where('rows.1.errors.1.column', 'currency')
                ->where('rows.1.errors.2.column', 'gross_amount')
                ->where('rows.2.status', 'conflict')
                ->where('rows.2.errors.0.code', 'duplicate_invoice_number')
                ->where('rows.2.errors.0.column', 'invoice_number')
                ->where('rows.2.errors.0.message', 'Ismétlődő számlaszám ezekben a sorokban: 4, 5.')
                ->where('rows.3.status', 'conflict')
                ->where('rows.4.status', 'invalid')
                ->where('rows.4.line', 7)
                ->where('rows.4.errors.0.code', 'row_structure')
                ->where('rows.4.errors.0.column', null));

        $this->confirm($company, $batch)->assertSessionHasErrors('import');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_confirmation_rechecks_existing_invoices_and_preserves_manual_balances(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        $this->actingAs($user);
        $batch = $this->upload($company, "{$customer};00042;2026-12-01;HUF;10000\n{$customer};00043;2026-12-01;HUF;120000");

        $invoice = $company->invoices()->create([
            'customer_id' => $customer,
            'invoice_number' => '00043',
            'due_date' => '2026-12-01',
            'gross_amount' => 130000,
        ]);
        $invoice->forceFill(['outstanding_amount' => 0, 'status' => 'paid'])->save();

        $this->get(route('organizations.invoice-imports.show', [$company, $batch]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.1.errors.0.code', 'existing_invoice_mismatch')
                ->where('rows.1.errors.0.column', 'gross_amount'));

        $this->confirm($company, $batch)->assertSessionHasErrors('import');
        $this->assertDatabaseCount('invoices', 1);

        $matching = $this->upload($company, "{$customer};00043;2026-12-01;HUF;130000");
        $this->confirm($company, $matching)->assertSessionHasNoErrors();
        $this->assertSame(1, $matching->fresh()->unchanged_count);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'outstanding_amount' => 0, 'status' => 'paid', 'source' => 'manual']);
    }

    public function test_invoice_list_shows_and_filters_the_original_source(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        $this->actingAs($user);
        $batch = $this->upload($company, "{$customer};00042;2026-12-01;HUF;10000");
        $this->confirm($company, $batch)->assertSessionHasNoErrors();
        $company->invoices()->create([
            'customer_id' => $customer,
            'invoice_number' => '00043',
            'due_date' => '2026-12-01',
            'gross_amount' => 10_000,
        ]);

        $list = route('organizations.invoices.index', $company);
        $this->get($list.'?source=csv&status=unchecked')->assertInertia(fn (Assert $page) => $page
            ->where('filters.source', 'csv')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.source', 'csv')
            ->where('invoices.data.0.number', '00042'));
        $this->get($list.'?source=manual')->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.source', 'manual')
            ->where('invoices.data.0.number', '00043'));
        $this->from($list)->get($list.'?source=invalid')
            ->assertRedirect($list)
            ->assertSessionHasErrorsIn('invoiceFilters', 'source');
    }

    public function test_batches_are_private_to_their_uploader_and_reject_foreign_customer_ids(): void
    {
        [$company, $user] = $this->createCompany();
        [$other, $coworker, $foreignCustomer] = $this->createCompany();
        $coworker->organizations()->attach($company, ['status' => 'active']);
        $this->actingAs($user);
        $batch = $this->upload($company, "{$foreignCustomer};00042;2026-12-01;HUF;120000");
        $url = route('organizations.invoice-imports.show', [$company, $batch]);

        $this->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.status', 'invalid')
                ->where('rows.0.errors.0.code', 'customer_not_found')
                ->where('rows.0.errors.0.column', 'customer_id'));
        $this->confirm($company, $batch)->assertSessionHasErrors('import');

        $this->actingAs($coworker)->get($url)->assertNotFound();
        $this->get(route('organizations.invoice-imports.show', [$other, $batch]))->assertNotFound();
        $this->confirm($company, $batch)->assertNotFound();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_an_existing_batch_with_legacy_errors_remains_readable_and_blocked(): void
    {
        [$company, $user] = $this->createCompany();
        $batch = InvoiceImport::create([
            'organization_id' => $company->id,
            'user_id' => $user->id,
            'rows' => [['line' => 2, 'data' => null, 'errors' => ['Korábbi importhiba']]],
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($user)->get(route('organizations.invoice-imports.show', [$company, $batch]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canImport', false)
                ->where('rows.0.errors.0.code', 'legacy_row_error')
                ->where('rows.0.errors.0.message', 'Korábbi importhiba'));
        $this->confirm($company, $batch)->assertSessionHasErrors('import');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_import_history_only_shows_the_users_unexpired_batches_in_the_company(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        [$other, $coworker, $otherCustomer] = $this->createCompany();
        $coworker->organizations()->attach($company, ['status' => 'active']);
        $user->organizations()->attach($other, ['status' => 'active']);
        $this->actingAs($user);
        $completed = $this->upload($company, "{$customer};00042;2026-12-01;HUF;120000");
        $this->confirm($company, $completed)->assertSessionHasNoErrors();
        $expired = $this->upload($company, "{$customer};00044;2026-12-01;HUF;120000");
        $expired->update(['expires_at' => now()]);
        $this->upload($other, "{$otherCustomer};00042;2026-12-01;HUF;120000");
        $this->actingAs($coworker);
        $this->upload($company, "{$customer};00045;2026-12-01;HUF;120000");
        $this->actingAs($user)->travel(1)->second();
        $pending = $this->upload($company, "{$customer};00043;2026-12-01;HUF;120000");

        $this->get(route('organizations.invoice-imports.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/invoice-import-history')
                ->has('imports.data', 2)
                ->where('imports.data.0.id', $pending->id)
                ->where('imports.data.0.created', null)
                ->missing('imports.data.0.rows')
                ->where('imports.data.1.id', $completed->id)
                ->where('imports.data.1.created', 1));
    }

    public function test_expired_previews_cannot_be_opened_or_confirmed_and_are_pruned(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        $this->actingAs($user);
        $batch = $this->upload($company, "{$customer};00042;2026-12-01;HUF;120000");
        $this->travel(31)->minutes();

        $this->get(route('organizations.invoice-imports.show', [$company, $batch]))->assertNotFound();
        $this->confirm($company, $batch)->assertNotFound();
        $this->artisan('model:prune', ['--model' => [InvoiceImport::class]])->assertSuccessful();
        $this->assertModelMissing($batch);
    }

    public function test_wrong_header_empty_files_and_excess_rows_are_rejected_before_staging(): void
    {
        [$company, $user, $customer] = $this->createCompany();
        $this->actingAs($user);

        foreach ([
            'invoice_number;currency',
            InvoiceCsvParser::HEADER,
            InvoiceCsvParser::HEADER."\n".str_repeat("{$customer};00042;2026-12-01;HUF;120000\n", 251),
        ] as $contents) {
            $this->post(route('organizations.invoice-imports.store', $company), [
                'csv' => UploadedFile::fake()->createWithContent('szamlak.csv', $contents),
            ])->assertSessionHasErrors('csv');
        }

        $this->assertDatabaseCount('invoice_imports', 0);
    }

    /** @return array{Organization, User, int} */
    private function createCompany(): array
    {
        $company = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($company, ['status' => 'active']);

        return [$company, $user, $company->customers()->create(['display_name' => 'Minta Ügyfél'])->id];
    }

    private function upload(Organization $company, string $rows): InvoiceImport
    {
        $response = $this->post(route('organizations.invoice-imports.store', $company), [
            'csv' => UploadedFile::fake()->createWithContent('szamlak.csv', "\xEF\xBB\xBF".InvoiceCsvParser::HEADER."\r\n".$rows),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return InvoiceImport::query()->findOrFail(basename((string) $response->headers->get('Location')));
    }

    private function confirm(Organization $company, InvoiceImport $batch): TestResponse
    {
        return $this->post(route('organizations.invoice-imports.confirm', [$company, $batch]));
    }
}
