<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateResaleListingRequest extends FormRequest
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
            'seller_customer_id' => 'required|exists:customers,id',
            'asking_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'asking_price.min' => 'Asking price cannot be negative.',
        ];
    }
}
