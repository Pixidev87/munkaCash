<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;

class CancelReminderTestRequest extends FormRequest
{
    protected $errorBag = 'deliveryCancellation';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['delivery_id' => ['required', 'uuid']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['delivery_id.*' => 'Frissítsd a levélelőnézetet, majd próbáld újra.'];
    }
}
