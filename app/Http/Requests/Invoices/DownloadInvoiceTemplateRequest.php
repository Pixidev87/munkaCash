<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DownloadInvoiceTemplateRequest extends FormRequest
{
    /** @return array<string, list<string|Exists>> */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'bail', 'nullable', 'integer', 'min:1',
                Rule::exists('customers', 'id')->where('organization_id', (string) $this->route('organization')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['customer_id.*' => 'Válassz ügyfelet a cég ügyfelei közül.'];
    }
}
