<?php

namespace App\Models\Integrations\Square;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareWebhook extends Model
{
    protected $fillable = [
        'connection_id',
        'subscription_id',
        'signature_key',
        'event_types',
        'is_active',
        'last_received_at',
    ];

    protected $casts = [
        'event_types' => 'array',
        'is_active' => 'boolean',
        'last_received_at' => 'datetime',
    ];

    protected $hidden = ['signature_key'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SquareConnection::class, 'connection_id');
    }

    public function supportsEvent(string $eventType): bool
    {
        return in_array($eventType, $this->event_types ?? []);
    }
}
