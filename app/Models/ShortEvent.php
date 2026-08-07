<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw telemetry row emitted by the mobile feed. Append-only: rows are written
 * once, rolled up by AggregateShortStatsJob and pruned by retention.
 */
class ShortEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_VIEW = 'view';

    public const TYPE_COMPLETE = 'complete';

    public const TYPE_LIKE = 'like';

    public const TYPE_UNLIKE = 'unlike';

    public const TYPE_SAVE = 'save';

    public const TYPE_UNSAVE = 'unsave';

    public const TYPE_SHARE = 'share';

    public const TYPE_CTA_CLICK = 'cta_click';

    public const TYPE_SKIP = 'skip';

    public const TYPES = [
        'impression', 'view', 'complete', 'like', 'unlike',
        'save', 'unsave', 'share', 'cta_click', 'skip',
    ];

    public const FEEDS = ['for_you', 'following', 'nearby', 'featured', 'event', 'artist', 'collection', 'story'];

    protected $fillable = [
        'short_id',
        'marketplace_customer_id',
        'session_id',
        'type',
        'watch_ms',
        'watch_ratio',
        'feed',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'watch_ms' => 'integer',
        'watch_ratio' => 'decimal:3',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }
}
