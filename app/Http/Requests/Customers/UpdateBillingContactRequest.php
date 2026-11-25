<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingContactRequest extends FormRequest
{
    protected $errorBag = 'address';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'billing_email' => ['present', 'nullable', 'string', 'email:rfc', 'max:254'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'billing_email.email' => 'Adj meg érvényes e-mail-címet.',
            'billing_email.max' => 'Az e-mail-cím legfeljebb 254 karakter lehet.',
            'revision.*' => 'Frissítsd a kapcsolattartó oldalát, majd próbáld újra.',
        ];
    }
}
