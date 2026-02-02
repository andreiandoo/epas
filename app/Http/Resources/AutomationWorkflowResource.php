<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationWorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type,
            'trigger_conditions' => $this->trigger_conditions,
            'is_active' => $this->is_active,
            'enrolled_count' => $this->enrolled_count,
            'completed_count' => $this->completed_count,
            'created_at' => $this->created_at->toIso8601String(),
            'steps' => AutomationStepResource::collection($this->whenLoaded('steps')),
            'steps_count' => $this->whenCounted('steps'),
        ];
    }
}
