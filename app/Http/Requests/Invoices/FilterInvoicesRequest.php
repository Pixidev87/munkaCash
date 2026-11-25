<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class FilterInvoicesRequest extends FormRequest
{
    protected $errorBag = 'invoiceFilters';

    /** @return array<string, list<string|EnumRule>> */
    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'disputed' => ['nullable', 'in:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'invoice_number.*' => 'A számlaszám legfeljebb 100 karakter lehet.',
            'status.*' => 'Válassz érvényes fizetési állapotot.',
            'disputed.*' => 'A vitatott számlák szűrője érvénytelen.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
