<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    protected $errorBag = 'customer';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'origin' => ['nullable', 'in:directory'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Add meg az ügyfél nevét.',
            'display_name.max' => 'Az ügyfél neve legfeljebb 255 karakter lehet.',
            'origin.in' => 'Indítsd újra az ügyfél rögzítését.',
        ];
    }
}
