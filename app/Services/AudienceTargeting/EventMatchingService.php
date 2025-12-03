<?php

namespace App\Services\AudienceTargeting;

use App\Models\AudienceSegment;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Event;
use App\Models\EventRecommendation;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventMatchingService
{
    // Scoring weights for different match factors
    protected const WEIGHTS = [
        'genre_match' => 25,
        'type_match' => 20,
        'artist_match' => 20,
        'price_fit' => 15,
        'location_proximity' => 10,
        'high_engagement' => 5,
        'watchlist' => 5,
    ];

    public function __construct(
        protected SegmentationService $segmentationService
    ) {}

    /**
     * Find best-fit customers for an event
     */
    public function findBestCustomersForEvent(
        Event $event,
        int $limit = 100,
        int $minScore = 50
    ): Collection {
        $tenant = $event->tenant;

        // Get all customer profiles for this tenant
        $profiles = CustomerProfile::where('tenant_id', $tenant->id)
            ->with('customer')
            ->get();

        // Score each profile against the event
        $scoredCustomers = $profiles->map(function ($profile) use ($event) {
            $result = $this->scoreCustomerForEvent($profile, $event);
            return [
                'customer_id' => $profile->customer_id,
                'customer' => $profile->customer,
                'profile' => $profile,
                'score' => $result['score'],
                'reasons' => $result['reasons'],
            ];
        })
            ->filter(fn ($item) => $item['score'] >= $minScore)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $scoredCustomers;
    }

    /**
     * Score a single customer against an event
     */
    public function scoreCustomerForEvent(CustomerProfile $profile, Event $event): array
    {
        $score = 0;
        $reasons = [];

        // 1. Genre match
        $genreScore = $this->calculateGenreMatchScore($profile, $event);
        if ($genreScore > 0) {
            $score += $genreScore;
            $reasons[] = ['reason' => 'genre_match', 'weight' => $genreScore];
        }

        // 2. Event type match
        $typeScore = $this->calculateTypeMatchScore($profile, $event);
        if ($typeScore > 0) {
            $score += $typeScore;
            $reasons[] = ['reason' => 'type_match', 'weight' => $typeScore];
        }

        // 3. Artist match
        $artistScore = $this->calculateArtistMatchScore($profile, $event);
        if ($artistScore > 0) {
            $score += $artistScore;
            $reasons[] = ['reason' => 'artist_match', 'weight' => $artistScore];
        }

        // 4. Price fit
        $priceScore = $this->calculatePriceFitScore($profile, $event);
        if ($priceScore > 0) {
            $score += $priceScore;
            $reasons[] = ['reason' => 'price_fit', 'weight' => $priceScore];
        }

        // 5. Location proximity
        $locationScore = $this->calculateLocationScore($profile, $event);
        if ($locationScore > 0) {
            $score += $locationScore;
            $reasons[] = ['reason' => 'location_proximity', 'weight' => $locationScore];
        }

        // 6. High engagement bonus
        if ($profile->engagement_score >= 70) {
            $engagementBonus = self::WEIGHTS['high_engagement'];
            $score += $engagementBonus;
            $reasons[] = ['reason' => 'high_engagement', 'weight' => $engagementBonus];
        }

        // 7. Watchlist bonus
        $watchlistScore = $this->calculateWatchlistScore($profile, $event);
        if ($watchlistScore > 0) {
            $score += $watchlistScore;
            $reasons[] = ['reason' => 'watchlist', 'weight' => $watchlistScore];
        }

        return [
            'score' => min(100, $score),
            'reasons' => $reasons,
        ];
    }

    /**
     * Calculate genre match score
     */
    protected function calculateGenreMatchScore(CustomerProfile $profile, Event $event): int
    {
        $preferredGenres = collect($profile->preferred_genres ?? []);

        if ($preferredGenres->isEmpty()) {
            return 0;
        }

        $eventGenres = $event->eventGenres()->pluck('slug')->toArray();

        if (empty($eventGenres)) {
            return 0;
        }

        $maxWeight = 0;
        foreach ($preferredGenres as $genre) {
            if (in_array($genre['slug'] ?? '', $eventGenres)) {
                $maxWeight = max($maxWeight, $genre['weight'] ?? 0);
            }
        }

        return (int) round($maxWeight * self::WEIGHTS['genre_match']);
    }

    /**
     * Calculate event type match score
     */
    protected function calculateTypeMatchScore(CustomerProfile $profile, Event $event): int
    {
        $preferredTypes = collect($profile->preferred_event_types ?? []);

        if ($preferredTypes->isEmpty()) {
            return 0;
        }

        $eventTypes = $event->eventTypes()->pluck('slug')->toArray();

        if (empty($eventTypes)) {
            return 0;
        }

        $maxWeight = 0;
        foreach ($preferredTypes as $type) {
            if (in_array($type['slug'] ?? '', $eventTypes)) {
                $maxWeight = max($maxWeight, $type['weight'] ?? 0);
            }
        }

        return (int) round($maxWeight * self::WEIGHTS['type_match']);
    }

    /**
     * Calculate artist match score
     */
    protected function calculateArtistMatchScore(CustomerProfile $profile, Event $event): int
    {
        // Get artists from events the customer has attended
        $attendedEvents = $profile->attended_events ?? [];

        if (empty($attendedEvents)) {
            return 0;
        }

        $attendedArtists = DB::table('event_artist')
            ->whereIn('event_id', $attendedEvents)
            ->pluck('artist_id')
            ->toArray();

        if (empty($attendedArtists)) {
            return 0;
        }

        // Check if any of this event's artists were at previous events
        $eventArtists = $event->artists()->pluck('artists.id')->toArray();
        $matchingArtists = array_intersect($attendedArtists, $eventArtists);

        if (empty($matchingArtists)) {
            return 0;
        }

        // Scale based on number of matching artists
        $matchRatio = min(1, count($matchingArtists) / max(1, count($eventArtists)));

        return (int) round($matchRatio * self::WEIGHTS['artist_match']);
    }

    /**
     * Calculate price fit score
     */
    protected function calculatePriceFitScore(CustomerProfile $profile, Event $event): int
    {
        $priceRange = $profile->preferred_price_range;

        if (!$priceRange) {
            return self::WEIGHTS['price_fit'] / 2; // Neutral score
        }

        // Get min ticket price for the event
        $minPrice = $event->ticketTypes()->min('price_cents') ?? 0;

        $minRange = $priceRange['min'] ?? 0;
        $maxRange = $priceRange['max'] ?? PHP_INT_MAX;

        if ($minPrice >= $minRange && $minPrice <= $maxRange) {
            return self::WEIGHTS['price_fit'];
        }

        // Partial score if close to range
        if ($minPrice < $minRange) {
            $distance = $minRange - $minPrice;
            $tolerance = $minRange * 0.5;

            if ($distance <= $tolerance) {
                return (int) round(self::WEIGHTS['price_fit'] * (1 - $distance / $tolerance));
            }
        }

        if ($minPrice > $maxRange) {
            $distance = $minPrice - $maxRange;
            $tolerance = $maxRange * 0.5;

            if ($distance <= $tolerance) {
                return (int) round(self::WEIGHTS['price_fit'] * (1 - $distance / $tolerance));
            }
        }

        return 0;
    }

    /**
     * Calculate location proximity score
     */
    protected function calculateLocationScore(CustomerProfile $profile, Event $event): int
    {
        $locationData = $profile->location_data;

        if (!$locationData || !($locationData['city'] ?? null)) {
            return 0;
        }

        // Simple city match for now
        // In production, could use geocoding and distance calculation
        $eventCity = $event->venue?->city ?? null;

        if (!$eventCity) {
            return 0;
        }

        if (strtolower($locationData['city']) === strtolower($eventCity)) {
            return self::WEIGHTS['location_proximity'];
        }

        // Could add country-level match for partial score
        $eventCountry = $event->venue?->country ?? null;
        $customerCountry = $locationData['country'] ?? null;

        if ($eventCountry && $customerCountry &&
            strtolower($eventCountry) === strtolower($customerCountry)) {
            return (int) (self::WEIGHTS['location_proximity'] / 2);
        }

        return 0;
    }

    /**
     * Calculate watchlist score
     */
    protected function calculateWatchlistScore(CustomerProfile $profile, Event $event): int
    {
        $customer = $profile->customer;

        if (!$customer) {
            return 0;
        }

        $isOnWatchlist = $customer->watchlist()
            ->where('events.id', $event->id)
            ->exists();

        return $isOnWatchlist ? self::WEIGHTS['watchlist'] : 0;
    }

    /**
     * Generate recommendations for an event
     */
    public function generateRecommendations(
        Event $event,
        int $limit = 500,
        int $minScore = 50
    ): int {
        $bestCustomers = $this->findBestCustomersForEvent($event, $limit, $minScore);

        $created = 0;
        foreach ($bestCustomers as $match) {
            EventRecommendation::updateOrCreate(
                [
                    'tenant_id' => $event->tenant_id,
                    'event_id' => $event->id,
                    'customer_id' => $match['customer_id'],
                ],
                [
                    'match_score' => $match['score'],
                    'match_reasons' => $match['reasons'],
                ]
            );
            $created++;
        }

        return $created;
    }

    /**
     * Get recommendations for a customer
     */
    public function getRecommendationsForCustomer(
        Customer $customer,
        Tenant $tenant,
        int $limit = 10
    ): Collection {
        return EventRecommendation::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->whereNull('converted_at')
            ->whereHas('event', function ($q) {
                $q->where('event_date', '>=', now())
                    ->where('is_cancelled', false)
                    ->where('is_sold_out', false);
            })
            ->orderByDesc('match_score')
            ->limit($limit)
            ->with('event')
            ->get();
    }

    /**
     * Create an auto-segment for an event's best customers
     */
    public function createEventTargetSegment(
        Event $event,
        int $minScore = 60,
        ?string $name = null
    ): AudienceSegment {
        $tenant = $event->tenant;

        // Generate recommendations if not exists
        $existingCount = EventRecommendation::where('event_id', $event->id)->count();
        if ($existingCount === 0) {
            $this->generateRecommendations($event);
        }

        // Create static segment from recommendations
        $segment = AudienceSegment::create([
            'tenant_id' => $tenant->id,
            'name' => $name ?? "Best fit for: {$event->title}",
            'description' => "Auto-generated segment of customers most likely to attend this event (min score: {$minScore})",
            'segment_type' => AudienceSegment::TYPE_STATIC,
            'status' => AudienceSegment::STATUS_ACTIVE,
        ]);

        // Add customers from recommendations
        $recommendations = EventRecommendation::where('event_id', $event->id)
            ->where('match_score', '>=', $minScore)
            ->get();

        $syncData = [];
        foreach ($recommendations as $rec) {
            $syncData[$rec->customer_id] = [
                'score' => $rec->match_score,
                'source' => 'ml',
                'added_at' => now(),
            ];
        }

        $segment->customers()->sync($syncData);
        $segment->update([
            'customer_count' => count($syncData),
            'last_synced_at' => now(),
        ]);

        return $segment;
    }

    /**
     * Track when a recommendation is clicked
     */
    public function trackClick(int $eventId, int $customerId): void
    {
        EventRecommendation::where('event_id', $eventId)
            ->where('customer_id', $customerId)
            ->whereNull('clicked_at')
            ->update(['clicked_at' => now()]);
    }

    /**
     * Track when a recommendation leads to a conversion
     */
    public function trackConversion(int $eventId, int $customerId, int $orderId): void
    {
        EventRecommendation::where('event_id', $eventId)
            ->where('customer_id', $customerId)
            ->whereNull('converted_at')
            ->update([
                'converted_at' => now(),
                'order_id' => $orderId,
            ]);
    }

    /**
     * Get recommendation analytics for an event
     */
    public function getEventRecommendationStats(Event $event): array
    {
        $stats = EventRecommendation::where('event_id', $event->id)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(notified_at) as notified,
                COUNT(clicked_at) as clicked,
                COUNT(converted_at) as converted,
                AVG(match_score) as avg_score
            ')
            ->first();

        return [
            'total_recommendations' => $stats->total ?? 0,
            'notified' => $stats->notified ?? 0,
            'clicked' => $stats->clicked ?? 0,
            'converted' => $stats->converted ?? 0,
            'avg_match_score' => round($stats->avg_score ?? 0, 1),
            'click_rate' => $stats->notified > 0
                ? round(($stats->clicked / $stats->notified) * 100, 2)
                : 0,
            'conversion_rate' => $stats->clicked > 0
                ? round(($stats->converted / $stats->clicked) * 100, 2)
                : 0,
        ];
    }
}
