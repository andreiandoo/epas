<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookAdsInsight extends Model
{
    protected $table = 'facebook_ads_insights';

    protected $fillable = [
        'ads_account_id',
        'campaign_id',
        'fb_campaign_id',
        'date',
        'impressions',
        'reach',
        'clicks',
        'spend',
        'ctr',
        'cpc',
        'cpm',
        'conversions',
        'conversion_value',
        'actions',
        'action_values',
        'currency',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'reach' => 'integer',
        'clicks' => 'integer',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
        'conversions' => 'integer',
        'conversion_value' => 'decimal:2',
        'actions' => 'array',
        'action_values' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FacebookAdsAccount::class, 'ads_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FacebookAdsCampaign::class, 'campaign_id');
    }
}
