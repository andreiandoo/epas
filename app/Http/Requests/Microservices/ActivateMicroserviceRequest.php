<?php

namespace App\Http\Requests\Microservices;

use Illuminate\Foundation\Http\FormRequest;

class ActivateMicroserviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'max:255'],
            'microservice_slug' => ['required', 'string', 'max:255', 'exists:microservices,slug'],
            'settings' => ['nullable', 'array'],
            'trial' => ['nullable', 'boolean'],
        ];
    }
}
