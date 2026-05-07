<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ArtistBookingRequest extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_NEGOTIATING = 'negotiating';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public const ACTIVE_STATUSES = ['new', 'viewed', 'negotiating'];
    public const ARCHIVE_STATUSES = ['rejected', 'expired'];

    protected $fillable = [
        'artist_id',
        'guest_name', 'guest_email', 'guest_phone', 'guest_company', 'guest_company_type',
        'event_date', 'event_time', 'event_venue_name', 'event_city', 'event_country',
        'event_type', 'audience_size',
        'proposed_fee_ron', 'proposed_set_length_min', 'conditions', 'initial_message',
        'status', 'guest_token',
        'first_viewed_at', 'last_artist_response_at', 'accepted_at', 'rejected_at', 'expires_at',
        'final_terms', 'rejection_reason',
    ];

    protected $casts = [
        'event_date' => 'date',
        'conditions' => 'array',
        'final_terms' => 'array',
        'first_viewed_at' => 'datetime',
        'last_artist_response_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function generateGuestToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('guest_token', $token)->exists());
        return $token;
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ArtistBookingMessage::class, 'booking_request_id')->orderBy('created_at');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
