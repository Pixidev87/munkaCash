<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_downloads_only_their_companys_customers_and_untrusted_text_is_neutralized(): void
    {
        $company = Organization::factory()->create();
        $owner = $this->attach($company, 'owner');
        $this->freezeSecond();
        $customer = $company->customers()->create(['display_name' => '  =1+1; "Minta"']);
        $customer->forceFill([
            'billing_email' => 'penzugy@example.test',
            'billing_email_confirmed_at' => now(),
            'do_not_contact' => true,
        ])->save();
        $other = Organization::factory()->create();
        $this->attach($other, 'owner', $owner);
        $other->customers()->create(['display_name' => 'Másik cég ügyfele']);

        $response = $this->actingAs($owner)->get(route('organizations.exports.customers', $company))
            ->assertOk()
            ->assertDownload('munkacash-ugyfelek-'.$company->id.'.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame([
            ['customer_id', 'display_name', 'billing_email', 'billing_email_confirmed_at_utc', 'do_not_contact'],
            [(string) $customer->id, "'  =1+1; \"Minta\"", 'penzugy@example.test', now()->toISOString(), '1'],
        ], $this->rows($response));
    }

    public function test_invoice_export_keeps_customer_links_leading_zeroes_and_unknown_and_zero_balances_distinct(): void
    {
        $company = Organization::factory()->create();
        $owner = $this->attach($company, 'owner');
        $customer = $company->customers()->create(['display_name' => 'Minta Kft.']);
        $this->freezeSecond();
        $fields = ['customer_id' => $customer->id, 'due_date' => '2026-12-01', 'gross_amount' => 120_000];
        $unchecked = $company->invoices()->create([...$fields, 'invoice_number' => '00042']);
        $paid = $company->invoices()->create([...$fields, 'invoice_number' => '+MINTA-1']);
        $paid->forceFill(['outstanding_amount' => 0, 'status' => 'paid', 'status_checked_at' => now(), 'is_disputed' => true])->save();
        $other = Organization::factory()->create();
        $foreign = $other->customers()->create(['display_name' => 'Másik ügyfél']);
        $other->invoices()->create([...$fields, 'customer_id' => $foreign->id, 'invoice_number' => '00042']);

        $response = $this->actingAs($owner)->get(route('organizations.exports.invoices', $company))
            ->assertDownload('munkacash-szamlak-'.$company->id.'.csv');

        $this->assertSame([
            ['invoice_id', 'customer_id', 'invoice_number', 'due_date', 'currency', 'gross_amount', 'outstanding_amount', 'status', 'status_checked_at_utc', 'is_disputed'],
            [(string) $unchecked->id, (string) $customer->id, '00042', '2026-12-01', 'HUF', '120000', '', 'unchecked', '', '0'],
            [(string) $paid->id, (string) $customer->id, "'+MINTA-1", '2026-12-01', 'HUF', '120000', '0', 'paid', now()->toISOString(), '1'],
        ], $this->rows($response));
    }

    public function test_only_current_verified_owners_may_download_and_the_company_page_reflects_that(): void
    {
        $company = Organization::factory()->create();
        $owner = $this->attach($company, 'owner');
        $member = $this->attach($company, 'member');
        $outsider = User::factory()->create();
        $unverified = $this->attach($company, 'owner', User::factory()->unverified()->create());
        $url = route('organizations.exports.invoices', $company);
        $page = route('organizations.show', $company);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($unverified)->get($url)->assertRedirect(route('verification.notice'));
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($member)->get($url)->assertForbidden();
        $this->get(route('organizations.exports.customers', $company))->assertForbidden();
        $this->get($page)->assertInertia(fn (Assert $page) => $page->where('canExportData', false));
        $this->actingAs($owner)->get($page)->assertInertia(fn (Assert $page) => $page->where('canExportData', true));

        $owner->organizations()->updateExistingPivot($company->id, ['status' => 'inactive']);
        $this->get($url)->assertNotFound();
    }

    private function attach(Organization $company, string $role, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $user->organizations()->attach($company, ['status' => 'active', 'role' => $role]);

        return $user;
    }

    /** @return list<list<string|null>> */
    private function rows(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
