<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class RevokeBillingContactConfirmationRequest extends FormRequest
{
    protected $errorBag = 'revocation';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:0']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['revision.*' => 'Frissítsd a kapcsolattartó oldalát, majd próbáld újra.'];
    }
}
