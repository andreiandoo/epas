<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAudienceSegment extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'criteria',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    public const SLUG_RECENT_BUYER = 'recent_buyer_30d';
    public const SLUG_HIGH_LTV = 'high_ltv';
    public const SLUG_REPEAT_BUYER = 'repeat_buyer';
    public const SLUG_ABANDONED_CART = 'abandoned_cart_14d';
    public const SLUG_DORMANT = 'dormant_180d';

    public function subscriptions()
    {
        return $this->hasMany(MarketplaceOrganizerAudienceSubscription::class, 'audience_segment_id');
    }
}
