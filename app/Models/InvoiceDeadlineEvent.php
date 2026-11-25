<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $previous_due_date
 * @property Carbon $due_date
 * @property Carbon $occurred_at
 */
#[Fillable(['actor_id', 'previous_due_date', 'due_date', 'occurred_at'])]
class InvoiceDeadlineEvent extends Model
{
    use MassPrunable;

    public const int RETENTION_DAYS = 90;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['previous_due_date' => 'date', 'due_date' => 'date', 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('occurred_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }
}
