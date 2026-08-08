<?php

namespace App\Services\Shorts;

use App\Models\Artist;
use App\Models\MarketplaceCustomer;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The viewer's taste, assembled once per feed page instead of per short.
 *
 * Reads what already exists — follows, favourite artists/venues, past orders,
 * profile city — rather than inventing a new preference store. Cached briefly:
 * a viewer's taste does not change between two scrolls, but it must not go stale
 * for a session either.
 */
class ShortAffinityProfile
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array{followed: array<string, bool>, favourites: array<string, bool>, events: array<int, bool>, city: string|null}
     */
    public function for(?MarketplaceCustomer $customer): array
    {
        if (! $customer) {
            return $this->empty();
        }

        return Cache::remember(
            "shorts:affinity:{$customer->id}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->build($customer),
        );
    }

    /**
     * @return array{followed: array<string, bool>, favourites: array<string, bool>, events: array<int, bool>, city: string|null}
     */
    protected function build(MarketplaceCustomer $customer): array
    {
        return [
            'followed' => $this->followed($customer),
            'favourites' => $this->favourites($customer),
            'events' => $this->purchasedEvents($customer),
            'city' => $customer->city,
        ];
    }

    /**
     * @return array<string, bool>
     */
    protected function followed(MarketplaceCustomer $customer): array
    {
        $keys = [];

        foreach (ShortFeedRanker::followedOwners($customer) as $follow) {
            $keys[$follow['type'].':'.$follow['id']] = true;
        }

        return $keys;
    }

    /**
     * Favourites are a weaker signal than a follow, but they predate the follow
     * graph and most viewers still only have these.
     *
     * @return array<string, bool>
     */
    protected function favourites(MarketplaceCustomer $customer): array
    {
        // One polymorphic table, keyed by favoriteable_type ('artist'|'venue'|
        // 'event') — not a table per type. The ranker keys owners by FQCN, so the
        // short token has to be mapped back to a class here.
        $classes = [
            'artist' => Artist::class,
            'venue' => Venue::class,
        ];

        $keys = [];

        try {
            $rows = DB::table('marketplace_customer_favorites')
                ->where('marketplace_customer_id', $customer->id)
                ->whereIn('favoriteable_type', array_keys($classes))
                ->get(['favoriteable_type', 'favoriteable_id']);

            foreach ($rows as $row) {
                $class = $classes[$row->favoriteable_type] ?? null;

                if ($class) {
                    $keys[$class.':'.$row->favoriteable_id] = true;
                }
            }
        } catch (\Throwable) {
            // Table absent (scoped dev schema) — favourites just do not
            // contribute; the ranker still has follows, geo and freshness.
        }

        return $keys;
    }

    /**
     * Events this viewer already bought into — the strongest possible statement
     * of interest in an artist/venue/genre.
     *
     * @return array<int, bool>
     */
    protected function purchasedEvents(MarketplaceCustomer $customer): array
    {
        try {
            return DB::table('orders')
                ->where('marketplace_customer_id', $customer->id)
                ->whereNotNull('event_id')
                ->distinct()
                ->pluck('event_id')
                ->flip()
                ->map(fn () => true)
                ->all();
        } catch (\Throwable $e) {
            Log::debug('ShortAffinityProfile: order lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Cold start: no signals at all. The ranker then falls back to featured +
     * freshness + popularity, which is the right first feed for a new account.
     *
     * @return array{followed: array<string, bool>, favourites: array<string, bool>, events: array<int, bool>, city: string|null}
     */
    protected function empty(): array
    {
        return ['followed' => [], 'favourites' => [], 'events' => [], 'city' => null];
    }

    public function forget(MarketplaceCustomer $customer): void
    {
        Cache::forget("shorts:affinity:{$customer->id}");
    }
}
