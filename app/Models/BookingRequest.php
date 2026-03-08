<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'artist_id',
        'tenant_artist_id',
        'agency_artist_id',
        'handled_by',
        'requester_name',
        'requester_email',
        'requester_phone',
        'requester_organization',
        'requester_country',
        'event_name',
        'event_date',
        'event_date_alt',
        'event_venue',
        'event_city',
        'event_country',
        'expected_capacity',
        'event_type',
        'offered_fee_cents',
        'fee_currency',
        'fee_includes_travel',
        'fee_includes_accommodation',
        'financial_notes',
        'set_duration_minutes',
        'backline_provided',
        'sound_engineer_provided',
        'technical_notes',
        'status',
        'priority',
        'decline_reason',
        'counter_fee_cents',
        'offer_notes',
        'offer_sent_at',
        'offer_expires_at',
        'confirmed_at',
        'source',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'event_date' => 'date',
        'event_date_alt' => 'date',
        'offer_sent_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'offered_fee_cents' => 'integer',
        'counter_fee_cents' => 'integer',
        'expected_capacity' => 'integer',
        'set_duration_minutes' => 'integer',
        'fee_includes_travel' => 'boolean',
        'fee_includes_accommodation' => 'boolean',
        'backline_provided' => 'boolean',
        'sound_engineer_provided' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function tenantArtist(): BelongsTo
    {
        return $this->belongsTo(TenantArtist::class);
    }

    public function agencyArtist(): BelongsTo
    {
        return $this->belongsTo(AgencyArtist::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Get offered fee in major currency units.
     */
    public function getOfferedFeeAttribute(): ?float
    {
        return $this->offered_fee_cents ? $this->offered_fee_cents / 100 : null;
    }

    /**
     * Get counter fee in major currency units.
     */
    public function getCounterFeeAttribute(): ?float
    {
        return $this->counter_fee_cents ? $this->counter_fee_cents / 100 : null;
    }

    /**
     * Send an offer back to the requester.
     */
    public function sendOffer(int $counterFeeCents, ?string $notes = null, ?\DateTimeInterface $expiresAt = null): bool
    {
        return $this->update([
            'status' => 'offer_sent',
            'counter_fee_cents' => $counterFeeCents,
            'offer_notes' => $notes,
            'offer_sent_at' => now(),
            'offer_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Confirm the booking.
     */
    public function confirm(): bool
    {
        return $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Decline the booking request.
     */
    public function decline(string $reason): bool
    {
        return $this->update([
            'status' => 'declined',
            'decline_reason' => $reason,
        ]);
    }

    /**
     * Check if the offer has expired.
     */
    public function isOfferExpired(): bool
    {
        return $this->offer_expires_at && $this->offer_expires_at->isPast();
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['new', 'reviewing', 'offer_sent', 'negotiating']);
    }

    public function scopeConfirmed($query)
    {
        return $query->whereIn('status', ['confirmed', 'contract_sent', 'contracted']);
    }

    public function scopeForArtist($query, int $artistId)
    {
        return $query->where('artist_id', $artistId);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }
}
