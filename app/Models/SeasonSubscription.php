<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonSubscription extends Model
{
    protected $fillable = [
        'season_id',
        'tenant_id',
        'customer_id',
        'order_id',
        'name',
        'subscription_type',
        'seat_label',
        'seat_uid',
        'section_id',
        'price_cents',
        'currency',
        'status',
        'events_included',
        'valid_from',
        'valid_until',
        'auto_renew',
        'subscriber_name',
        'subscriber_email',
        'subscriber_phone',
        'meta',
    ];

    protected $casts = [
        'events_included' => 'array',
        'meta' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'auto_renew' => 'boolean',
        'price_cents' => 'integer',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if this subscription is currently valid.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now()->toDateString();
        return $this->valid_from <= $now && $this->valid_until >= $now;
    }

    /**
     * Check if a specific event is included in this subscription.
     */
    public function includesEvent(int $eventId): bool
    {
        if (empty($this->events_included)) {
            // Full subscription = all season events
            return $this->subscription_type === 'full';
        }

        return in_array($eventId, $this->events_included);
    }

    /**
     * Check if this subscription has a reserved seat.
     */
    public function hasReservedSeat(): bool
    {
        return !empty($this->seat_uid) || !empty($this->seat_label);
    }

    /**
     * Get price in major currency units.
     */
    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
