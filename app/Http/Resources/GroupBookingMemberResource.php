<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupBookingMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_booking_id' => $this->group_booking_id,
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'email' => $this->email,
            'ticket_count' => $this->ticket_count,
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'payment_status' => $this->payment_status,
            'payment_link' => $this->when(
                $this->payment_status === 'pending',
                $this->payment_link
            ),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
