<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class ResetInvoiceBalanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->errorBagName();
    }

    public function errorBagName(): string
    {
        return 'reset-balance-'.$this->route('invoice');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Erősítsd meg, hogy a számla egyenlege új ellenőrzést igényel.',
            'revision.*' => 'Frissítsd a számlalistát, majd próbáld újra.',
        ];
    }

    public function revision(): int
    {
        return (int) $this->validated('revision');
    }
}
