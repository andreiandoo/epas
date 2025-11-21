<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupBookingRequest extends FormRequest
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
            'organizer_customer_id' => 'required|exists:customers,id',
            'group_name' => 'required|string|max:255',
            'total_tickets' => 'required|integer|min:2|max:500',
            'ticket_price' => 'required|numeric|min:0',
            'payment_type' => 'required|in:full,split,invoice',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'total_tickets.min' => 'Group booking requires at least 2 tickets.',
            'payment_type.in' => 'Payment type must be full, split, or invoice.',
        ];
    }
}
