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

    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return $this->safe()->only([
            'invoice_number', 'status', 'disputed', 'overdue', 'source', 'customer_id',
            'awaiting_balance', 'billing_unconfirmed', 'sort', 'due_from', 'due_to',
        ]);
    }

    /** @return array<string, list<string|EnumRule|Exists>> */
    public function rules(): array
    {
        $dateRules = ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];

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
            'due_from' => $dateRules,
            'due_to' => $this->filled('due_from') ? [...$dateRules, 'after_or_equal:due_from'] : $dateRules,
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
            'due_from.*' => 'Adj meg érvényes kezdőnapot.',
            'due_to.*' => 'Adj meg érvényes zárónapot, amely nem előzi meg a kezdőnapot.',
            'sort.*' => 'Válassz érvényes rendezési sorrendet.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
