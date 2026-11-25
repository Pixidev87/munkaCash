<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;
use RuntimeException;

/**
 * @phpstan-type PurgeEntry array{organization_id: int, purged_at: string}
 * @phpstan-type PurgeArchive array{version: int, exported_at: string, purges: list<PurgeEntry>}
 */
class OrganizationPurgeLedger
{
    private const int MAX_ENTRIES = 10_000;

    public const int MAX_ARCHIVE_BYTES = 4 * 1024 * 1024;

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    public function export(): string
    {
        $rows = DB::table('organization_purges')
            ->orderBy('organization_id')
            ->limit(self::MAX_ENTRIES + 1)
            ->get(['organization_id', 'purged_at']);

        if ($rows->count() > self::MAX_ENTRIES) {
            throw new RuntimeException('The deletion ledger exceeds the export limit.');
        }

        return Crypt::encryptString(json_encode([
            'version' => 1,
            'exported_at' => now()->toDateTimeString(),
            'purges' => $rows->map(static fn (object $row): array => [
                'organization_id' => (int) $row->organization_id,
                'purged_at' => (string) $row->purged_at,
            ])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return PurgeArchive
     *
     * @throws JsonException
     */
    public function decrypt(string $contents): array
    {
        $data = json_decode(Crypt::decryptString(trim($contents)), true, 16, JSON_THROW_ON_ERROR);

        $validated = Validator::make(['archive' => $data], [
            'archive' => ['required', 'array:version,exported_at,purges'],
            'archive.version' => ['required', 'integer', 'in:1'],
            'archive.exported_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'archive.purges' => ['present', 'array', 'list', 'max:'.self::MAX_ENTRIES],
            'archive.purges.*' => ['required', 'array:organization_id,purged_at'],
            'archive.purges.*.organization_id' => ['required', 'integer', 'min:1', 'distinct'],
            'archive.purges.*.purged_at' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:archive.exported_at'],
        ])->validate();

        $archive = $validated['archive'];
        $exportedAt = $archive['exported_at'];
        if (! is_string($exportedAt)) {
            throw new JsonException('Invalid archive timestamp.');
        }

        $entries = [];
        foreach ($archive['purges'] as $entry) {
            if (! is_string($entry['purged_at'])) {
                throw new JsonException('Invalid deletion timestamp.');
            }

            $entries[] = [
                'organization_id' => (int) $entry['organization_id'],
                'purged_at' => $entry['purged_at'],
            ];
        }

        return ['version' => 1, 'exported_at' => $exportedAt, 'purges' => $entries];
    }

    /** @param list<PurgeEntry> $entries */
    public function replay(array $entries, bool $apply): int
    {
        return DB::transaction(function () use ($entries, $apply): int {
            $affected = 0;

            foreach ($entries as $entry) {
                $id = $entry['organization_id'];
                $company = Organization::query()->lockForUpdate()->find($id);
                $record = DB::table('organization_purges')->where('organization_id', $id)->lockForUpdate()->first();

                if ($record !== null && (string) $record->purged_at !== $entry['purged_at']) {
                    throw new RuntimeException("Conflicting deletion history for organization {$id}.");
                }

                if ($company !== null && ($company->created_at === null || $company->created_at->toDateTimeString() > $entry['purged_at'])) {
                    throw new RuntimeException("Cannot verify the identity of restored organization {$id}.");
                }

                $affected += (int) ($company !== null);
                if (! $apply) {
                    continue;
                }

                if ($company !== null && $company->deleteOrFail() !== true) {
                    throw new RuntimeException("Could not delete restored organization {$id}.");
                }

                if ($record === null) {
                    DB::table('organization_purges')->insert($entry);
                }
            }

            return $affected;
        }, attempts: 3);
    }
}
