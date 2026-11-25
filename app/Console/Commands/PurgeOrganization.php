<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurgeOrganization extends Command
{
    protected $signature = 'organizations:purge
        {organization : The numeric organization ID}
        {--apply : Permanently delete the organization after its export window}';

    protected $description = 'Check eligibility for, or apply, the permanent deletion of a single closed organization';

    public function handle(): int
    {
        $id = filter_var($this->argument('organization'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            $this->error('Give a valid organization ID.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        $result = DB::transaction(function () use ($id, $apply): string {
            $company = Organization::query()->lockForUpdate()->find($id);

            if ($company === null) {
                return DB::table('organization_purges')->where('organization_id', $id)->exists()
                    ? 'already_purged' : 'missing';
            }

            if (! $company->isClosing() || $company->export_until === null || $company->export_until->isFuture()) {
                return 'ineligible';
            }

            if (! $apply) {
                return 'eligible';
            }

            // The record and its tenant-owned descendants are removed in the same transaction.
            if ($company->deleteOrFail() !== true) {
                throw new RuntimeException('The organization could not be deleted.');
            }
            DB::table('organization_purges')->insert([
                'organization_id' => $id,
                'purged_at' => now(),
            ]);

            return 'purged';
        }, attempts: 3);

        $message = match ($result) {
            'purged' => 'Organization data deleted.',
            'already_purged' => 'Organization was already deleted.',
            'eligible' => 'Organization is eligible for deletion. Run with --apply to delete.',
            'ineligible' => 'Organization must be closed and its export window must have expired.',
            'missing' => 'Organization was not found.',
        };

        if (in_array($result, ['missing', 'ineligible'], true)) {
            $this->error($message);

            return self::FAILURE;
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
