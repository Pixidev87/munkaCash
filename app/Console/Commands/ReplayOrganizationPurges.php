<?php

namespace App\Console\Commands;

use App\Services\OrganizationPurgeLedger;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

class ReplayOrganizationPurges extends Command
{
    protected $signature = 'organizations:purges:replay
        {archive : Path to the encrypted deletion ledger}
        {--apply : Delete restored organizations and restore the ledger in maintenance mode}';

    protected $description = 'Check or reapply past organization deletions on a restored database';

    public function handle(OrganizationPurgeLedger $ledger): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && ! app()->isDownForMaintenance()) {
            $this->error('Enable maintenance mode before applying the deletion ledger.');

            return self::FAILURE;
        }

        $path = realpath((string) $this->argument('archive'));
        $contents = $path !== false && is_file($path) && is_readable($path)
            ? file_get_contents($path, length: OrganizationPurgeLedger::MAX_ARCHIVE_BYTES + 1)
            : false;

        if ($contents === false || strlen($contents) > OrganizationPurgeLedger::MAX_ARCHIVE_BYTES) {
            $this->error('Give a readable deletion ledger archive within the size limit.');

            return self::INVALID;
        }

        try {
            $archive = $ledger->decrypt($contents);
            $affected = $ledger->replay($archive['purges'], $apply);
        } catch (DecryptException|JsonException|ValidationException) {
            $this->error('The deletion ledger archive is invalid or cannot be authenticated.');

            return self::INVALID;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Ledger exported at {$archive['exported_at']}.");
        $this->info($apply ? "Restored organizations deleted: {$affected}." : "Restored organizations to delete: {$affected}.");

        return self::SUCCESS;
    }
}
