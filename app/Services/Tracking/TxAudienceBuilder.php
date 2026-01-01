<?php

namespace App\Services\Tracking;

use App\Models\CustomerSegment;
use App\Models\Platform\CoreCustomer;
use App\Models\FeatureStore\FsPersonActivityPattern;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use App\Models\FeatureStore\FsPersonChannelAffinity;
use App\Models\FeatureStore\FsPersonEmailMetrics;
use App\Models\FeatureStore\FsPersonPurchaseWindow;
use App\Models\FeatureStore\FsPersonTicketPref;
use App\Models\FeatureStore\FsPersonDaily;
use App\Models\Tracking\PersonTag;
use App\Models\Tracking\PersonTagAssignment;
use App\Models\Tracking\TxEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audience Builder Service
 *
 * Builds audiences based on tracking data and feature store metrics.
 * Supports complex queries combining multiple criteria.
 */
class TxAudienceBuilder
{
    protected int $tenantId;
    protected array $criteria = [];
    protected ?int $limit = null;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Create a new builder instance for a tenant.
     */
    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    /**
     * Find people with affinity for specific artists.
     *
     * @param array $artistIds List of artist IDs
     * @param float $minScore Minimum affinity score (default 5.0)
     */
    public function withArtistAffinity(array $artistIds, float $minScore = 5.0): self
    {
        $this->criteria[] = [
            'type' => 'artist_affinity',
            'artist_ids' => $artistIds,
            'min_score' => $minScore,
        ];
        return $this;
    }

    /**
     * Find people with affinity for specific genres.
     *
     * @param array $genres List of genre names
     * @param float $minScore Minimum affinity score (default 5.0)
     */
    public function withGenreAffinity(array $genres, float $minScore = 5.0): self
    {
        $this->criteria[] = [
            'type' => 'genre_affinity',
            'genres' => $genres,
            'min_score' => $minScore,
        ];
        return $this;
    }

    /**
     * Find people who have purchased in a specific price band.
     *
     * @param string $priceBand Price band: 'low', 'mid', 'high', 'premium'
     */
    public function withPriceBand(string $priceBand): self
    {
        $this->criteria[] = [
            'type' => 'price_band',
            'price_band' => $priceBand,
        ];
        return $this;
    }

    /**
     * Find people who prefer specific ticket categories.
     *
     * @param array $categories Ticket categories (GA, VIP, Premium, etc.)
     */
    public function withTicketPreference(array $categories): self
    {
        $this->criteria[] = [
            'type' => 'ticket_preference',
            'categories' => $categories,
        ];
        return $this;
    }

    /**
     * Find people who have attended events (entry_granted).
     *
     * @param int $minAttendance Minimum number of attendances
     */
    public function withMinAttendance(int $minAttendance = 1): self
    {
        $this->criteria[] = [
            'type' => 'min_attendance',
            'min_count' => $minAttendance,
        ];
        return $this;
    }

    /**
     * Find people who have made purchases.
     *
     * @param int $minPurchases Minimum number of purchases
     */
    public function withMinPurchases(int $minPurchases = 1): self
    {
        $this->criteria[] = [
            'type' => 'min_purchases',
            'min_count' => $minPurchases,
        ];
        return $this;
    }

    /**
     * Find people who have spent at least a certain amount.
     *
     * @param float $minAmount Minimum total spent
     */
    public function withMinSpent(float $minAmount): self
    {
        $this->criteria[] = [
            'type' => 'min_spent',
            'min_amount' => $minAmount,
        ];
        return $this;
    }

    /**
     * Find people active in the last N days.
     *
     * @param int $days Number of days
     */
    public function activeInLastDays(int $days = 30): self
    {
        $this->criteria[] = [
            'type' => 'active_days',
            'days' => $days,
        ];
        return $this;
    }

    /**
     * Find people who viewed specific events.
     *
     * @param array $eventIds Event entity IDs
     */
    public function viewedEvents(array $eventIds): self
    {
        $this->criteria[] = [
            'type' => 'viewed_events',
            'event_ids' => $eventIds,
        ];
        return $this;
    }

    /**
     * Find people who have NOT purchased specific events (for retargeting).
     *
     * @param array $eventIds Event entity IDs
     */
    public function notPurchasedEvents(array $eventIds): self
    {
        $this->criteria[] = [
            'type' => 'not_purchased_events',
            'event_ids' => $eventIds,
        ];
        return $this;
    }

    /**
     * Find people who abandoned checkout.
     *
     * @param int $daysAgo Number of days to look back
     */
    public function abandonedCheckout(int $daysAgo = 7): self
    {
        $this->criteria[] = [
            'type' => 'abandoned_checkout',
            'days_ago' => $daysAgo,
        ];
        return $this;
    }

    /**
     * Find people with specific tags (all must match).
     *
     * @param array $tagIds Tag IDs or slugs
     */
    public function withTags(array $tagIds): self
    {
        $this->criteria[] = [
            'type' => 'with_tags',
            'tag_ids' => $tagIds,
            'match' => 'all',
        ];
        return $this;
    }

    /**
     * Find people with any of the specified tags.
     *
     * @param array $tagIds Tag IDs or slugs
     */
    public function withAnyTags(array $tagIds): self
    {
        $this->criteria[] = [
            'type' => 'with_tags',
            'tag_ids' => $tagIds,
            'match' => 'any',
        ];
        return $this;
    }

    /**
     * Find people without specific tags.
     *
     * @param array $tagIds Tag IDs or slugs
     */
    public function withoutTags(array $tagIds): self
    {
        $this->criteria[] = [
            'type' => 'without_tags',
            'tag_ids' => $tagIds,
        ];
        return $this;
    }

    /**
     * Find people in a specific tag category.
     *
     * @param string $category Tag category (lifecycle, behavior, preference, etc.)
     */
    public function inTagCategory(string $category): self
    {
        $this->criteria[] = [
            'type' => 'tag_category',
            'category' => $category,
        ];
        return $this;
    }

    /**
     * Find people with specific purchase window preference.
     *
     * @param string $windowType Window type: last_minute, week, two_weeks, month, early_bird
     * @param float $minScore Minimum preference score (0-1)
     */
    public function withPurchaseWindow(string $windowType, float $minScore = 0.3): self
    {
        $this->criteria[] = [
            'type' => 'purchase_window',
            'window_type' => $windowType,
            'min_score' => $minScore,
        ];
        return $this;
    }

    /**
     * Find early bird buyers (purchase 31+ days before event).
     */
    public function earlyBirdBuyers(float $minScore = 0.4): self
    {
        return $this->withPurchaseWindow('early_bird', $minScore);
    }

    /**
     * Find last minute buyers (purchase 0-1 days before event).
     */
    public function lastMinuteBuyers(float $minScore = 0.4): self
    {
        return $this->withPurchaseWindow('last_minute', $minScore);
    }

    /**
     * Find people active during specific hours.
     *
     * @param int $startHour Start hour (0-23)
     * @param int $endHour End hour (0-23)
     */
    public function activeInHours(int $startHour, int $endHour): self
    {
        $this->criteria[] = [
            'type' => 'activity_hours',
            'start_hour' => $startHour,
            'end_hour' => $endHour,
        ];
        return $this;
    }

    /**
     * Find weekend buyers.
     */
    public function weekendBuyers(): self
    {
        $this->criteria[] = [
            'type' => 'weekend_buyer',
        ];
        return $this;
    }

    /**
     * Find people who prefer a specific channel.
     *
     * @param string $channel Channel: email, organic, paid_search, paid_social, direct, etc.
     */
    public function withChannelPreference(string $channel): self
    {
        $this->criteria[] = [
            'type' => 'channel_preference',
            'channel' => $channel,
        ];
        return $this;
    }

    /**
     * Find people with low email fatigue (good for campaigns).
     *
     * @param float $maxFatigue Maximum fatigue score (0-100)
     */
    public function withLowEmailFatigue(float $maxFatigue = 50): self
    {
        $this->criteria[] = [
            'type' => 'email_fatigue',
            'max_fatigue' => $maxFatigue,
        ];
        return $this;
    }

    /**
     * Find people with high email engagement.
     *
     * @param float $minOpenRate Minimum open rate (0-1)
     */
    public function withEmailEngagement(float $minOpenRate = 0.2): self
    {
        $this->criteria[] = [
            'type' => 'email_engagement',
            'min_open_rate' => $minOpenRate,
        ];
        return $this;
    }

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Execute the query and return person IDs.
     *
     * @return Collection<int> Collection of person IDs
     */
    public function getPersonIds(): Collection
    {
        if (empty($this->criteria)) {
            return collect();
        }

        $personIdSets = [];

        foreach ($this->criteria as $criterion) {
            $personIdSets[] = $this->applyCriterion($criterion);
        }

        // Intersect all sets (AND logic)
        $result = array_shift($personIdSets);
        foreach ($personIdSets as $set) {
            $result = $result->intersect($set);
        }

        if ($this->limit) {
            $result = $result->take($this->limit);
        }

        return $result->values();
    }

    /**
     * Execute the query and return CoreCustomer models.
     *
     * @return Collection<CoreCustomer>
     */
    public function getPersons(): Collection
    {
        $personIds = $this->getPersonIds();

        if ($personIds->isEmpty()) {
            return collect();
        }

        return CoreCustomer::whereIn('id', $personIds)->get();
    }

    /**
     * Get the count of matching persons.
     */
    public function count(): int
    {
        return $this->getPersonIds()->count();
    }

    /**
     * Save the audience as a CustomerSegment.
     */
    public function saveAsSegment(string $name, ?string $description = null, bool $isDynamic = true): CustomerSegment
    {
        $personIds = $this->getPersonIds();

        $segment = CustomerSegment::create([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'description' => $description,
            'conditions' => $this->criteria,
            'is_dynamic' => $isDynamic,
            'member_count' => $personIds->count(),
            'last_calculated_at' => now(),
        ]);

        // Add members if not dynamic (static segment)
        if (!$isDynamic && $personIds->isNotEmpty()) {
            $now = now();
            $segment->customers()->attach(
                $personIds->mapWithKeys(fn ($id) => [$id => ['added_at' => $now]])->toArray()
            );
        }

        return $segment;
    }

    /**
     * Recalculate a dynamic segment.
     */
    public static function recalculateSegment(CustomerSegment $segment): int
    {
        if (!$segment->is_dynamic || empty($segment->conditions)) {
            return $segment->member_count;
        }

        $builder = new self($segment->tenant_id);
        $builder->criteria = $segment->conditions;

        $count = $builder->count();

        $segment->update([
            'member_count' => $count,
            'last_calculated_at' => now(),
        ]);

        return $count;
    }

    /**
     * Apply a single criterion and return matching person IDs.
     */
    protected function applyCriterion(array $criterion): Collection
    {
        return match ($criterion['type']) {
            'artist_affinity' => $this->queryArtistAffinity($criterion),
            'genre_affinity' => $this->queryGenreAffinity($criterion),
            'price_band' => $this->queryPriceBand($criterion),
            'ticket_preference' => $this->queryTicketPreference($criterion),
            'min_attendance' => $this->queryMinAttendance($criterion),
            'min_purchases' => $this->queryMinPurchases($criterion),
            'min_spent' => $this->queryMinSpent($criterion),
            'active_days' => $this->queryActiveDays($criterion),
            'viewed_events' => $this->queryViewedEvents($criterion),
            'not_purchased_events' => $this->queryNotPurchasedEvents($criterion),
            'abandoned_checkout' => $this->queryAbandonedCheckout($criterion),
            'with_tags' => $this->queryWithTags($criterion),
            'without_tags' => $this->queryWithoutTags($criterion),
            'tag_category' => $this->queryTagCategory($criterion),
            'purchase_window' => $this->queryPurchaseWindow($criterion),
            'activity_hours' => $this->queryActivityHours($criterion),
            'weekend_buyer' => $this->queryWeekendBuyer($criterion),
            'channel_preference' => $this->queryChannelPreference($criterion),
            'email_fatigue' => $this->queryEmailFatigue($criterion),
            'email_engagement' => $this->queryEmailEngagement($criterion),
            default => collect(),
        };
    }

    protected function queryArtistAffinity(array $criterion): Collection
    {
        return FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->whereIn('artist_id', $criterion['artist_ids'])
            ->where('affinity_score', '>=', $criterion['min_score'])
            ->pluck('person_id')
            ->unique();
    }

    protected function queryGenreAffinity(array $criterion): Collection
    {
        return FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
            ->whereIn('genre', $criterion['genres'])
            ->where('affinity_score', '>=', $criterion['min_score'])
            ->pluck('person_id')
            ->unique();
    }

    protected function queryPriceBand(array $criterion): Collection
    {
        return FsPersonTicketPref::where('tenant_id', $this->tenantId)
            ->where('price_band', $criterion['price_band'])
            ->pluck('person_id')
            ->unique();
    }

    protected function queryTicketPreference(array $criterion): Collection
    {
        return FsPersonTicketPref::where('tenant_id', $this->tenantId)
            ->whereIn('ticket_category', $criterion['categories'])
            ->where('purchases_count', '>', 0)
            ->pluck('person_id')
            ->unique();
    }

    protected function queryMinAttendance(array $criterion): Collection
    {
        return TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'entry_granted')
            ->groupBy('person_id')
            ->havingRaw('COUNT(*) >= ?', [$criterion['min_count']])
            ->pluck('person_id');
    }

    protected function queryMinPurchases(array $criterion): Collection
    {
        return TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'order_completed')
            ->groupBy('person_id')
            ->havingRaw('COUNT(*) >= ?', [$criterion['min_count']])
            ->pluck('person_id');
    }

    protected function queryMinSpent(array $criterion): Collection
    {
        return TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'order_completed')
            ->groupBy('person_id')
            ->havingRaw("SUM((payload->>'gross_amount')::numeric) >= ?", [$criterion['min_amount']])
            ->pluck('person_id');
    }

    protected function queryActiveDays(array $criterion): Collection
    {
        return TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('occurred_at', '>=', now()->subDays($criterion['days']))
            ->pluck('person_id')
            ->unique();
    }

    protected function queryViewedEvents(array $criterion): Collection
    {
        return TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'event_view')
            ->whereIn(DB::raw("entities->>'event_entity_id'"), array_map('strval', $criterion['event_ids']))
            ->pluck('person_id')
            ->unique();
    }

    protected function queryNotPurchasedEvents(array $criterion): Collection
    {
        // Get all people who viewed but didn't purchase
        $viewed = $this->queryViewedEvents(['event_ids' => $criterion['event_ids']]);

        $purchased = TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'order_completed')
            ->whereIn(DB::raw("entities->>'event_entity_id'"), array_map('strval', $criterion['event_ids']))
            ->pluck('person_id')
            ->unique();

        return $viewed->diff($purchased);
    }

    protected function queryAbandonedCheckout(array $criterion): Collection
    {
        $since = now()->subDays($criterion['days_ago']);

        // People who started checkout
        $startedCheckout = TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'checkout_started')
            ->where('occurred_at', '>=', $since)
            ->pluck('person_id')
            ->unique();

        // People who completed order
        $completed = TxEvent::where('tenant_id', $this->tenantId)
            ->whereNotNull('person_id')
            ->where('event_name', 'order_completed')
            ->where('occurred_at', '>=', $since)
            ->pluck('person_id')
            ->unique();

        return $startedCheckout->diff($completed);
    }

    protected function queryWithTags(array $criterion): Collection
    {
        $tagIds = $this->resolveTagIds($criterion['tag_ids']);
        $match = $criterion['match'] ?? 'all';

        if (empty($tagIds)) {
            return collect();
        }

        $query = PersonTagAssignment::where('tenant_id', $this->tenantId)
            ->whereIn('tag_id', $tagIds)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });

        if ($match === 'all') {
            return $query->groupBy('person_id')
                ->havingRaw('COUNT(DISTINCT tag_id) = ?', [count($tagIds)])
                ->pluck('person_id');
        }

        return $query->pluck('person_id')->unique();
    }

    protected function queryWithoutTags(array $criterion): Collection
    {
        $tagIds = $this->resolveTagIds($criterion['tag_ids']);

        if (empty($tagIds)) {
            // Return all persons in tenant if no tags specified
            return CoreCustomer::fromTenant($this->tenantId)
                ->notMerged()
                ->pluck('id');
        }

        // Get persons who have any of the excluded tags
        $excluded = PersonTagAssignment::where('tenant_id', $this->tenantId)
            ->whereIn('tag_id', $tagIds)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->pluck('person_id')
            ->unique();

        // Return all persons except excluded
        return CoreCustomer::fromTenant($this->tenantId)
            ->notMerged()
            ->whereNotIn('id', $excluded)
            ->pluck('id');
    }

    protected function queryTagCategory(array $criterion): Collection
    {
        $tagIds = PersonTag::where('tenant_id', $this->tenantId)
            ->where('category', $criterion['category'])
            ->pluck('id');

        if ($tagIds->isEmpty()) {
            return collect();
        }

        return PersonTagAssignment::where('tenant_id', $this->tenantId)
            ->whereIn('tag_id', $tagIds)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->pluck('person_id')
            ->unique();
    }

    protected function queryPurchaseWindow(array $criterion): Collection
    {
        return FsPersonPurchaseWindow::where('tenant_id', $this->tenantId)
            ->where('window_type', $criterion['window_type'])
            ->where('preference_score', '>=', $criterion['min_score'])
            ->pluck('person_id')
            ->unique();
    }

    protected function queryActivityHours(array $criterion): Collection
    {
        $startHour = $criterion['start_hour'];
        $endHour = $criterion['end_hour'];

        return FsPersonActivityPattern::where('tenant_id', $this->tenantId)
            ->where(function ($query) use ($startHour, $endHour) {
                if ($startHour <= $endHour) {
                    $query->whereBetween('preferred_hour', [$startHour, $endHour]);
                } else {
                    // Handle overnight range (e.g., 22 to 4)
                    $query->where('preferred_hour', '>=', $startHour)
                          ->orWhere('preferred_hour', '<=', $endHour);
                }
            })
            ->pluck('person_id')
            ->unique();
    }

    protected function queryWeekendBuyer(array $criterion): Collection
    {
        return FsPersonActivityPattern::where('tenant_id', $this->tenantId)
            ->where('is_weekend_buyer', true)
            ->pluck('person_id')
            ->unique();
    }

    protected function queryChannelPreference(array $criterion): Collection
    {
        // Find persons where the specified channel has the highest conversion rate
        return FsPersonChannelAffinity::where('tenant_id', $this->tenantId)
            ->where('channel', $criterion['channel'])
            ->where('conversion_count', '>', 0)
            ->pluck('person_id')
            ->unique();
    }

    protected function queryEmailFatigue(array $criterion): Collection
    {
        return FsPersonEmailMetrics::where('tenant_id', $this->tenantId)
            ->where('fatigue_score', '<=', $criterion['max_fatigue'])
            ->pluck('person_id')
            ->unique();
    }

    protected function queryEmailEngagement(array $criterion): Collection
    {
        return FsPersonEmailMetrics::where('tenant_id', $this->tenantId)
            ->where('open_rate_30d', '>=', $criterion['min_open_rate'])
            ->pluck('person_id')
            ->unique();
    }

    /**
     * Resolve tag IDs from mixed input (IDs or slugs).
     */
    protected function resolveTagIds(array $tags): array
    {
        $ids = [];

        foreach ($tags as $tag) {
            if (is_int($tag) || is_numeric($tag)) {
                $ids[] = (int) $tag;
            } else {
                // Assume it's a slug
                $tagModel = PersonTag::where('tenant_id', $this->tenantId)
                    ->where('slug', $tag)
                    ->first();

                if ($tagModel) {
                    $ids[] = $tagModel->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Get propensity scores for an event.
     *
     * Calculates how likely each person is to purchase tickets for a specific event.
     *
     * @param int $eventId The event entity ID
     * @param array $eventArtistIds Artist IDs associated with the event
     * @param array $eventGenres Genres associated with the event
     * @param float $eventAvgPrice Average ticket price for the event
     * @return Collection<array{person_id: int, propensity_score: float}>
     */
    public function getPropensityScores(
        int $eventId,
        array $eventArtistIds,
        array $eventGenres,
        float $eventAvgPrice
    ): Collection {
        // Get all potential customers with any affinity data
        $personIds = collect();

        if (!empty($eventArtistIds)) {
            $personIds = $personIds->merge(
                FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
                    ->whereIn('artist_id', $eventArtistIds)
                    ->pluck('person_id')
            );
        }

        if (!empty($eventGenres)) {
            $personIds = $personIds->merge(
                FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
                    ->whereIn('genre', $eventGenres)
                    ->pluck('person_id')
            );
        }

        $personIds = $personIds->unique();

        if ($personIds->isEmpty()) {
            return collect();
        }

        // Calculate scores for each person
        $scores = [];

        foreach ($personIds as $personId) {
            $score = 0;
            $factors = 0;

            // Artist affinity component (0-40 points)
            if (!empty($eventArtistIds)) {
                $artistScore = FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
                    ->where('person_id', $personId)
                    ->whereIn('artist_id', $eventArtistIds)
                    ->max('affinity_score') ?? 0;
                $score += min(40, $artistScore * 2);
                $factors++;
            }

            // Genre affinity component (0-30 points)
            if (!empty($eventGenres)) {
                $genreScore = FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
                    ->where('person_id', $personId)
                    ->whereIn('genre', $eventGenres)
                    ->avg('affinity_score') ?? 0;
                $score += min(30, $genreScore * 2);
                $factors++;
            }

            // Price fit component (0-20 points)
            $priceFit = FsPersonTicketPref::calculatePriceFit($this->tenantId, $personId, $eventAvgPrice);
            $score += $priceFit * 20;
            $factors++;

            // Purchase history component (0-10 points)
            $purchases = TxEvent::where('tenant_id', $this->tenantId)
                ->where('person_id', $personId)
                ->where('event_name', 'order_completed')
                ->count();
            $score += min(10, $purchases * 2);

            $scores[] = [
                'person_id' => $personId,
                'propensity_score' => round($score, 2),
            ];
        }

        // Sort by score descending
        return collect($scores)->sortByDesc('propensity_score')->values();
    }
}
