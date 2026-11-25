<?php

namespace App\Http\Requests\Invoices;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceBalanceRequest extends FormRequest
{
    private ?Invoice $selectedInvoice = null;

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->errorBagName();
    }

    public function errorBagName(): string
    {
        return 'balance-'.$this->route('invoice');
    }

    public function invoice(): Invoice
    {
        return $this->selectedInvoice ??= $this->user()
            ->findActiveOrganization((string) $this->route('organization'))
            ->invoices()
            ->findOrFail((string) $this->route('invoice'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'outstanding_amount' => ['required', 'integer', 'min:0', 'max:'.$this->invoice()->gross_amount],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'outstanding_amount.*' => 'Adj meg 0 és a bruttó összeg közötti egész forintértéket.',
            'revision.*' => 'Frissítsd a számlalistát, majd próbáld újra.',
        ];
    }
}
