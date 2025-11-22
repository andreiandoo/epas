<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCoupon extends Model
{
    protected $fillable = [
        'affiliate_id',
        'coupon_code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * Scope for active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
