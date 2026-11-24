<?php

namespace App\Models;

use App\Enums\ReminderTestDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $reminder_approval_id
 * @property ReminderTestDeliveryStatus $status
 * @property string|null $reason
 * @property Carbon|null $available_at
 * @property Carbon $updated_at
 */
#[Fillable(['status', 'reason', 'available_at'])]
class ReminderTestDelivery extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'status' => ReminderTestDeliveryStatus::class,
        ];
    }
}
