<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Platform\CoreCustomerEvent;

class EventMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'tenant_id',
        'created_by',
        'type',
        'title',
        'description',
        'start_date',
        'end_date',
        'budget',
        'currency',
        'targeting',
        'platform_campaign_id',
        'attribution_window_days',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'impressions',
        'clicks',
        'conversions',
        'attributed_revenue',
        'cac',
        'roi',
        'roas',
        'impact_metric',
        'baseline_value',
        'post_value',
        'is_active',
        'metrics_updated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'attributed_revenue' => 'decimal:2',
        'cac' => 'decimal:2',
        'roi' => 'decimal:2',
        'roas' => 'decimal:2',
        'baseline_value' => 'decimal:2',
        'post_value' => 'decimal:2',
        'is_active' => 'boolean',
        'metrics_updated_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'attribution_window_days' => 'integer',
    ];

    // Milestone types
    const TYPE_CAMPAIGN_FB = 'campaign_fb';
    const TYPE_CAMPAIGN_GOOGLE = 'campaign_google';
    const TYPE_CAMPAIGN_TIKTOK = 'campaign_tiktok';
    const TYPE_CAMPAIGN_INSTAGRAM = 'campaign_instagram';
    const TYPE_CAMPAIGN_OTHER = 'campaign_other';
    const TYPE_EMAIL = 'email';
    const TYPE_PRICE = 'price';
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_PRESS = 'press';
    const TYPE_LINEUP = 'lineup';
    const TYPE_CUSTOM = 'custom';

    // Ad campaign types
    const AD_CAMPAIGN_TYPES = [
        self::TYPE_CAMPAIGN_FB,
        self::TYPE_CAMPAIGN_GOOGLE,
        self::TYPE_CAMPAIGN_TIKTOK,
        self::TYPE_CAMPAIGN_INSTAGRAM,
        self::TYPE_CAMPAIGN_OTHER,
    ];

    // Type labels for UI
    const TYPE_LABELS = [
        self::TYPE_CAMPAIGN_FB => 'Facebook Ads',
        self::TYPE_CAMPAIGN_GOOGLE => 'Google Ads',
        self::TYPE_CAMPAIGN_TIKTOK => 'TikTok Ads',
        self::TYPE_CAMPAIGN_INSTAGRAM => 'Instagram Ads',
        self::TYPE_CAMPAIGN_OTHER => 'Other Ads',
        self::TYPE_EMAIL => 'Email Campaign',
        self::TYPE_PRICE => 'Price Change',
        self::TYPE_ANNOUNCEMENT => 'Announcement',
        self::TYPE_PRESS => 'Press Release',
        self::TYPE_LINEUP => 'Lineup Update',
        self::TYPE_CUSTOM => 'Custom',
    ];

    // Type icons for UI
    const TYPE_ICONS = [
        self::TYPE_CAMPAIGN_FB => '📘',
        self::TYPE_CAMPAIGN_GOOGLE => '🔍',
        self::TYPE_CAMPAIGN_TIKTOK => '🎵',
        self::TYPE_CAMPAIGN_INSTAGRAM => '📸',
        self::TYPE_CAMPAIGN_OTHER => '📣',
        self::TYPE_EMAIL => '📧',
        self::TYPE_PRICE => '💰',
        self::TYPE_ANNOUNCEMENT => '📢',
        self::TYPE_PRESS => '📰',
        self::TYPE_LINEUP => '🎤',
        self::TYPE_CUSTOM => '📌',
    ];

    // Type colors for UI
    const TYPE_COLORS = [
        self::TYPE_CAMPAIGN_FB => '#1877f2',
        self::TYPE_CAMPAIGN_GOOGLE => '#ea4335',
        self::TYPE_CAMPAIGN_TIKTOK => '#000000',
        self::TYPE_CAMPAIGN_INSTAGRAM => '#e4405f',
        self::TYPE_CAMPAIGN_OTHER => '#6b7280',
        self::TYPE_EMAIL => '#f59e0b',
        self::TYPE_PRICE => '#10b981',
        self::TYPE_ANNOUNCEMENT => '#8b5cf6',
        self::TYPE_PRESS => '#3b82f6',
        self::TYPE_LINEUP => '#ec4899',
        self::TYPE_CUSTOM => '#6b7280',
    ];

    /* Relations */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attributedEvents(): HasMany
    {
        return $this->hasMany(CoreCustomerEvent::class, 'attributed_milestone_id');
    }

    /* Scopes */
    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAdCampaigns($query)
    {
        return $query->whereIn('type', self::AD_CAMPAIGN_TYPES);
    }

    public function scopeWithBudget($query)
    {
        return $query->whereNotNull('budget')->where('budget', '>', 0);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where(function ($q3) use ($endDate) {
                         $q3->where('end_date', '>=', $endDate)
                            ->orWhereNull('end_date');
                     });
              });
        });
    }

    public function scopeActiveOnDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->where('end_date', '>=', $date)
                  ->orWhereNull('end_date');
            });
    }

    /* Helpers */
    public function isAdCampaign(): bool
    {
        return in_array($this->type, self::AD_CAMPAIGN_TYPES);
    }

    public function hasBudget(): bool
    {
        return $this->budget !== null && $this->budget > 0;
    }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'Unknown';
    }

    public function getTypeIcon(): string
    {
        return self::TYPE_ICONS[$this->type] ?? '📌';
    }

    public function getTypeColor(): string
    {
        return self::TYPE_COLORS[$this->type] ?? '#6b7280';
    }

    /**
     * Get the attribution end date (end_date + attribution_window_days)
     */
    public function getAttributionEndDate(): \Carbon\Carbon
    {
        $endDate = $this->end_date ?? $this->start_date;
        return $endDate->copy()->addDays($this->attribution_window_days);
    }

    /**
     * Check if a date falls within the attribution window
     */
    public function isWithinAttributionWindow($date): bool
    {
        $checkDate = \Carbon\Carbon::parse($date);
        return $checkDate->gte($this->start_date) && $checkDate->lte($this->getAttributionEndDate());
    }

    /**
     * Calculate ROI: ((revenue - budget) / budget) * 100
     */
    public function calculateROI(): ?float
    {
        if (!$this->hasBudget() || $this->budget == 0) {
            return null;
        }

        return round((($this->attributed_revenue - $this->budget) / $this->budget) * 100, 2);
    }

    /**
     * Calculate ROAS: revenue / budget
     */
    public function calculateROAS(): ?float
    {
        if (!$this->hasBudget() || $this->budget == 0) {
            return null;
        }

        return round($this->attributed_revenue / $this->budget, 2);
    }

    /**
     * Calculate CAC: budget / conversions
     */
    public function calculateCAC(): ?float
    {
        if (!$this->hasBudget() || $this->conversions == 0) {
            return null;
        }

        return round($this->budget / $this->conversions, 2);
    }

    /**
     * Update calculated metrics
     */
    public function updateCalculatedMetrics(): void
    {
        $this->roi = $this->calculateROI();
        $this->roas = $this->calculateROAS();
        $this->cac = $this->calculateCAC();
        $this->metrics_updated_at = now();
        $this->save();
    }

    /**
     * Get UTM parameters as array for link generation
     */
    public function getUtmParameters(): array
    {
        $params = [];

        if ($this->utm_source) $params['utm_source'] = $this->utm_source;
        if ($this->utm_medium) $params['utm_medium'] = $this->utm_medium;
        if ($this->utm_campaign) $params['utm_campaign'] = $this->utm_campaign;
        if ($this->utm_content) $params['utm_content'] = $this->utm_content;
        if ($this->utm_term) $params['utm_term'] = $this->utm_term;

        return $params;
    }

    /**
     * Generate tracking URL for the event
     */
    public function generateTrackingUrl(string $baseUrl): string
    {
        $params = $this->getUtmParameters();

        if (empty($params)) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }

    /**
     * Auto-generate UTM parameters based on milestone type and title
     */
    public function autoGenerateUtmParameters(): void
    {
        if (!$this->utm_campaign) {
            $this->utm_campaign = \Illuminate\Support\Str::slug($this->title);
        }

        if (!$this->utm_source) {
            $this->utm_source = match ($this->type) {
                self::TYPE_CAMPAIGN_FB, self::TYPE_CAMPAIGN_INSTAGRAM => 'facebook',
                self::TYPE_CAMPAIGN_GOOGLE => 'google',
                self::TYPE_CAMPAIGN_TIKTOK => 'tiktok',
                self::TYPE_EMAIL => 'email',
                default => 'organic',
            };
        }

        if (!$this->utm_medium) {
            $this->utm_medium = $this->isAdCampaign() ? 'cpc' : ($this->type === self::TYPE_EMAIL ? 'email' : 'referral');
        }
    }

    /**
     * Check if milestone matches given UTM parameters (for attribution)
     */
    public function matchesUtmParameters(array $params): bool
    {
        // If milestone has utm_campaign set, it must match
        if ($this->utm_campaign && isset($params['utm_campaign'])) {
            return strtolower($this->utm_campaign) === strtolower($params['utm_campaign']);
        }

        // If milestone has utm_source set, it must match
        if ($this->utm_source && isset($params['utm_source'])) {
            return strtolower($this->utm_source) === strtolower($params['utm_source']);
        }

        return false;
    }

    /**
     * Check if milestone matches click ID platform (for ad attribution)
     */
    public function matchesClickIdPlatform(?string $platform): bool
    {
        if (!$platform) {
            return false;
        }

        return match ($this->type) {
            self::TYPE_CAMPAIGN_FB, self::TYPE_CAMPAIGN_INSTAGRAM => $platform === 'facebook',
            self::TYPE_CAMPAIGN_GOOGLE => $platform === 'google',
            self::TYPE_CAMPAIGN_TIKTOK => $platform === 'tiktok',
            default => false,
        };
    }
}
