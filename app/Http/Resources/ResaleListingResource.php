<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResaleListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'ticket_id' => $this->ticket_id,
            'seller_customer_id' => $this->seller_customer_id,
            'original_price' => $this->original_price,
            'asking_price' => $this->asking_price,
            'platform_fee' => $this->platform_fee,
            'status' => $this->status,
            'listed_at' => $this->listed_at?->toIso8601String(),
            'sold_at' => $this->sold_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'ticket' => $this->whenLoaded('ticket'),
            'seller' => $this->whenLoaded('seller'),
        ];
    }
}
