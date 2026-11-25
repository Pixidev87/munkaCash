<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceDeadlineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->errorBagName();
    }

    public function errorBagName(): string
    {
        return 'deadline-'.$this->route('invoice');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'due_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'revision' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'due_date.*' => 'Adj meg érvényes fizetési határidőt.',
            'revision.*' => 'Frissítsd a számlalistát, majd próbáld újra.',
        ];
    }
}
