<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $reminders_enabled
 * @property string $name
 * @property Carbon|null $closeout_started_at
 * @property Carbon|null $export_until
 */
#[Fillable(['name'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reminders_enabled' => 'boolean',
            'closeout_started_at' => 'datetime',
            'export_until' => 'datetime',
        ];
    }

    public function isClosing(): bool
    {
        return $this->closeout_started_at !== null;
    }

    public function exportAvailable(): bool
    {
        return ! $this->isClosing() || $this->export_until?->isFuture() === true;
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
