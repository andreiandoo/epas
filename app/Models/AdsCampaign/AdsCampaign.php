<?php

namespace App\Models\AdsCampaign;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdsCampaign extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'ads_campaigns';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'service_request_id',
        'marketplace_client_id',
        'name',
        'description',
        'objective',
        'total_budget',
        'daily_budget',
        'spent_budget',
        'currency',
        'budget_allocation',
        'start_date',
        'end_date',
        'target_platforms',
        'ab_testing_enabled',
        'ab_test_variable',
        'ab_test_split_percentage',
        'ab_test_winner_date',
        'ab_test_winner',
        'ab_test_metric',
        'auto_optimize',
        'optimization_rules',
        'optimization_goal',
        'last_optimized_at',
        'retargeting_enabled',
        'retargeting_config',
        'tracking_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'status',
        'status_notes',
        'total_impressions',
        'total_clicks',
        'total_conversions',
        'total_spend',
        'total_revenue',
        'avg_ctr',
        'avg_cpc',
        'avg_cpm',
        'roas',
        'cac',
        'created_by',
    ];

    protected $casts = [
        'target_platforms' => 'array',
        'optimization_rules' => 'array',
        'retargeting_config' => 'array',
        'total_budget' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'spent_budget' => 'decimal:2',
        'total_spend' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'avg_ctr' => 'decimal:4',
        'avg_cpc' => 'decimal:4',
        'avg_cpm' => 'decimal:4',
        'roas' => 'decimal:4',
        'cac' => 'decimal:4',
        'ab_testing_enabled' => 'boolean',
        'auto_optimize' => 'boolean',
        'retargeting_enabled' => 'boolean',
        'ab_test_split_percentage' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'ab_test_winner_date' => 'datetime',
        'last_optimized_at' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_LAUNCHING = 'launching';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_OPTIMIZING = 'optimizing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_ARCHIVED = 'archived';

    // Objective constants
    const OBJECTIVE_CONVERSIONS = 'conversions';
    const OBJECTIVE_TRAFFIC = 'traffic';
    const OBJECTIVE_AWARENESS = 'awareness';
    const OBJECTIVE_ENGAGEMENT = 'engagement';
    const OBJECTIVE_LEADS = 'leads';

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(AdsServiceRequest::class, 'service_request_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdsCampaignCreative::class, 'campaign_id')->orderBy('sort_order');
    }

    public function targeting(): HasMany
    {
        return $this->hasMany(AdsCampaignTargeting::class, 'campaign_id');
    }

    public function platformCampaigns(): HasMany
    {
        return $this->hasMany(AdsPlatformCampaign::class, 'campaign_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(AdsCampaignMetric::class, 'campaign_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AdsCampaignReport::class, 'campaign_id');
    }

    public function optimizationLogs(): HasMany
    {
        return $this->hasMany(AdsOptimizationLog::class, 'campaign_id');
    }

    // ==========================================
    // STATUS HELPERS
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canLaunch(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED])
            && $this->creatives()->where('status', 'approved')->exists()
            && $this->targeting()->exists();
    }

    public function canPause(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_OPTIMIZING]);
    }

    public function canResume(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    // ==========================================
    // BUDGET HELPERS
    // ==========================================

    public function getRemainingBudgetAttribute(): float
    {
        return max(0, (float) $this->total_budget - (float) $this->spent_budget);
    }

    public function getBudgetUtilizationAttribute(): float
    {
        if ((float) $this->total_budget <= 0) return 0;
        return round(((float) $this->spent_budget / (float) $this->total_budget) * 100, 2);
    }

    public function isBudgetExhausted(): bool
    {
        return $this->remaining_budget <= 0;
    }

    public function addSpend(float $amount): void
    {
        $this->increment('spent_budget', $amount);
        $this->increment('total_spend', $amount);
    }

    // ==========================================
    // PERFORMANCE HELPERS
    // ==========================================

    public function recalculateAggregates(): void
    {
        $platforms = $this->platformCampaigns;

        $this->update([
            'total_impressions' => $platforms->sum('impressions'),
            'total_clicks' => $platforms->sum('clicks'),
            'total_conversions' => $platforms->sum('conversions'),
            'total_spend' => $platforms->sum('spend'),
            'total_revenue' => $platforms->sum('revenue'),
            'avg_ctr' => $platforms->sum('impressions') > 0
                ? ($platforms->sum('clicks') / $platforms->sum('impressions')) * 100
                : 0,
            'avg_cpc' => $platforms->sum('clicks') > 0
                ? $platforms->sum('spend') / $platforms->sum('clicks')
                : 0,
            'avg_cpm' => $platforms->sum('impressions') > 0
                ? ($platforms->sum('spend') / $platforms->sum('impressions')) * 1000
                : 0,
            'roas' => $platforms->sum('spend') > 0
                ? $platforms->sum('revenue') / $platforms->sum('spend')
                : 0,
            'cac' => $platforms->sum('conversions') > 0
                ? $platforms->sum('spend') / $platforms->sum('conversions')
                : 0,
        ]);
    }

    public function getPerformanceSummaryAttribute(): array
    {
        return [
            'impressions' => $this->total_impressions,
            'clicks' => $this->total_clicks,
            'conversions' => $this->total_conversions,
            'spend' => $this->total_spend,
            'revenue' => $this->total_revenue,
            'ctr' => $this->avg_ctr,
            'cpc' => $this->avg_cpc,
            'cpm' => $this->avg_cpm,
            'roas' => $this->roas,
            'cac' => $this->cac,
            'budget_remaining' => $this->remaining_budget,
            'budget_utilization' => $this->budget_utilization,
        ];
    }

    // ==========================================
    // A/B TESTING
    // ==========================================

    public function getVariantAMetrics(): array
    {
        return $this->getVariantMetrics('A');
    }

    public function getVariantBMetrics(): array
    {
        return $this->getVariantMetrics('B');
    }

    protected function getVariantMetrics(string $variant): array
    {
        $metrics = $this->metrics()
            ->where('variant_label', $variant)
            ->selectRaw('
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(spend) as spend,
                SUM(revenue) as revenue
            ')
            ->first();

        if (!$metrics) {
            return ['impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'spend' => 0, 'revenue' => 0, 'ctr' => 0, 'roas' => 0];
        }

        return [
            'impressions' => (int) $metrics->impressions,
            'clicks' => (int) $metrics->clicks,
            'conversions' => (int) $metrics->conversions,
            'spend' => (float) $metrics->spend,
            'revenue' => (float) $metrics->revenue,
            'ctr' => $metrics->impressions > 0 ? ($metrics->clicks / $metrics->impressions) * 100 : 0,
            'roas' => $metrics->spend > 0 ? $metrics->revenue / $metrics->spend : 0,
        ];
    }

    public function determineAbTestWinner(): ?string
    {
        if (!$this->ab_testing_enabled) return null;

        $a = $this->getVariantAMetrics();
        $b = $this->getVariantBMetrics();

        $metric = $this->ab_test_metric ?? 'conversions';

        $aValue = match ($metric) {
            'ctr' => $a['ctr'],
            'conversions' => $a['conversions'],
            'cpc' => $a['clicks'] > 0 ? $a['spend'] / $a['clicks'] : PHP_INT_MAX,
            'roas' => $a['roas'],
            default => $a['conversions'],
        };

        $bValue = match ($metric) {
            'ctr' => $b['ctr'],
            'conversions' => $b['conversions'],
            'cpc' => $b['clicks'] > 0 ? $b['spend'] / $b['clicks'] : PHP_INT_MAX,
            'roas' => $b['roas'],
            default => $b['conversions'],
        };

        // For CPC, lower is better
        if ($metric === 'cpc') {
            return $aValue <= $bValue ? 'A' : 'B';
        }

        return $aValue >= $bValue ? 'A' : 'B';
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRunning($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_OPTIMIZING]);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeOnPlatform($query, string $platform)
    {
        return $query->whereJsonContains('target_platforms', $platform);
    }

    // ==========================================
    // UTM HELPERS
    // ==========================================

    public function generateTrackingUrl(string $baseUrl): string
    {
        $params = array_filter([
            'utm_source' => $this->utm_source ?? 'tixello_ads',
            'utm_medium' => $this->utm_medium ?? 'paid_social',
            'utm_campaign' => $this->utm_campaign ?? \Illuminate\Support\Str::slug($this->name),
            'utm_content' => $this->utm_content,
        ]);

        return $baseUrl . '?' . http_build_query($params);
    }

    // ==========================================
    // ACTIVITY LOG
    // ==========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_budget', 'daily_budget', 'auto_optimize', 'ab_testing_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Ads campaign {$eventName}")
            ->useLogName('ads_campaigns');
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }
}
