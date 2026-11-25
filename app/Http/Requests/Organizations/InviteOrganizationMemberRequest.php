<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

class InviteOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->user()->findActiveOrganization((string) $this->route('organization'));

        return $this->user()->can('manageMembers', $company);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:255']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.*' => 'Adj meg egy érvényes e-mail-címet.'];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }
}
