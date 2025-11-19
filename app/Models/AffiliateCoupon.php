<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCoupon extends Model
{
    protected $fillable = [
        'affiliate_id',
        'coupon_code',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
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
