<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmInvoiceImportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['preview_token' => ['nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['preview_token.*' => 'Nyisd meg újra az import előnézetét.'];
    }
}
