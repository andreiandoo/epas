<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAdsCampaign extends Model
{
    protected $table = 'facebook_ads_campaigns';

    protected $fillable = [
        'ads_account_id',
        'fb_campaign_id',
        'name',
        'objective',
        'status',
        'effective_status',
        'daily_budget',
        'lifetime_budget',
        'budget_currency',
        'start_time',
        'stop_time',
        'metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FacebookAdsAccount::class, 'ads_account_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(FacebookAdsInsight::class, 'campaign_id');
    }
}
