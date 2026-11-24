<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRestrictionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['do_not_contact' => ['required', 'boolean']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['do_not_contact.*' => 'Add meg, hogy az ügyfél emlékeztetői tiltva legyenek-e.'];
    }

    public function isBlocked(): bool
    {
        return (bool) $this->validated('do_not_contact');
    }
}
