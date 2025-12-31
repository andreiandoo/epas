<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsWidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboard_id,
            'type' => $this->type,
            'title' => $this->title,
            'data_source' => $this->data_source,
            'config' => $this->config,
            'position' => $this->position,
            'refresh_interval' => $this->refresh_interval,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
