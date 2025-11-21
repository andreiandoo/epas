<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'order' => $this->order,
            'type' => $this->type,
            'config' => $this->config,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
