<?php

namespace App\Http\Requests\Microservices;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'headers' => ['nullable', 'array'],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:60'],
            'retry_limit' => ['nullable', 'integer', 'min:0', 'max:5'],
            'verify_ssl' => ['nullable', 'boolean'],
        ];
    }
}
