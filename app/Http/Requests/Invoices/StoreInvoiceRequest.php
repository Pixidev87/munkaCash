<?php

namespace App\Http\Requests\Invoices;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreInvoiceRequest extends FormRequest
{
    protected $errorBag = 'invoice';

    /** @return array<string, list<string|Exists>> */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'bail', 'required', 'integer',
                Rule::exists('customers', 'id')->where('organization_id', (string) $this->route('organization')),
            ],
            'invoice_number' => ['required', 'string', 'max:100'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'gross_amount' => ['required', 'integer', 'min:1', 'max:'.Invoice::MAX_GROSS_AMOUNT],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.*' => 'Válassz ügyfelet a cég ügyfelei közül.',
            'invoice_number.*' => 'Add meg a számlaszámot, legfeljebb 100 karakterben.',
            'due_date.*' => 'Adj meg érvényes fizetési határidőt.',
            'gross_amount.*' => 'Adj meg pozitív egész forintösszeget, legfeljebb 12 számjeggyel.',
        ];
    }
}
