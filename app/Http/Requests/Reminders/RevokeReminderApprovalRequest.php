<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;

class RevokeReminderApprovalRequest extends FormRequest
{
    protected $errorBag = 'approvalRevocation';

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
