<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerNameRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->errorBagName();
    }

    public function errorBagName(): string
    {
        return 'customer-name-'.$this->route('customer');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'previous_name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'display_name.*' => 'Adj meg egy legfeljebb 255 karakteres ügyfélnevet.',
            'previous_name.*' => 'Frissítsd az ügyféllistát, majd próbáld újra.',
        ];
    }
}
