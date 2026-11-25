<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class FilterInvoiceImportsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $dates = ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];

        return [
            'uploaded_from' => $dates,
            'uploaded_to' => $this->filled('uploaded_from') ? [...$dates, 'after_or_equal:uploaded_from'] : $dates,
            'status' => ['nullable', 'in:pending,completed'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uploaded_from.*' => 'Adj meg érvényes kezdőnapot.',
            'uploaded_to.*' => 'Adj meg érvényes zárónapot, amely nem előzi meg a kezdőnapot.',
            'status.*' => 'Válassz érvényes importállapotot.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
