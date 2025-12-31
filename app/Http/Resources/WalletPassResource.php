<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletPassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'ticket_id' => $this->ticket_id,
            'platform' => $this->platform,
            'pass_identifier' => $this->pass_identifier,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'download_url' => $this->when($this->status === 'generated', $this->download_url),
            'installed_at' => $this->installed_at?->toIso8601String(),
            'last_updated_at' => $this->last_updated_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'ticket' => $this->whenLoaded('ticket'),
        ];
    }
}
