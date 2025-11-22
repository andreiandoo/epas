<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'segment_id' => $this->segment_id,
            'name' => $this->name,
            'subject' => $this->subject,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'stats' => [
                'total_recipients' => $this->total_recipients,
                'sent' => $this->sent_count,
                'opened' => $this->opened_count,
                'clicked' => $this->clicked_count,
                'open_rate' => $this->getOpenRate(),
                'click_rate' => $this->getClickRate(),
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'segment' => new CustomerSegmentResource($this->whenLoaded('segment')),
        ];
    }
}
