<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistBookingMessage extends Model
{
    public const TYPE_MESSAGE = 'message';
    public const TYPE_COUNTER = 'counter';
    public const TYPE_ACCEPT = 'accept';
    public const TYPE_REJECT = 'reject';

    public const SENDER_ARTIST = 'artist';
    public const SENDER_GUEST = 'guest';

    protected $fillable = [
        'booking_request_id',
        'sender_type',
        'type',
        'body',
        'counter_terms',
        'read_at',
    ];

    protected $casts = [
        'counter_terms' => 'array',
        'read_at' => 'datetime',
    ];

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(ArtistBookingRequest::class, 'booking_request_id');
    }
}
