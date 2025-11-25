<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class TicketType extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'sku',
        'price_cents',
        'currency',
        'quota_total',
        'quota_sold',
        'status',
        'sales_start_at',
        'sales_end_at',
        'bulk_discounts',
        'meta',
    ];

    protected $casts = [
        'meta'           => 'array',
        'bulk_discounts' => 'array',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
    ];

    protected $appends = ['price', 'price_max', 'capacity', 'sale_starts_at', 'sale_ends_at', 'is_active'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // Accessor/Mutator for price_max (maps to price_cents)
    protected function priceMax(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price_cents ? $this->price_cents / 100 : 0,
            set: fn ($value) => ['price_cents' => $value ? (int)($value * 100) : 0]
        );
    }

    // Accessor/Mutator for price (read-only computed from price_cents - sale price placeholder)
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price_cents ? $this->price_cents / 100 : 0,
            set: fn ($value) => [] // Ignore sale price for now, use price_cents
        );
    }

    // Accessor/Mutator for capacity (maps to quota_total)
    protected function capacity(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quota_total,
            set: fn ($value) => ['quota_total' => $value ?? 0]
        );
    }

    // Accessor/Mutator for sale_starts_at (maps to sales_start_at)
    protected function saleStartsAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->sales_start_at,
            set: fn ($value) => ['sales_start_at' => $value]
        );
    }

    // Accessor/Mutator for sale_ends_at (maps to sales_end_at)
    protected function saleEndsAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->sales_end_at,
            set: fn ($value) => ['sales_end_at' => $value]
        );
    }

    // Accessor/Mutator for is_active (maps to status)
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'active',
            set: fn ($value) => ['status' => $value ? 'active' : 'hidden']
        );
    }
}
