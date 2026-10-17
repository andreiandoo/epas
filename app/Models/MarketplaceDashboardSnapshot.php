<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A dashboard value of a closed month, stored once so it is never recomputed.
 * `dashboard:unfreeze` deletes rows when a month must be recalculated.
 */
class MarketplaceDashboardSnapshot extends Model
{
    protected $fillable = ['marketplace_client_id', 'kind', 'period', 'payload', 'computed_at'];

    protected $casts = [
        'payload' => 'array',
        'computed_at' => 'datetime',
    ];

    /** Stored value for (marketplace, kind, period), computing and saving it on first use. */
    public static function remember(int $marketplaceId, string $kind, string $period, Closure $compute): mixed
    {
        $keys = ['marketplace_client_id' => $marketplaceId, 'kind' => $kind, 'period' => $period];

        $row = static::where($keys)->first();
        if ($row) {
            return $row->payload['v'] ?? null;
        }

        $value = $compute();
        static::updateOrCreate($keys, ['payload' => ['v' => $value], 'computed_at' => now()]);

        return $value;
    }
}
