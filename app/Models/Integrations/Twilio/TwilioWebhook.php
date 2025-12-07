<?php

namespace App\Models\Integrations\Twilio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioWebhook extends Model
{
    protected $fillable = [
        'connection_id', 'event_type', 'endpoint_url', 'is_active', 'last_triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TwilioConnection::class, 'connection_id');
    }
}
