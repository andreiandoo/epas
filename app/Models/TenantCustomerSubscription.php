<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomerSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'subscription_plan_id',
        'order_id',
        'name',
        'status',
        'shows_included',
        'shows_used',
        'tickets_included',
        'seat_uids',
        'seat_labels',
        'event_seating_id',
        'price_cents',
        'currency',
        'valid_from',
        'valid_until',
        'priority_access',
        'benefits',
        'redeemed_events',
        'meta',
    ];

    protected $casts = [
        'seat_uids'        => 'array',
        'seat_labels'      => 'array',
        'benefits'         => 'array',
        'redeemed_events'  => 'array',
        'meta'             => 'array',
        'priority_access'  => 'boolean',
        'shows_included'   => 'integer',
        'shows_used'       => 'integer',
        'tickets_included' => 'integer',
        'price_cents'      => 'integer',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantSubscriptionPlan::class, 'subscription_plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getShowsRemainingAttribute(): ?int
    {
        if ($this->shows_included === null) {
            return null;
        }
        return max(0, $this->shows_included - ($this->shows_used ?? 0));
    }

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        $today = now()->toDateString();
        if ($this->valid_from && $this->valid_from->toDateString() > $today) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->toDateString() < $today) {
            return false;
        }
        return true;
    }
}
