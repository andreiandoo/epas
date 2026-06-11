<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlexPass extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'total_entries',
        'price_cents',
        'currency',
        'status',
        'eligible_event_ids',
        'eligible_ticket_type_ids',
        'valid_from',
        'valid_until',
        'max_sales',
        'total_sold',
        'max_entries_per_event',
        'is_transferable',
        'is_refundable',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'total_entries'            => 'integer',
        'price_cents'              => 'integer',
        'eligible_event_ids'       => 'array',
        'eligible_ticket_type_ids' => 'array',
        'valid_from'               => 'datetime',
        'valid_until'              => 'datetime',
        'max_sales'                => 'integer',
        'total_sold'               => 'integer',
        'max_entries_per_event'    => 'integer',
        'is_transferable'          => 'boolean',
        'is_refundable'            => 'boolean',
        'meta'                     => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(FlexPassPurchase::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAvailable(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        if ($this->max_sales && $this->total_sold >= $this->max_sales) {
            return false;
        }

        return true;
    }

    public function isEventEligible(int $eventId): bool
    {
        if (empty($this->eligible_event_ids)) {
            return true;
        }

        return in_array($eventId, $this->eligible_event_ids);
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->where(fn ($q) => $q->whereNull('max_sales')->orWhereColumn('total_sold', '<', 'max_sales'));
    }
}
