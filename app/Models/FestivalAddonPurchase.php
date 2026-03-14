<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FestivalAddonPurchase extends Model
{
    protected $fillable = [
        'festival_addon_id',
        'tenant_id',
        'customer_id',
        'order_id',
        'festival_pass_purchase_id',
        'code',
        'quantity',
        'price_cents_paid',
        'currency',
        'status',
        'selected_options',
        'assigned_spot',
        'valid_from',
        'valid_until',
        'meta',
    ];

    protected $casts = [
        'quantity'         => 'integer',
        'price_cents_paid' => 'integer',
        'selected_options' => 'array',
        'valid_from'       => 'datetime',
        'valid_until'      => 'datetime',
        'meta'             => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $purchase) {
            if (empty($purchase->code)) {
                $purchase->code = 'FA-' . strtoupper(Str::random(10));
            }
        });
    }

    public function festivalAddon(): BelongsTo
    {
        return $this->belongsTo(FestivalAddon::class);
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

    public function passPurchase(): BelongsTo
    {
        return $this->belongsTo(FestivalPassPurchase::class, 'festival_pass_purchase_id');
    }

    public function getPricePaidAttribute(): float
    {
        return $this->price_cents_paid / 100;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isValid(): bool
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

        return true;
    }
}
