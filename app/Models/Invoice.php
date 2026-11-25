<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $organization_id
 * @property string|null $invoice_import_id
 * @property string $source
 * @property string $currency
 * @property string $invoice_number
 * @property Carbon $due_date
 * @property int $gross_amount
 * @property int|null $outstanding_amount
 * @property InvoiceStatus $status
 * @property Carbon|null $status_checked_at
 * @property bool $is_disputed
 * @property int $revision
 * @property-read Customer $customer
 * @property-read User|null $checker
 */
#[Fillable(['customer_id', 'invoice_number', 'due_date', 'gross_amount'])]
class Invoice extends Model
{
    public const int MAX_GROSS_AMOUNT = 999_999_999_999;

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'due_date' => 'date',
            'gross_amount' => 'integer',
            'outstanding_amount' => 'integer',
            'revision' => 'integer',
            'is_disputed' => 'boolean',
            'status' => InvoiceStatus::class,
            'status_checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<InvoiceImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(InvoiceImport::class, 'invoice_import_id');
    }

    /** @return BelongsTo<User, $this> */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** @return HasMany<InvoiceBalanceEvent, $this> */
    public function balanceEvents(): HasMany
    {
        return $this->hasMany(InvoiceBalanceEvent::class);
    }

    /** @return HasMany<InvoiceDeadlineEvent, $this> */
    public function deadlineEvents(): HasMany
    {
        return $this->hasMany(InvoiceDeadlineEvent::class);
    }

    /** @return HasMany<ReminderApproval, $this> */
    public function reminderApprovals(): HasMany
    {
        return $this->hasMany(ReminderApproval::class);
    }
}
