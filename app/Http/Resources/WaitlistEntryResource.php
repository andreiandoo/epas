<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_id' => $this->event_id,
            'customer_id' => $this->customer_id,
            'ticket_type_id' => $this->ticket_type_id,
            'position' => $this->position,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'priority' => $this->priority,
            'notified_at' => $this->notified_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'customer' => $this->whenLoaded('customer'),
            'event' => $this->whenLoaded('event'),
        ];
    }
}
