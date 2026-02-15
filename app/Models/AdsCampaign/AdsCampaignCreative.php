<?php

namespace App\Models\AdsCampaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsCampaignCreative extends Model
{
    protected $table = 'ads_campaign_creatives';

    protected $fillable = [
        'campaign_id',
        'type',
        'headline',
        'primary_text',
        'description',
        'cta_type',
        'cta_url',
        'display_url',
        'media_path',
        'media_url',
        'media_type',
        'thumbnail_path',
        'media_width',
        'media_height',
        'media_duration',
        'media_size',
        'carousel_items',
        'variant_label',
        'is_winner',
        'facebook_overrides',
        'instagram_overrides',
        'google_overrides',
        'impressions',
        'clicks',
        'ctr',
        'spend',
        'conversions',
        'status',
        'rejection_reason',
        'sort_order',
    ];

    protected $casts = [
        'carousel_items' => 'array',
        'facebook_overrides' => 'array',
        'instagram_overrides' => 'array',
        'google_overrides' => 'array',
        'is_winner' => 'boolean',
        'ctr' => 'decimal:4',
        'spend' => 'decimal:2',
    ];

    // CTA types for event marketing
    const CTA_GET_TICKETS = 'GET_TICKETS';
    const CTA_BOOK_NOW = 'BOOK_NOW';
    const CTA_LEARN_MORE = 'LEARN_MORE';
    const CTA_SIGN_UP = 'SIGN_UP';
    const CTA_SHOP_NOW = 'SHOP_NOW';
    const CTA_WATCH_MORE = 'WATCH_MORE';
    const CTA_GET_OFFER = 'GET_OFFER';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdsCampaign::class, 'campaign_id');
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function isCarousel(): bool
    {
        return $this->type === 'carousel';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(): void
    {
        $this->update(['status' => 'approved', 'rejection_reason' => null]);
    }

    public function reject(string $reason): void
    {
        $this->update(['status' => 'rejected', 'rejection_reason' => $reason]);
    }

    public function recalculateMetrics(): void
    {
        if ($this->impressions > 0) {
            $this->update([
                'ctr' => ($this->clicks / $this->impressions) * 100,
            ]);
        }
    }

    /**
     * Get the effective content for a specific platform (with overrides)
     */
    public function getContentForPlatform(string $platform): array
    {
        $base = [
            'headline' => $this->headline,
            'primary_text' => $this->primary_text,
            'description' => $this->description,
            'cta_type' => $this->cta_type,
            'cta_url' => $this->cta_url,
            'media_path' => $this->media_path,
            'media_url' => $this->media_url,
        ];

        $overrides = match ($platform) {
            'facebook' => $this->facebook_overrides ?? [],
            'instagram' => $this->instagram_overrides ?? [],
            'google' => $this->google_overrides ?? [],
            default => [],
        };

        return array_merge($base, $overrides);
    }

    /**
     * Validate media specs for platform requirements
     */
    public function validateForPlatform(string $platform): array
    {
        $errors = [];

        if ($platform === 'facebook' || $platform === 'instagram') {
            if ($this->isImage()) {
                if ($this->media_width && $this->media_height) {
                    $ratio = $this->media_width / $this->media_height;
                    // Facebook/Instagram feed: 1.91:1 to 1:1
                    if ($ratio < 0.8 || $ratio > 2.0) {
                        $errors[] = "Image aspect ratio ({$ratio}) outside recommended range (0.8 - 2.0)";
                    }
                }
                if ($this->media_size && $this->media_size > 30 * 1024 * 1024) {
                    $errors[] = 'Image exceeds 30MB limit';
                }
            }
            if ($this->isVideo()) {
                if ($this->media_duration && $this->media_duration > 240) {
                    $errors[] = 'Video exceeds 240 second limit for feed ads';
                }
                if ($this->media_size && $this->media_size > 4 * 1024 * 1024 * 1024) {
                    $errors[] = 'Video exceeds 4GB limit';
                }
            }
            if (strlen($this->headline ?? '') > 40) {
                $errors[] = 'Headline exceeds 40 character recommendation';
            }
            if (strlen($this->primary_text ?? '') > 125) {
                $errors[] = 'Primary text exceeds 125 character recommendation';
            }
        }

        if ($platform === 'google') {
            if ($this->isImage()) {
                if ($this->media_size && $this->media_size > 5 * 1024 * 1024) {
                    $errors[] = 'Image exceeds 5MB limit for Google Ads';
                }
            }
            if (strlen($this->headline ?? '') > 30) {
                $errors[] = 'Headline exceeds 30 character limit for Google Ads';
            }
            if (strlen($this->description ?? '') > 90) {
                $errors[] = 'Description exceeds 90 character limit for Google Ads';
            }
        }

        return $errors;
    }

    public function scopeForVariant($query, string $variant)
    {
        return $query->where('variant_label', $variant);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'active']);
    }
}
