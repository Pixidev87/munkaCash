<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class DiscardInvoiceImportRequest extends FormRequest
{
    protected $errorBag = 'discardImport';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Erősítsd meg a függő import elvetését.'];
    }
}
