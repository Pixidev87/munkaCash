<?php

namespace App\Http\Requests\Invoices;

use App\Services\InvoiceCsvParser;
use Illuminate\Foundation\Http\FormRequest;

class UploadInvoiceCsvRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['csv' => ['required', 'file', 'extensions:csv', 'max:'.InvoiceCsvParser::MAX_BYTES_KIB]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['csv.*' => 'Legfeljebb '.InvoiceCsvParser::MAX_BYTES_KIB.' KiB méretű .csv fájlt tölts fel.'];
    }
}
