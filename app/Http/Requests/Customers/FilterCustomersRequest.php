<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class FilterCustomersRequest extends FormRequest
{
    protected $errorBag = 'customerFilters';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'billing_contact' => ['nullable', 'in:missing,unconfirmed,confirmed'],
            'blocked' => ['nullable', 'in:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.*' => 'A keresés legfeljebb 255 karakter lehet.',
            'billing_contact.*' => 'Válassz érvényes pénzügyicím-állapotot.',
            'blocked.*' => 'Az emlékeztetőtiltás szűrője érvénytelen.',
        ];
    }
}
