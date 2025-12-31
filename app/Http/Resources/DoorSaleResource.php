<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoorSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'customer_email' => $this->customer_email,
            'customer_name' => $this->customer_name,
            'subtotal' => $this->subtotal,
            'platform_fee' => $this->platform_fee,
            'payment_processing_fee' => $this->payment_processing_fee,
            'total' => $this->total,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'refunded_amount' => $this->refunded_amount,
            'total_tickets' => $this->getTotalTickets(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => DoorSaleItemResource::collection($this->whenLoaded('items')),
            'event' => $this->whenLoaded('event'),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }
}
