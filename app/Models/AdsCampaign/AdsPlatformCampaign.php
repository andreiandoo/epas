<?php

namespace App\Models\AdsCampaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdsPlatformCampaign extends Model
{
    protected $table = 'ads_platform_campaigns';

    protected $fillable = [
        'campaign_id',
        'platform',
        'platform_campaign_id',
        'platform_adset_id',
        'platform_ad_id',
        'platform_creative_id',
        'platform_objective',
        'bid_strategy',
        'bid_amount',
        'budget_allocated',
        'daily_budget',
        'variant_label',
        'status',
        'error_message',
        'api_response',
        'impressions',
        'reach',
        'clicks',
        'ctr',
        'cpc',
        'cpm',
        'spend',
        'conversions',
        'conversion_rate',
        'cost_per_conversion',
        'revenue',
        'roas',
        'frequency',
        'video_views',
        'video_view_rate',
        'last_synced_at',
        'launched_at',
    ];

    protected $casts = [
        'api_response' => 'array',
        'budget_allocated' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'bid_amount' => 'decimal:2',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
        'spend' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'cost_per_conversion' => 'decimal:4',
        'revenue' => 'decimal:2',
        'roas' => 'decimal:4',
        'video_view_rate' => 'decimal:4',
        'last_synced_at' => 'datetime',
        'launched_at' => 'datetime',
    ];

    const PLATFORM_FACEBOOK = 'facebook';
    const PLATFORM_INSTAGRAM = 'instagram';
    const PLATFORM_GOOGLE = 'google';
    const PLATFORM_TIKTOK = 'tiktok';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdsCampaign::class, 'campaign_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(AdsCampaignMetric::class, 'platform_campaign_id');
    }

    public function optimizationLogs(): HasMany
    {
        return $this->hasMany(AdsOptimizationLog::class, 'platform_campaign_id');
    }

    // Status helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCreated(): bool
    {
        return !empty($this->platform_campaign_id);
    }

    public function needsSync(): bool
    {
        if (!$this->isActive()) return false;
        if (!$this->last_synced_at) return true;
        return $this->last_synced_at->diffInMinutes(now()) > 60;
    }

    /**
     * Update metrics from platform API response
     */
    public function syncMetrics(array $data): void
    {
        $this->update(array_merge($data, [
            'last_synced_at' => now(),
            'ctr' => ($data['impressions'] ?? $this->impressions) > 0
                ? (($data['clicks'] ?? $this->clicks) / ($data['impressions'] ?? $this->impressions)) * 100
                : 0,
            'cpc' => ($data['clicks'] ?? $this->clicks) > 0
                ? ($data['spend'] ?? $this->spend) / ($data['clicks'] ?? $this->clicks)
                : 0,
            'cpm' => ($data['impressions'] ?? $this->impressions) > 0
                ? (($data['spend'] ?? $this->spend) / ($data['impressions'] ?? $this->impressions)) * 1000
                : 0,
            'conversion_rate' => ($data['clicks'] ?? $this->clicks) > 0
                ? (($data['conversions'] ?? $this->conversions) / ($data['clicks'] ?? $this->clicks)) * 100
                : 0,
            'cost_per_conversion' => ($data['conversions'] ?? $this->conversions) > 0
                ? ($data['spend'] ?? $this->spend) / ($data['conversions'] ?? $this->conversions)
                : 0,
            'roas' => ($data['spend'] ?? $this->spend) > 0
                ? ($data['revenue'] ?? $this->revenue) / ($data['spend'] ?? $this->spend)
                : 0,
        ]));
    }

    /**
     * Get platform display name
     */
    public function getPlatformLabelAttribute(): string
    {
        return match ($this->platform) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'google' => 'Google Ads',
            'tiktok' => 'TikTok',
            default => ucfirst($this->platform),
        };
    }

    /**
     * Get platform icon
     */
    public function getPlatformIconAttribute(): string
    {
        return match ($this->platform) {
            'facebook' => 'heroicon-o-chat-bubble-left',
            'instagram' => 'heroicon-o-camera',
            'google' => 'heroicon-o-magnifying-glass',
            'tiktok' => 'heroicon-o-play',
            default => 'heroicon-o-globe-alt',
        };
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subHour());
            });
    }
}
