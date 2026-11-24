<?php

namespace App\Http\Requests\Reminders;

use App\Services\ReminderTestMailer;
use Illuminate\Foundation\Http\FormRequest;

class QueueReminderTestRequest extends FormRequest
{
    public function authorize(ReminderTestMailer $mailer): bool
    {
        abort_unless($mailer->enabled(), 404);

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['approval_id' => ['required', 'integer', 'min:1']];
    }

    public function approvalId(): int
    {
        return (int) $this->validated('approval_id');
    }
}
