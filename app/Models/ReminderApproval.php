<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $fingerprint
 * @property Carbon $approved_at
 * @property Carbon $expires_at
 * @property-read User|null $approver
 * @property-read ReminderTestDelivery|null $testDelivery
 */
#[Fillable(['approved_by', 'fingerprint', 'template_version', 'invoice_number', 'outstanding_amount', 'currency', 'approved_at', 'expires_at'])]
class ReminderApproval extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasOne<ReminderTestDelivery, $this> */
    public function testDelivery(): HasOne
    {
        return $this->hasOne(ReminderTestDelivery::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('approved_at', '<=', now()->subDays(90));
    }
}
