<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class FilterInvoiceImportsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,completed'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.*' => 'Válassz érvényes importállapotot.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
