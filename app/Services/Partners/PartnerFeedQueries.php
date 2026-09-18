<?php

namespace App\Services\Partners;

use App\Models\MarketplaceClient;
use Illuminate\Database\Query\Builder;

/**
 * Query pieces shared by the partner endpoints that count or filter upcoming events.
 *
 * Child occurrences of a recurring or multi-day event often carry their artists
 * and genres only on the parent, so every taxonomy join must accept both.
 */
class PartnerFeedQueries
{
    /**
     * Upcoming, not cancelled feed rows (alias f) joined to their event (alias e).
     */
    public static function upcoming(Builder $query, MarketplaceClient $client): Builder
    {
        return $query
            ->from('partner_event_feed as f')
            ->join('events as e', 'e.id', '=', 'f.event_id')
            ->where('f.marketplace_client_id', $client->id)
            ->whereNull('f.removed_at')
            ->where('f.status', '!=', 'cancelled')
            ->where('f.listed_until', '>=', now());
    }

    /**
     * Join a pivot on the feed's event or on its parent event.
     */
    public static function joinPivot(Builder $query, string $table, string $alias): Builder
    {
        return $query->join("{$table} as {$alias}", function ($join) use ($alias) {
            $join->on("{$alias}.event_id", '=', 'f.event_id')
                ->orOn("{$alias}.event_id", '=', 'e.parent_id');
        });
    }

    /**
     * The same rule for a query already scoped to partner_event_feed rows
     * (used by the events endpoint, where the table is not aliased).
     */
    public static function eventOrParent($query, string $column): void
    {
        $query->whereColumn($column, 'partner_event_feed.event_id')
            ->orWhereIn($column, fn ($parents) => $parents->select('parent_id')
                ->from('events')
                ->whereColumn('events.id', 'partner_event_feed.event_id')
                ->whereNotNull('parent_id'));
    }
}
