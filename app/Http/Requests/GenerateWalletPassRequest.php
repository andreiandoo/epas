<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateWalletPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'ticket_id' => 'required|exists:tickets,id',
            'platform' => 'required|in:apple,google',
        ];
    }

    public function messages(): array
    {
        return [
            'platform.in' => 'Platform must be either "apple" or "google".',
            'ticket_id.exists' => 'The specified ticket does not exist.',
        ];
    }
}
