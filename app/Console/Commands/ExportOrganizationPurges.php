<?php

namespace App\Console\Commands;

use App\Services\OrganizationPurgeLedger;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class ExportOrganizationPurges extends Command
{
    protected $signature = 'organizations:purges:export';

    protected $description = 'Write an encrypted organization deletion ledger to standard output';

    public function handle(OrganizationPurgeLedger $ledger): int
    {
        try {
            $archive = $ledger->export();
        } catch (JsonException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($archive);

        return self::SUCCESS;
    }
}
