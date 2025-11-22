<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessDoorSaleRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.ticket_type_id' => 'required|exists:ticket_types,id',
            'items.*.quantity' => 'required|integer|min:1|max:10',
            'payment_method' => 'required|in:card_tap,apple_pay,google_pay',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'At least one ticket type must be selected.',
            'items.*.quantity.max' => 'Maximum 10 tickets per item.',
            'payment_method.in' => 'Invalid payment method.',
        ];
    }
}
