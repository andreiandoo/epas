<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-type notification opt-outs (D12).
 *
 * Absence of a row means "use the default for this type" — so the table only
 * grows when someone actually changes something, and a default change reaches
 * everyone who never expressed a preference.
 */
class NotificationPreference extends Model
{
    public const TYPE_SHORTS_DROPPED = 'shorts_dropped';

    public const TYPE_SHORTS_TRENDING = 'shorts_trending';

    public const TYPE_FOLLOWED_POSTED = 'followed_posted';

    public const TYPE_SHORTS_ABANDONED = 'shorts_abandoned';

    /** Defaults applied when a customer has no row for a type. */
    public const DEFAULTS = [
        self::TYPE_SHORTS_DROPPED => ['push' => true, 'email' => false],
        self::TYPE_SHORTS_TRENDING => ['push' => false, 'email' => false],
        self::TYPE_FOLLOWED_POSTED => ['push' => true, 'email' => false],
        // Opt-in by default off: a "you looked but didn't buy" nudge is the one
        // most likely to feel like surveillance.
        self::TYPE_SHORTS_ABANDONED => ['push' => false, 'email' => false],
    ];

    protected $fillable = [
        'marketplace_customer_id',
        'type',
        'push',
        'email',
    ];

    protected $casts = [
        'push' => 'boolean',
        'email' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    /**
     * Whether a given channel is allowed for this customer and type.
     */
    public static function allows(int $customerId, string $type, string $channel = 'push'): bool
    {
        $row = static::query()
            ->where('marketplace_customer_id', $customerId)
            ->where('type', $type)
            ->first();

        if ($row) {
            return (bool) $row->{$channel};
        }

        return (bool) (self::DEFAULTS[$type][$channel] ?? false);
    }
}
