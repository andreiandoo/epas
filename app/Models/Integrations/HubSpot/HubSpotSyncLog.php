<?php

namespace App\Models\Integrations\HubSpot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubSpotSyncLog extends Model
{
    protected $table = 'hubspot_sync_logs';

    protected $fillable = [
        'connection_id', 'object_type', 'operation', 'hubspot_id', 'local_id',
        'direction', 'status', 'payload', 'response', 'correlation_ref',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(HubSpotConnection::class, 'connection_id');
    }
}
