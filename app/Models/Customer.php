<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $display_name
 * @property string|null $billing_email
 * @property Carbon|null $billing_email_confirmed_at
 * @property int $billing_email_revision
 */
#[Fillable(['display_name'])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'billing_email_confirmed_at' => 'datetime',
            'billing_email_revision' => 'integer',
        ];
    }
}
