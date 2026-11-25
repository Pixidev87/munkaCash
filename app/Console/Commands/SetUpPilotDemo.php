<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvoiceCsvParser;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetUpPilotDemo extends Command
{
    protected $signature = 'munkacash:demo';

    protected $description = 'Create two fictitious pilot companies and print their local test credentials';

    private const string FIRST_COMPANY = 'MunkaCash Demó · Hárs Műhely';

    private const string SECOND_COMPANY = 'MunkaCash Demó · Duna Stúdió';

    private const array ACCOUNTS = [
        'hars-tulajdonos@munkacash.test' => 'Hárs Műhely tulajdonosa',
        'hars-munkatars@munkacash.test' => 'Hárs Műhely munkatársa',
        'duna-tulajdonos@munkacash.test' => 'Duna Stúdió tulajdonosa',
    ];

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Pilot demo data can only be created in a local or testing environment.');

            return self::FAILURE;
        }

        try {
            $demo = DB::transaction($this->createDemo(...), attempts: 3);
        } catch (UniqueConstraintViolationException) {
            $demo = null;
        }

        if ($demo === null) {
            $this->error('Demo names or email addresses are already in use. Use an empty demo database.');

            return self::FAILURE;
        }

        $this->table(['Demo company', 'ID'], [
            [self::FIRST_COMPANY, $demo['firstCompanyId']],
            [self::SECOND_COMPANY, $demo['secondCompanyId']],
        ]);
        $this->table(['Demo login', 'Password'], $demo['credentials']);
        $this->info('Save these credentials for this local demo. The passwords are only displayed now.');
        $this->newLine();
        $this->line('Copy these two lines into a UTF-8 CSV for Hárs Műhely:');
        $this->line(InvoiceCsvParser::HEADER);
        $this->line(implode(';', [
            $demo['firstCustomerId'],
            'DEMO-CSV-003',
            today('Europe/Budapest')->subWeek()->toDateString(),
            'HUF',
            '65000',
        ]));

        return self::SUCCESS;
    }

    /** @return array{firstCompanyId: int, secondCompanyId: int, firstCustomerId: int, credentials: list<array{string, string}>}|null */
    private function createDemo(): ?array
    {
        if (User::query()->whereIn('email', array_keys(self::ACCOUNTS))->exists()
            || Organization::query()->whereIn('name', [self::FIRST_COMPANY, self::SECOND_COMPANY])->exists()) {
            return null;
        }

        $first = Organization::create(['name' => self::FIRST_COMPANY]);
        $second = Organization::create(['name' => self::SECOND_COMPANY]);
        $credentials = [];
        $users = [];

        foreach (self::ACCOUNTS as $email => $name) {
            $password = Str::random(32);
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $users[] = $user;
            $credentials[] = [$email, $password];
        }

        [$firstOwner, $member, $secondOwner] = $users;
        $first->users()->attach($firstOwner, ['role' => 'owner', 'status' => 'active']);
        $first->users()->attach($member, ['role' => 'member', 'status' => 'active']);
        $second->users()->attach($secondOwner, ['role' => 'owner', 'status' => 'active']);

        $firstCustomer = $first->customers()->create(['display_name' => 'Fiktív ügyfél · Tölgy Iroda']);
        $firstCustomer->forceFill([
            'billing_email' => 'tolgy-penzugy@munkacash.test',
            'billing_email_confirmed_at' => now(),
            'billing_email_confirmed_by' => $firstOwner->id,
        ])->save();
        $secondCustomer = $second->customers()->create(['display_name' => 'Fiktív ügyfél · Part Iroda']);

        $this->createInvoice($first, $firstCustomer, $firstOwner, 'DEMO-001', gross: 120_000, outstanding: 80_000);
        $this->createInvoice($first, $firstCustomer, $firstOwner, 'DEMO-002', gross: 50_000, outstanding: 50_000, disputed: true);
        $this->createInvoice($second, $secondCustomer, $secondOwner, 'DEMO-001', gross: 90_000, outstanding: 90_000);

        return [
            'firstCompanyId' => $first->id,
            'secondCompanyId' => $second->id,
            'firstCustomerId' => $firstCustomer->id,
            'credentials' => $credentials,
        ];
    }

    private function createInvoice(Organization $company, Customer $customer, User $checker, string $number, int $gross, int $outstanding, bool $disputed = false): void
    {
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'due_date' => today('Europe/Budapest')->subWeek()->toDateString(),
            'gross_amount' => $gross,
        ]);

        $invoice->forceFill([
            'outstanding_amount' => $outstanding,
            'status' => InvoiceStatus::fromCheckedBalance($gross, $outstanding),
            'status_source' => 'manual',
            'status_checked_at' => now(),
            'checked_by' => $checker->id,
            'is_disputed' => $disputed,
        ])->save();
    }
}
