<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderSettingRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['enabled.*' => 'Add meg, hogy a céges emlékeztetők engedélyezettek-e.'];
    }

    public function enabled(): bool
    {
        return (bool) $this->validated('enabled');
    }
}
