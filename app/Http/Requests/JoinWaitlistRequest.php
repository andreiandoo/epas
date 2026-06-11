<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'event_id' => 'required|exists:events,id',
            'customer_id' => 'required|exists:customers,id',
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'quantity' => 'required|integer|min:1|max:10',
            'priority' => 'nullable|in:normal,vip',
        ];
    }
}
