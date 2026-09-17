<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ad created in the marketplace admin and published on a media partner's site.
 */
class PartnerAd extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    public const SYNC_PENDING = 'pending';
    public const SYNC_PUBLISHED = 'published';
    public const SYNC_WITHDRAWN = 'withdrawn';
    public const SYNC_FAILED = 'failed';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_partner_id',
        'name',
        'slot',
        'format',
        'title',
        'text',
        'cta',
        'image_path',
        'image_mobile_path',
        'link_type',
        'event_id',
        'custom_url',
        'utm_campaign',
        'starts_at',
        'ends_at',
        'priority',
        'status',
        'paused_reason',
        'created_by_marketplace_admin_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'synced_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(MarketplacePartner::class, 'marketplace_partner_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(PartnerAdStat::class);
    }

    public function adFormat(): ?PartnerAdFormat
    {
        return PartnerAdFormat::where('marketplace_partner_id', $this->marketplace_partner_id)
            ->where('slot', $this->slot)
            ->where('format', $this->format)
            ->first();
    }
}
