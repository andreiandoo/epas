<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One follow edge: a marketplace customer → an artist, organiser or venue (B2).
 */
class MarketplaceFollow extends Model
{
    /** What a customer is allowed to follow, keyed by the token the API accepts. */
    public const FOLLOWABLE_TYPES = [
        'artist' => Artist::class,
        'tenant' => Tenant::class,
        'venue' => Venue::class,
    ];

    protected $fillable = [
        'marketplace_customer_id',
        'followable_type',
        'followable_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    public function followable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('marketplace_customer_id', $customerId);
    }

    /**
     * Resolve the API's short token ("artist") to a model class.
     */
    public static function resolveType(string $token): ?string
    {
        return self::FOLLOWABLE_TYPES[$token] ?? null;
    }

    /**
     * Inverse of resolveType() — what the API hands back to the client.
     */
    public static function tokenFor(?string $class): ?string
    {
        if (! $class) {
            return null;
        }

        return array_search($class, self::FOLLOWABLE_TYPES, true) ?: null;
    }
}
