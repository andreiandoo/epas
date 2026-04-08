<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceVanityUrl extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'slug',
        'target_type',
        'target_id',
        'target_url',
        'is_active',
        'clicks_count',
        'last_accessed_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'clicks_count' => 'integer',
        'last_accessed_at' => 'datetime',
    ];

    public const TYPE_ARTIST = 'artist';
    public const TYPE_EVENT = 'event';
    public const TYPE_VENUE = 'venue';
    public const TYPE_ORGANIZER = 'organizer';
    public const TYPE_EXTERNAL_URL = 'external_url';

    public const TYPES = [
        self::TYPE_ARTIST => 'Artist',
        self::TYPE_EVENT => 'Event',
        self::TYPE_VENUE => 'Venue',
        self::TYPE_ORGANIZER => 'Organizator',
        self::TYPE_EXTERNAL_URL => 'URL extern',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Resolve the target model based on target_type and target_id.
     */
    public function resolveTarget(): ?Model
    {
        if (!$this->target_id) return null;

        return match ($this->target_type) {
            self::TYPE_ARTIST => Artist::find($this->target_id),
            self::TYPE_EVENT => Event::find($this->target_id),
            self::TYPE_VENUE => Venue::find($this->target_id),
            self::TYPE_ORGANIZER => MarketplaceOrganizer::find($this->target_id),
            default => null,
        };
    }

    /**
     * Build the canonical internal URL path for this vanity URL's target.
     */
    public function getTargetUrl(): ?string
    {
        if ($this->target_type === self::TYPE_EXTERNAL_URL) {
            return $this->target_url;
        }

        $target = $this->resolveTarget();
        if (!$target) return null;

        return match ($this->target_type) {
            self::TYPE_ARTIST => '/artist/' . $target->slug,
            self::TYPE_EVENT => '/bilete/' . $target->slug,
            self::TYPE_VENUE => '/locatie/' . $target->slug,
            self::TYPE_ORGANIZER => '/organizator/' . $target->slug,
            default => null,
        };
    }

    /**
     * Get a human-readable label for the target (for admin tables).
     */
    public function getTargetLabel(): ?string
    {
        if ($this->target_type === self::TYPE_EXTERNAL_URL) {
            return $this->target_url;
        }

        $target = $this->resolveTarget();
        if (!$target) return null;

        return match ($this->target_type) {
            self::TYPE_ARTIST, self::TYPE_VENUE, self::TYPE_ORGANIZER => $target->name ?? '#' . $target->id,
            self::TYPE_EVENT => is_array($target->title) ? ($target->title['ro'] ?? $target->title['en'] ?? reset($target->title) ?: '#' . $target->id) : ($target->title ?? '#' . $target->id),
            default => null,
        };
    }

    public function recordHit(): void
    {
        $this->forceFill([
            'clicks_count' => $this->clicks_count + 1,
            'last_accessed_at' => now(),
        ])->saveQuietly();
    }
}
