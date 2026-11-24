<?php

namespace App\Models;

use App\Enums\ReminderTestDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $reminder_approval_id
 * @property ReminderTestDeliveryStatus $status
 * @property string|null $reason
 */
#[Fillable(['status', 'reason'])]
class ReminderTestDelivery extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ReminderTestDeliveryStatus::class,
        ];
    }
}
