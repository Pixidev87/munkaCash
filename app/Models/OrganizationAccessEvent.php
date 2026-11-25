<?php

namespace App\Models;

use App\Enums\OrganizationAccessAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property OrganizationAccessAction $action
 * @property Carbon $occurred_at
 */
#[Fillable(['organization_id', 'actor_id', 'subject_id', 'action', 'occurred_at'])]
class OrganizationAccessEvent extends Model
{
    use MassPrunable;

    public const int RETENTION_DAYS = 90;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['action' => OrganizationAccessAction::class, 'occurred_at' => 'datetime'];
    }

    public static function record(Organization $company, User $actor, OrganizationAccessAction $action, ?User $subject = null): void
    {
        static::query()->create([
            'organization_id' => $company->id,
            'actor_id' => $actor->id,
            'subject_id' => $subject?->id,
            'action' => $action,
            'occurred_at' => now(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('occurred_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }
}
