<?php

namespace App\Http\Requests\Reminders;

use App\Enums\ReminderTestDeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class FilterReminderDeliveriesRequest extends FormRequest
{
    /** @return array<string, list<string|EnumRule>> */
    public function rules(): array
    {
        return [
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
            'reason.*' => 'Válassz érvényes küldési okot.',
            'status.*' => 'Válassz érvényes küldési állapotot.',
            'invoice_number.*' => 'A számlaszám legfeljebb 100 karakter lehet.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
