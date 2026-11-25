<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $do_not_contact
 * @property string $display_name
 * @property string|null $billing_email
 * @property Carbon|null $billing_email_confirmed_at
 * @property int $billing_email_revision
 */
#[Fillable(['display_name'])]
class Customer extends Model
{
    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<CustomerBillingContactEvent, $this> */
    public function billingContactEvents(): HasMany
    {
        return $this->hasMany(CustomerBillingContactEvent::class);
    }

    /** @param Builder<Customer> $query */
    #[Scope]
    protected function withoutConfirmedBillingAddress(Builder $query): void
    {
        $query->where(fn (Builder $address) => $address->whereNull('billing_email')
            ->orWhereNull('billing_email_confirmed_at'));
    }

    protected function casts(): array
    {
        return [
            'do_not_contact' => 'boolean',
            'billing_email_confirmed_at' => 'datetime',
            'billing_email_revision' => 'integer',
        ];
    }
}
