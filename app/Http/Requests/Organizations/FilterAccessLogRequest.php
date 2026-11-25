<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationAccessAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class FilterAccessLogRequest extends FormRequest
{
    /** @return array<string, list<string|EnumRule>> */
    public function rules(): array
    {
        return [
            'action' => ['nullable', Rule::enum(OrganizationAccessAction::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'action.*' => 'Válassz érvényes művelettípust.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
