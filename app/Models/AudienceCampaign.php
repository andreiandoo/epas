<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudienceCampaign extends Model
{
    protected $fillable = [
        'tenant_id',
        'segment_id',
        'event_id',
        'name',
        'description',
        'campaign_type',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'settings',
        'results',
    ];

    protected $casts = [
        'settings' => 'array',
        'results' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const TYPE_EMAIL = 'email';
    const TYPE_META_ADS = 'meta_ads';
    const TYPE_GOOGLE_ADS = 'google_ads';
    const TYPE_TIKTOK_ADS = 'tiktok_ads';
    const TYPE_MULTI_CHANNEL = 'multi_channel';

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(AudienceSegment::class, 'segment_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AudienceExport::class, 'campaign_id');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('campaign_type', $type);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeScheduledForNow($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Check if campaign is email type
     */
    public function isEmailCampaign(): bool
    {
        return $this->campaign_type === self::TYPE_EMAIL;
    }

    /**
     * Check if campaign is an advertising campaign
     */
    public function isAdCampaign(): bool
    {
        return in_array($this->campaign_type, [
            self::TYPE_META_ADS,
            self::TYPE_GOOGLE_ADS,
            self::TYPE_TIKTOK_ADS,
        ]);
    }

    /**
     * Check if campaign can be launched
     */
    public function canLaunch(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED]);
    }

    /**
     * Check if campaign can be paused
     */
    public function canPause(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if campaign can be resumed
     */
    public function canResume(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Mark campaign as started
     */
    public function markStarted(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark campaign as completed
     */
    public function markCompleted(array $results = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'results' => array_merge($this->results ?? [], $results),
        ]);
    }

    /**
     * Mark campaign as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'results' => array_merge($this->results ?? [], [
                'error' => $errorMessage,
                'failed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get click-through rate
     */
    public function getCtrAttribute(): ?float
    {
        $results = $this->results ?? [];
        $sent = $results['sent'] ?? $results['impressions'] ?? 0;
        $clicks = $results['clicks'] ?? 0;

        if ($sent === 0) {
            return null;
        }

        return round(($clicks / $sent) * 100, 2);
    }

    /**
     * Get conversion rate
     */
    public function getConversionRateAttribute(): ?float
    {
        $results = $this->results ?? [];
        $clicks = $results['clicks'] ?? 0;
        $conversions = $results['conversions'] ?? 0;

        if ($clicks === 0) {
            return null;
        }

        return round(($conversions / $clicks) * 100, 2);
    }

    /**
     * Get return on ad spend (ROAS)
     */
    public function getRoasAttribute(): ?float
    {
        $results = $this->results ?? [];
        $cost = $results['cost_cents'] ?? 0;
        $revenue = $results['revenue_cents'] ?? 0;

        if ($cost === 0) {
            return null;
        }

        return round($revenue / $cost, 2);
    }
}
