<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudienceExport extends Model
{
    protected $fillable = [
        'tenant_id',
        'segment_id',
        'campaign_id',
        'platform',
        'export_type',
        'external_audience_id',
        'external_audience_name',
        'customer_count',
        'matched_count',
        'match_rate',
        'status',
        'error_message',
        'exported_at',
        'expires_at',
    ];

    protected $casts = [
        'match_rate' => 'decimal:2',
        'exported_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const PLATFORM_META = 'meta';
    const PLATFORM_GOOGLE = 'google';
    const PLATFORM_TIKTOK = 'tiktok';
    const PLATFORM_BREVO = 'brevo';

    const TYPE_CUSTOM_AUDIENCE = 'custom_audience';
    const TYPE_LOOKALIKE = 'lookalike';
    const TYPE_EMAIL_LIST = 'email_list';
    const TYPE_CONTACT_SYNC = 'contact_sync';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
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

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AudienceCampaign::class, 'campaign_id');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if export is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if export has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if export is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark export as processing
     */
    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark export as completed
     */
    public function markCompleted(
        string $externalAudienceId,
        ?string $externalAudienceName = null,
        ?int $matchedCount = null,
        ?\DateTimeInterface $expiresAt = null
    ): void {
        $matchRate = null;
        if ($matchedCount !== null && $this->customer_count > 0) {
            $matchRate = round(($matchedCount / $this->customer_count) * 100, 2);
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'external_audience_id' => $externalAudienceId,
            'external_audience_name' => $externalAudienceName,
            'matched_count' => $matchedCount,
            'match_rate' => $matchRate,
            'exported_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Mark export as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get platform display name
     */
    public function getPlatformDisplayNameAttribute(): string
    {
        return match ($this->platform) {
            self::PLATFORM_META => 'Meta (Facebook/Instagram)',
            self::PLATFORM_GOOGLE => 'Google Ads',
            self::PLATFORM_TIKTOK => 'TikTok Ads',
            self::PLATFORM_BREVO => 'Brevo (Email)',
            default => ucfirst($this->platform),
        };
    }

    /**
     * Get export type display name
     */
    public function getExportTypeDisplayNameAttribute(): string
    {
        return match ($this->export_type) {
            self::TYPE_CUSTOM_AUDIENCE => 'Custom Audience',
            self::TYPE_LOOKALIKE => 'Lookalike Audience',
            self::TYPE_EMAIL_LIST => 'Email List',
            self::TYPE_CONTACT_SYNC => 'Contact Sync',
            default => ucfirst(str_replace('_', ' ', $this->export_type)),
        };
    }
}
