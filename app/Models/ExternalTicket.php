<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalTicket extends Model
{
    protected $fillable = [
        'event_id',
        'marketplace_client_id',
        'import_batch_id',
        'source_name',
        'barcode',
        'attendee_first_name',
        'attendee_last_name',
        'attendee_email',
        'ticket_type_name',
        'original_id',
        'status',
        'checked_in_at',
        'checked_in_by',
        'meta',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function getAttendeeNameAttribute(): string
    {
        return trim(($this->attendee_first_name ?? '') . ' ' . ($this->attendee_last_name ?? '')) ?: '-';
    }
}
