<?php

namespace App\Http\Requests\Reminders;

use App\Enums\ReminderTestDeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class FilterReminderDeliveriesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return $this->safe()->only(['status', 'invoice_number', 'reason', 'queued_from', 'queued_to']);
    }

    /** @return array<string, list<string|EnumRule>> */
    public function rules(): array
    {
        $dateRules = ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];

        return [
            'queued_from' => $dateRules,
            'queued_to' => $this->filled('queued_from') ? [...$dateRules, 'after_or_equal:queued_from'] : $dateRules,
            'reason' => ['nullable', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_]*\z/'],
            'status' => ['nullable', Rule::enum(ReminderTestDeliveryStatus::class)],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'queued_from.*' => 'Adj meg érvényes kezdőnapot.',
            'queued_to.*' => 'Adj meg érvényes zárónapot, amely nem előzi meg a kezdőnapot.',
            'reason.*' => 'Válassz érvényes küldési okot.',
            'status.*' => 'Válassz érvényes küldési állapotot.',
            'invoice_number.*' => 'A számlaszám legfeljebb 100 karakter lehet.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
