<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatHold extends Model
{
    protected $fillable = [
        'event_seating_id',
        'seat_uid',
        'session_uid',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // Only has created_at

    /**
     * Relationships
     */
    public function eventSeating(): BelongsTo
    {
        return $this->belongsTo(EventSeatingLayout::class, 'event_seating_id');
    }

    /**
     * Scopes
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>=', now());
    }

    public function scopeForSession($query, string $sessionUid)
    {
        return $query->where('session_uid', $sessionUid);
    }

    /**
     * Check if hold is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if hold is still active
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get remaining time in seconds
     */
    public function getRemainingSeconds(): int
    {
        return max(0, $this->expires_at->diffInSeconds(now(), false));
    }
}
