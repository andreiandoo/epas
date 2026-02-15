<?php

namespace App\Models\AdsCampaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsCampaignMetric extends Model
{
    protected $table = 'ads_campaign_metrics';

    protected $fillable = [
        'campaign_id',
        'platform_campaign_id',
        'date',
        'platform',
        'impressions',
        'reach',
        'clicks',
        'ctr',
        'frequency',
        'spend',
        'cpc',
        'cpm',
        'conversions',
        'conversion_rate',
        'cost_per_conversion',
        'revenue',
        'roas',
        'cac',
        'tickets_sold',
        'new_customers',
        'likes',
        'shares',
        'comments',
        'saves',
        'video_views',
        'video_view_rate',
        'video_views_25',
        'video_views_50',
        'video_views_75',
        'video_views_100',
        'quality_score',
        'relevance_score',
        'variant_label',
    ];

    protected $casts = [
        'date' => 'date',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
        'spend' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'cost_per_conversion' => 'decimal:4',
        'revenue' => 'decimal:2',
        'roas' => 'decimal:4',
        'cac' => 'decimal:4',
        'quality_score' => 'decimal:2',
        'relevance_score' => 'decimal:2',
        'video_view_rate' => 'decimal:4',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdsCampaign::class, 'campaign_id');
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(AdsPlatformCampaign::class, 'platform_campaign_id');
    }

    // Scopes for analytics queries
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeAggregated($query)
    {
        return $query->where('platform', 'aggregated');
    }

    public function scopeForVariant($query, string $variant)
    {
        return $query->where('variant_label', $variant);
    }

    /**
     * Calculate derived metrics from raw data
     */
    public static function calculateDerived(array $data): array
    {
        $impressions = $data['impressions'] ?? 0;
        $clicks = $data['clicks'] ?? 0;
        $spend = $data['spend'] ?? 0;
        $conversions = $data['conversions'] ?? 0;
        $revenue = $data['revenue'] ?? 0;

        return array_merge($data, [
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
            'cpc' => $clicks > 0 ? $spend / $clicks : 0,
            'cpm' => $impressions > 0 ? ($spend / $impressions) * 1000 : 0,
            'conversion_rate' => $clicks > 0 ? ($conversions / $clicks) * 100 : 0,
            'cost_per_conversion' => $conversions > 0 ? $spend / $conversions : 0,
            'roas' => $spend > 0 ? $revenue / $spend : 0,
            'cac' => $conversions > 0 ? $spend / $conversions : 0,
        ]);
    }
}
