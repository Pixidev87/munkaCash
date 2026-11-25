<?php

namespace App\Models;

use App\Services\InvoiceCsvParser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @phpstan-import-type StoredCsvRow from InvoiceCsvParser
 *
 * @property string $id
 * @property list<StoredCsvRow>|null $rows
 * @property int|null $created_count
 * @property int|null $unchanged_count
 * @property Carbon|null $confirmed_at
 * @property Carbon $expires_at
 */
#[Fillable(['organization_id', 'user_id', 'rows', 'created_count', 'unchanged_count', 'confirmed_at', 'expires_at'])]
class InvoiceImport extends Model
{
    use HasUuids, MassPrunable;

    protected function casts(): array
    {
        return [
            'rows' => 'array',
            'created_count' => 'integer',
            'unchanged_count' => 'integer',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @param Builder<InvoiceImport> $query */
    #[Scope]
    protected function availableTo(Builder $query, int $organizationId, int $userId): void
    {
        $query->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('expires_at', '>', now());
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
