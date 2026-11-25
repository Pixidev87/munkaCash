<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Validation\Rules\Exists;

class FilterInvoicesRequest extends FormRequest
{
    protected $errorBag = 'invoiceFilters';

    /** @return array<string, list<string|EnumRule|Exists>> */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'bail', 'nullable', 'integer', 'min:1',
                Rule::exists('customers', 'id')->where('organization_id', (string) $this->route('organization')),
            ],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(InvoiceSource::class)],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'disputed' => ['nullable', 'in:1'],
            'awaiting_balance' => ['nullable', 'in:1'],
            'billing_unconfirmed' => ['nullable', 'in:1'],
            'overdue' => ['nullable', 'in:1'],
            'sort' => ['nullable', 'in:due_asc,due_desc,outstanding_desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.*' => 'Válassz ügyfelet a cég ügyfelei közül.',
            'invoice_number.*' => 'A számlaszám legfeljebb 100 karakter lehet.',
            'source.*' => 'Válassz érvényes számlaforrást.',
            'status.*' => 'Válassz érvényes fizetési állapotot.',
            'awaiting_balance.*' => 'Az egyenleg-ellenőrzési szűrő érvénytelen.',
            'billing_unconfirmed.*' => 'A pénzügyi cím szűrője érvénytelen.',
            'overdue.*' => 'A lejárt számlák szűrője érvénytelen.',
            'disputed.*' => 'A vitatott számlák szűrője érvénytelen.',
            'sort.*' => 'Válassz érvényes rendezési sorrendet.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
