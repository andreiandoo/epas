<?php

namespace App\Models\Integrations\Twilio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioMessage extends Model
{
    protected $fillable = [
        'connection_id', 'message_sid', 'channel', 'direction', 'from_number',
        'to_number', 'body', 'media_urls', 'status', 'price', 'price_unit',
        'correlation_ref', 'sent_at', 'delivered_at', 'error_details',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'error_details' => 'array',
        'price' => 'decimal:4',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TwilioConnection::class, 'connection_id');
    }
}
