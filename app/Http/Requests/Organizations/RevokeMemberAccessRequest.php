<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class RevokeMemberAccessRequest extends FormRequest
{
    private ?Organization $company = null;

    public function company(): Organization
    {
        return $this->company ??= $this->user()->findActiveOrganization((string) $this->route('organization'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->company());
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = 'member-'.$this->route('member');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Erősítsd meg a munkatárs hozzáférésének visszavonását.'];
    }
}
