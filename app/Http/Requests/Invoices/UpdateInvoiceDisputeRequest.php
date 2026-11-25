<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceDisputeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->errorBagName();
    }

    public function errorBagName(): string
    {
        return 'dispute-'.$this->route('invoice');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'is_disputed' => ['required', 'boolean'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'is_disputed.*' => 'Add meg a számla vitatott állapotát.',
            'revision.*' => 'Frissítsd a számlalistát, majd próbáld újra.',
        ];
    }

    public function isDisputed(): bool
    {
        return (bool) $this->validated('is_disputed');
    }

    public function revision(): int
    {
        return (int) $this->validated('revision');
    }
}
