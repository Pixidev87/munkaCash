<?php

namespace App\Models;

use App\Enums\BillingContactAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property BillingContactAction $action
 * @property Carbon $occurred_at
 */
#[Fillable(['actor_id', 'action', 'occurred_at'])]
class CustomerBillingContactEvent extends Model
{
    use MassPrunable;

    public const int RETENTION_DAYS = 90;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['action' => BillingContactAction::class, 'occurred_at' => 'datetime'];
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
