<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationAccessAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class FilterAccessLogRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function criteria(): array
    {
        return $this->safe()->only(['action', 'from', 'to', 'actor_id']);
    }

    /** @return array<string, list<string|EnumRule>> */
    public function rules(): array
    {
        $dateRules = ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];

        return [
            'from' => $dateRules,
            'to' => $this->filled('from') ? [...$dateRules, 'after_or_equal:from'] : $dateRules,
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', Rule::enum(OrganizationAccessAction::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.*' => 'Adj meg érvényes kezdőnapot.',
            'to.*' => 'Adj meg érvényes zárónapot, amely nem előzi meg a kezdőnapot.',
            'actor_id.*' => 'Válassz érvényes végrehajtót.',
            'action.*' => 'Válassz érvényes művelettípust.',
            'page.*' => 'Az oldalszám legyen pozitív egész szám.',
        ];
    }
}
