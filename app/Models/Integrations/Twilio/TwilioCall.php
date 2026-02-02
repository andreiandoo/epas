<?php

namespace App\Models\Integrations\Twilio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioCall extends Model
{
    protected $fillable = [
        'connection_id', 'call_sid', 'direction', 'from_number', 'to_number',
        'status', 'duration', 'price', 'price_unit', 'twiml', 'correlation_ref',
        'started_at', 'ended_at', 'error_details',
    ];

    protected $casts = [
        'error_details' => 'array',
        'price' => 'decimal:4',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TwilioConnection::class, 'connection_id');
    }
}
