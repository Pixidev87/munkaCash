<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;

class ApproveReminderRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'fingerprint' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'previous_approval_id' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fingerprint.*' => 'Frissítsd a levélelőnézetet a jóváhagyáshoz.',
            'previous_approval_id.*' => 'A jóváhagyás állapota érvénytelen. Frissítsd a levélelőnézetet.',
        ];
    }
}
