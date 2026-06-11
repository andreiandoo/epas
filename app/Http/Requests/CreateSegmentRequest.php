<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'required|array|min:1',
            'conditions.*.type' => 'required|string|in:total_spent,orders_count,last_order,tag',
            'conditions.*.operator' => 'required|string|in:=,!=,>,<,>=,<=',
            'conditions.*.value' => 'required',
            'is_dynamic' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'conditions.min' => 'At least one condition is required.',
        ];
    }
}
