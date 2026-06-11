<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_id' => $this->event_id,
            'organizer_customer_id' => $this->organizer_customer_id,
            'group_name' => $this->group_name,
            'total_tickets' => $this->total_tickets,
            'ticket_price' => $this->ticket_price,
            'discount_percentage' => $this->discount_percentage,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'payment_type' => $this->payment_type,
            'status' => $this->status,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'members' => GroupBookingMemberResource::collection($this->whenLoaded('members')),
            'organizer' => $this->whenLoaded('organizer'),
            'event' => $this->whenLoaded('event'),
            'payment_progress' => $this->when(
                $this->payment_type === 'split',
                fn() => round(($this->paid_amount / $this->total_amount) * 100, 1)
            ),
        ];
    }
}
