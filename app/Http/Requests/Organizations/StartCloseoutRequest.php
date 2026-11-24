<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

class StartCloseoutRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Erősítsd meg a cég írásvédetté tételét.'];
    }
}
