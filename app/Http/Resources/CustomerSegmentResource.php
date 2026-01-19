<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSegmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'conditions' => $this->conditions,
            'is_dynamic' => $this->is_dynamic,
            'member_count' => $this->member_count,
            'last_calculated_at' => $this->last_calculated_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'campaigns_count' => $this->whenCounted('campaigns'),
        ];
    }
}
