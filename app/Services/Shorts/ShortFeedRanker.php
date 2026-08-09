<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceFollow;
use App\Models\Short;
use App\Models\ShortEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scores and orders the "For You" feed (B2 §6).
 *
 * Deliberately an explainable scored query, not a model: every term is a named
 * signal with a configurable weight, and the breakdown can be logged. When a
 * short ends up at position 1, someone must be able to say why.
 *
 * Runs over a bounded candidate pool (config: shorts.feed.candidate_pool) rather
 * than the whole table — the feed only ever shows a page, and scoring in PHP is
 * fine at that size while staying portable across sqlite/pgsql.
 */
class ShortFeedRanker
{
    public function __construct(private readonly ShortAffinityProfile $profile) {}

    /**
     * @param  Collection<int, Short>  $candidates
     * @return Collection<int, Short> ordered best-first, diversified
     */
    public function rank(Collection $candidates, ?MarketplaceCustomer $customer, ?int $pageLimit = null): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $weights = config('shorts.ranker.weights');
        $profile = $this->profile->for($customer);
        $seen = $this->seenShortIds($customer, $candidates->pluck('id')->all());

        $scored = $candidates->map(function (Short $short) use ($weights, $profile, $seen) {
            $parts = [
                'affinity' => $weights['affinity'] * $this->affinity($short, $profile),
                'popularity' => $weights['popularity'] * $this->popularity($short),
                'watch' => $weights['watch'] * (float) $short->avg_watch_ratio,
                'geo' => $weights['geo'] * $this->geo($short, $profile),
                'freshness' => $weights['freshness'] * $this->freshness($short),
                'featured' => $weights['featured'] * ($short->is_featured ? 1.0 : 0.0),
                // Velocity relative to baseline, recomputed by ComputeTrendingJob.
                'trending' => ($weights['trending'] ?? 0) * min((float) $short->trending_score / 5, 1.0),
                // Something already watched should not crowd out something new.
                'seen' => -$weights['seen_penalty'] * (isset($seen[$short->id]) ? 1.0 : 0.0),
                // A poster short auto-built from catalogue images loses a tie to
                // a real vertical clip. It only applies while there is no video
                // asset: once a render lands, the short stops being a still and
                // stops being penalised (B3).
                'generated' => -($weights['generated_penalty'] ?? 0) * ($this->isPosterOnly($short) ? 1.0 : 0.0),
            ];

            $short->setAttribute('feed_score', array_sum($parts));
            $short->setAttribute('feed_score_parts', $parts);

            return $short;
        });

        $ordered = $scored->sortByDesc(fn (Short $short) => $short->getAttribute('feed_score'))->values();

        $this->explain($ordered);

        if ($pageLimit !== null) {
            $ordered = $this->explore($ordered, $pageLimit);
        }

        return config('shorts.feed.diversity_enabled', true)
            ? $this->diversify($ordered)
            : $ordered;
    }

    /**
     * Favourite / followed / previously bought — the strongest signal there is.
     *
     * @param  array<string, mixed>  $profile
     */
    protected function affinity(Short $short, array $profile): float
    {
        $score = 0.0;

        if ($short->owner_type && $short->owner_id) {
            $key = $short->owner_type.':'.$short->owner_id;

            if (isset($profile['followed'][$key])) {
                $score += 1.0;
            }

            if (isset($profile['favourites'][$key])) {
                $score += 0.6;
            }
        }

        if ($short->event_id && isset($profile['events'][$short->event_id])) {
            $score += 0.8;
        }

        return min($score, 2.0);
    }

    /**
     * Velocity, not totals: a short from last year with 100k views should not
     * outrank one taking off right now.
     */
    protected function popularity(Short $short): float
    {
        $signal = $short->views + ($short->completions * 2) + $short->likes;

        if ($signal <= 0) {
            return 0.0;
        }

        // Compressed so a viral outlier does not flatten everything else.
        return min(log10($signal + 1) / 5, 1.0);
    }

    /**
     * The event's city comes from its venue — `events` carries no city column.
     * Relies on the eager load in ShortFeedService::baseQuery(); an unloaded
     * relation contributes nothing rather than firing a query per short.
     *
     * @param  array<string, mixed>  $profile
     */
    protected function geo(Short $short, array $profile): float
    {
        $city = $profile['city'] ?? null;

        if (! $city || ! $short->relationLoaded('event') || ! $short->event) {
            return 0.0;
        }

        $venue = $short->event->relationLoaded('venue') ? $short->event->venue : null;

        if (! $venue?->city) {
            return 0.0;
        }

        return strcasecmp((string) $venue->city, (string) $city) === 0 ? 1.0 : 0.0;
    }

    /**
     * A generated short that still has no video behind it — a poster played as
     * a card.
     *
     * Checked on the columns rather than on the `playback_url` accessor on
     * purpose: that accessor signs a provider URL, which is a network-adjacent
     * call, and the ranker runs it over a 200-row candidate pool on every feed
     * request.
     */
    protected function isPosterOnly(Short $short): bool
    {
        if (! $short->is_generated || $short->is_external) {
            return false;
        }

        return ! $short->provider_asset_id && ! $short->hls_url && ! $short->path;
    }

    /**
     * Exponential decay on publication age, half-life from config.
     */
    protected function freshness(Short $short): float
    {
        if (! $short->published_at) {
            return 0.0;
        }

        $hours = max(0, now()->diffInHours($short->published_at, absolute: true));
        $halfLife = max(1, (int) config('shorts.ranker.freshness_half_life_hours', 72));

        return 2 ** (-$hours / $halfLife);
    }

    /**
     * Shorts this viewer already saw, so they can be pushed down.
     *
     * Reads the distilled seen store first: it is one row per (viewer, short)
     * rather than one per playback, and it survives the telemetry prune — without
     * it a short would start reappearing in someone's feed 90 days later purely
     * because the evidence was deleted. Raw telemetry is the fallback so the
     * penalty still works between two runs of SyncShortImpressionsJob.
     *
     * @param  array<int, int>  $candidateIds
     * @return array<int, bool>
     */
    protected function seenShortIds(?MarketplaceCustomer $customer, array $candidateIds): array
    {
        if (! $customer || $candidateIds === []) {
            return [];
        }

        $seen = [];

        try {
            $seen = DB::table('short_impressions')
                ->where('marketplace_customer_id', $customer->id)
                ->whereIn('short_id', $candidateIds)
                ->pluck('short_id')
                ->flip()
                ->map(fn () => true)
                ->all();
        } catch (\Throwable $e) {
            Log::debug('ShortFeedRanker: seen store unavailable', ['error' => $e->getMessage()]);
        }

        try {
            $recent = ShortEvent::query()
                ->where('marketplace_customer_id', $customer->id)
                ->whereIn('short_id', $candidateIds)
                ->whereIn('type', [ShortEvent::TYPE_VIEW, ShortEvent::TYPE_COMPLETE])
                ->distinct()
                ->pluck('short_id')
                ->flip()
                ->map(fn () => true)
                ->all();

            $seen += $recent;
        } catch (\Throwable $e) {
            Log::debug('ShortFeedRanker: seen lookup failed', ['error' => $e->getMessage()]);
        }

        return $seen;
    }

    /**
     * Reserve a slice of the page for shorts nobody has watched yet (D5).
     *
     * Without this the ranker is rich-get-richer: popularity is a term in the
     * score, so a short with no impressions can never accumulate the signal it
     * would need to rank. Epsilon-greedy — a fixed fraction of slots goes to
     * low-impression shorts regardless of score.
     *
     * @param  Collection<int, Short>  $ordered
     * @return Collection<int, Short>
     */
    protected function explore(Collection $ordered, int $limit): Collection
    {
        $rate = (float) config('shorts.ranker.exploration_rate', 0.15);

        if ($rate <= 0 || $ordered->count() <= $limit) {
            return $ordered;
        }

        $slots = (int) floor($limit * $rate);

        if ($slots < 1) {
            return $ordered;
        }

        $threshold = (int) config('shorts.ranker.exploration_impression_threshold', 50);

        $head = $ordered->take($limit);
        $tail = $ordered->slice($limit);

        // Fresh candidates that missed the cut purely for lack of signal.
        $fresh = $tail
            ->filter(fn (Short $short) => $short->impressions < $threshold)
            ->take($slots);

        if ($fresh->isEmpty()) {
            return $ordered;
        }

        // Displace from the bottom of the page, never the top: exploration must
        // not cost the viewer the best thing we had for them.
        $kept = $head->take($limit - $fresh->count());

        return $kept->concat($fresh)->concat($tail->reject(
            fn (Short $short) => $fresh->contains(fn (Short $f) => $f->is($short))
        ))->values();
    }

    /**
     * Never two consecutive shorts from the same owner. Without this a single
     * prolific organiser takes over the whole page.
     *
     * @param  Collection<int, Short>  $ordered
     * @return Collection<int, Short>
     */
    protected function diversify(Collection $ordered): Collection
    {
        $result = collect();
        $deferred = collect();
        $lastOwner = null;

        foreach ($ordered as $short) {
            $owner = $short->owner_type.':'.$short->owner_id;

            if ($owner !== ':' && $owner === $lastOwner) {
                $deferred->push($short);

                continue;
            }

            $result->push($short);
            $lastOwner = $owner;

            // Re-admit a deferred short as soon as it no longer clashes.
            $promotable = $deferred->first(fn (Short $d) => $d->owner_type.':'.$d->owner_id !== $lastOwner);

            if ($promotable) {
                $deferred = $deferred->reject(fn (Short $d) => $d->is($promotable))->values();
                $result->push($promotable);
                $lastOwner = $promotable->owner_type.':'.$promotable->owner_id;
            }
        }

        // Anything still deferred goes at the end rather than being dropped —
        // a diversity rule must not silently shrink the page.
        return $result->concat($deferred)->values();
    }

    /**
     * @param  Collection<int, Short>  $ordered
     */
    protected function explain(Collection $ordered): void
    {
        if (! config('shorts.ranker.explain') || app()->isProduction()) {
            return;
        }

        Log::debug('ShortFeedRanker scores', $ordered->take(10)->mapWithKeys(fn (Short $short) => [
            $short->id => [
                'score' => round((float) $short->getAttribute('feed_score'), 3),
                'parts' => array_map(fn ($v) => round((float) $v, 3), $short->getAttribute('feed_score_parts')),
            ],
        ])->all());
    }

    /**
     * Owner keys the viewer follows — used both by the ranker and by the
     * "following" segment's candidate query.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public static function followedOwners(MarketplaceCustomer $customer): array
    {
        try {
            return DB::table('marketplace_follows')
                ->where('marketplace_customer_id', $customer->id)
                ->get(['followable_type', 'followable_id'])
                ->map(fn ($row) => ['type' => $row->followable_type, 'id' => (int) $row->followable_id])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, class-string>
     */
    public static function followableClasses(): array
    {
        return array_values(MarketplaceFollow::FOLLOWABLE_TYPES);
    }
}
