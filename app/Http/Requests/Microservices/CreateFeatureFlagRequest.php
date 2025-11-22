<?php

namespace App\Http\Requests\Microservices;

use Illuminate\Foundation\Http\FormRequest;

class CreateFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'unique:feature_flags,key'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
            'rollout_strategy' => ['nullable', 'in:all,percentage,whitelist,custom'],
            'rollout_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'whitelist' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
        ];
    }
}
