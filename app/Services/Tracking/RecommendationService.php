<?php

namespace App\Services\Tracking;

use App\Models\CoreCustomer;
use App\Models\Event;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use App\Models\FeatureStore\FsPersonAntiAffinityArtist;
use App\Models\FeatureStore\FsPersonAntiAffinityGenre;
use App\Models\FeatureStore\FsPersonTicketPref;
use App\Models\FeatureStore\FsPersonPurchaseWindow;
use App\Models\FeatureStore\FsPersonActivityPattern;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    protected int $tenantId;
    protected int $personId;
    protected ?CoreCustomer $person = null;

    // Recommendation weights
    protected const WEIGHTS = [
        'artist_affinity' => 0.35,
        'genre_affinity' => 0.25,
        'price_match' => 0.15,
        'timing_match' => 0.10,
        'collaborative' => 0.10,
        'recency_boost' => 0.05,
    ];

    // Anti-affinity penalty
    protected const ANTI_AFFINITY_PENALTY = 0.5;

    public function __construct(int $tenantId, int $personId)
    {
        $this->tenantId = $tenantId;
        $this->personId = $personId;
    }

    public static function for(int $tenantId, int $personId): self
    {
        return new self($tenantId, $personId);
    }

    /**
     * Get personalized event recommendations
     */
    public function getEventRecommendations(int $limit = 10, array $options = []): array
    {
        $cacheKey = "recs:events:{$this->tenantId}:{$this->personId}:" . md5(json_encode($options));
        $cacheTtl = $options['cache_ttl'] ?? 3600;

        if (($options['cached'] ?? true) && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Get person's preferences
        $artistAffinities = $this->getArtistAffinities();
        $genreAffinities = $this->getGenreAffinities();
        $antiAffinities = $this->getAntiAffinities();
        $pricePrefs = $this->getPricePreferences();
        $purchaseWindow = $this->getPurchaseWindowPreference();

        // Get upcoming events
        $events = $this->getUpcomingEvents($options);

        // Score each event
        $scoredEvents = [];
        foreach ($events as $event) {
            $score = $this->scoreEvent(
                $event,
                $artistAffinities,
                $genreAffinities,
                $antiAffinities,
                $pricePrefs,
                $purchaseWindow
            );

            if ($score > 0) {
                $scoredEvents[] = [
                    'event_id' => $event->id,
                    'event' => $event,
                    'score' => round($score, 4),
                    'reasons' => $this->getRecommendationReasons($event, $artistAffinities, $genreAffinities, $pricePrefs),
                ];
            }
        }

        // Sort by score and limit
        usort($scoredEvents, fn($a, $b) => $b['score'] <=> $a['score']);
        $recommendations = array_slice($scoredEvents, 0, $limit);

        // Add collaborative recommendations if not enough
        if (count($recommendations) < $limit) {
            $collaborative = $this->getCollaborativeRecommendations(
                $limit - count($recommendations),
                array_column($recommendations, 'event_id')
            );
            $recommendations = array_merge($recommendations, $collaborative);
        }

        $result = [
            'person_id' => $this->personId,
            'recommendations' => $recommendations,
            'generated_at' => now()->toIso8601String(),
            'strategy' => 'hybrid',
        ];

        Cache::put($cacheKey, $result, $cacheTtl);

        return $result;
    }

    /**
     * Get artist recommendations based on taste
     */
    public function getArtistRecommendations(int $limit = 10): array
    {
        $cacheKey = "recs:artists:{$this->tenantId}:{$this->personId}";

        return Cache::remember($cacheKey, 3600, function () use ($limit) {
            // Get liked genres
            $genreAffinities = $this->getGenreAffinities();
            if ($genreAffinities->isEmpty()) {
                return ['recommendations' => [], 'strategy' => 'cold_start'];
            }

            // Get already known artists
            $knownArtistIds = FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
                ->where('person_id', $this->personId)
                ->pluck('artist_id')
                ->toArray();

            // Find artists in preferred genres that user hasn't interacted with
            $recommendations = DB::table('artists')
                ->whereIn('genre_id', $genreAffinities->pluck('genre_id'))
                ->whereNotIn('id', $knownArtistIds)
                ->where('tenant_id', $this->tenantId)
                ->orderByDesc('popularity_score')
                ->limit($limit)
                ->get()
                ->map(function ($artist) use ($genreAffinities) {
                    $genreScore = $genreAffinities->firstWhere('genre_id', $artist->genre_id)?->affinity_score ?? 0;
                    return [
                        'artist_id' => $artist->id,
                        'artist_name' => $artist->name,
                        'score' => $genreScore * ($artist->popularity_score ?? 0.5),
                        'reason' => 'Based on your interest in ' . ($artist->genre_name ?? 'similar genres'),
                    ];
                })
                ->sortByDesc('score')
                ->values()
                ->toArray();

            return [
                'person_id' => $this->personId,
                'recommendations' => $recommendations,
                'generated_at' => now()->toIso8601String(),
                'strategy' => 'genre_expansion',
            ];
        });
    }

    /**
     * Get "customers who bought X also bought Y" recommendations
     */
    public function getCollaborativeRecommendations(int $limit = 5, array $excludeEventIds = []): array
    {
        // Find similar users based on purchase overlap
        $similarUsers = $this->findSimilarUsers(50);

        if (empty($similarUsers)) {
            return [];
        }

        // Get events purchased by similar users but not by this user
        $userEventIds = $this->getUserPurchasedEventIds();
        $allExcluded = array_merge($userEventIds, $excludeEventIds);

        $recommendations = DB::table('core_customer_events as cce')
            ->join('events as e', 'cce.event_id', '=', 'e.id')
            ->whereIn('cce.person_id', $similarUsers)
            ->where('cce.event_type', 'purchase')
            ->where('e.start_date', '>', now())
            ->whereNotIn('cce.event_id', $allExcluded)
            ->groupBy('cce.event_id', 'e.id', 'e.name')
            ->select([
                'cce.event_id',
                'e.name as event_name',
                DB::raw('COUNT(DISTINCT cce.person_id) as buyer_count'),
            ])
            ->orderByDesc('buyer_count')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'event_id' => $row->event_id,
                'score' => min(1.0, $row->buyer_count / 10),
                'reasons' => ["Popular with {$row->buyer_count} similar fans"],
            ])
            ->toArray();

        return $recommendations;
    }

    /**
     * Find users with similar taste
     */
    public function findSimilarUsers(int $limit = 50): array
    {
        // Get this user's top artists
        $userArtists = FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('affinity_score', '>', 0.3)
            ->orderByDesc('affinity_score')
            ->limit(20)
            ->pluck('artist_id')
            ->toArray();

        if (empty($userArtists)) {
            return [];
        }

        // Find other users who like the same artists
        return FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->where('person_id', '!=', $this->personId)
            ->whereIn('artist_id', $userArtists)
            ->where('affinity_score', '>', 0.3)
            ->groupBy('person_id')
            ->havingRaw('COUNT(DISTINCT artist_id) >= ?', [min(3, count($userArtists))])
            ->orderByRaw('COUNT(DISTINCT artist_id) DESC')
            ->limit($limit)
            ->pluck('person_id')
            ->toArray();
    }

    /**
     * Get cross-sell recommendations (merchandise, upgrades)
     */
    public function getCrossSellRecommendations(int $eventId): array
    {
        $pricePrefs = $this->getPricePreferences();

        // Determine if user is likely to upgrade
        $upgradeScore = 0;
        if ($pricePrefs->isNotEmpty()) {
            $avgPriceBand = $pricePrefs->avg('price_band') ?? 2;
            $highEndPurchases = $pricePrefs->where('price_band', '>=', 3)->sum('purchase_count');
            $totalPurchases = $pricePrefs->sum('purchase_count');

            if ($totalPurchases > 0) {
                $upgradeScore = ($highEndPurchases / $totalPurchases) * 0.5 + ($avgPriceBand / 5) * 0.5;
            }
        }

        return [
            'event_id' => $eventId,
            'upgrade_propensity' => round($upgradeScore, 3),
            'recommendations' => [
                [
                    'type' => 'vip_upgrade',
                    'show' => $upgradeScore > 0.4,
                    'priority' => $upgradeScore > 0.6 ? 'high' : 'medium',
                ],
                [
                    'type' => 'merchandise',
                    'show' => true,
                    'priority' => 'low',
                ],
                [
                    'type' => 'parking',
                    'show' => true,
                    'priority' => 'medium',
                ],
            ],
        ];
    }

    /**
     * Score an event for recommendation
     */
    protected function scoreEvent(
        $event,
        Collection $artistAffinities,
        Collection $genreAffinities,
        array $antiAffinities,
        Collection $pricePrefs,
        ?string $purchaseWindow
    ): float {
        $score = 0;

        // Artist affinity score
        $artistId = $event->artist_id ?? null;
        if ($artistId) {
            $artistAffinity = $artistAffinities->firstWhere('artist_id', $artistId);
            if ($artistAffinity) {
                $score += self::WEIGHTS['artist_affinity'] * $artistAffinity->affinity_score;
            }

            // Apply anti-affinity penalty
            if (isset($antiAffinities['artists'][$artistId])) {
                $score -= self::ANTI_AFFINITY_PENALTY * $antiAffinities['artists'][$artistId];
            }
        }

        // Genre affinity score
        $genreId = $event->genre_id ?? null;
        if ($genreId) {
            $genreAffinity = $genreAffinities->firstWhere('genre_id', $genreId);
            if ($genreAffinity) {
                $score += self::WEIGHTS['genre_affinity'] * $genreAffinity->affinity_score;
            }

            // Apply anti-affinity penalty
            if (isset($antiAffinities['genres'][$genreId])) {
                $score -= self::ANTI_AFFINITY_PENALTY * $antiAffinities['genres'][$genreId];
            }
        }

        // Price match score
        if ($pricePrefs->isNotEmpty()) {
            $eventPriceBand = $this->getEventPriceBand($event);
            $priceMatch = $pricePrefs->firstWhere('price_band', $eventPriceBand);
            if ($priceMatch) {
                $maxPurchases = $pricePrefs->max('purchase_count') ?: 1;
                $score += self::WEIGHTS['price_match'] * ($priceMatch->purchase_count / $maxPurchases);
            }
        }

        // Timing match score
        if ($purchaseWindow && isset($event->start_date)) {
            $daysUntilEvent = now()->diffInDays($event->start_date, false);
            $windowMatch = $this->matchesPurchaseWindow($daysUntilEvent, $purchaseWindow);
            $score += self::WEIGHTS['timing_match'] * $windowMatch;
        }

        // Recency boost for newer events
        if (isset($event->created_at)) {
            $daysSinceCreated = now()->diffInDays($event->created_at);
            $recencyBoost = max(0, 1 - ($daysSinceCreated / 30));
            $score += self::WEIGHTS['recency_boost'] * $recencyBoost;
        }

        return max(0, $score);
    }

    /**
     * Get human-readable recommendation reasons
     */
    protected function getRecommendationReasons($event, Collection $artistAffinities, Collection $genreAffinities, Collection $pricePrefs): array
    {
        $reasons = [];

        // Check artist affinity
        if (isset($event->artist_id)) {
            $artistAffinity = $artistAffinities->firstWhere('artist_id', $event->artist_id);
            if ($artistAffinity && $artistAffinity->affinity_score > 0.5) {
                $reasons[] = "You've shown strong interest in " . ($event->artist_name ?? 'this artist');
            }
        }

        // Check genre affinity
        if (isset($event->genre_id)) {
            $genreAffinity = $genreAffinities->firstWhere('genre_id', $event->genre_id);
            if ($genreAffinity && $genreAffinity->affinity_score > 0.3) {
                $reasons[] = "Matches your taste in " . ($event->genre_name ?? 'this genre');
            }
        }

        // Price match
        if ($pricePrefs->isNotEmpty()) {
            $eventPriceBand = $this->getEventPriceBand($event);
            if ($pricePrefs->firstWhere('price_band', $eventPriceBand)) {
                $reasons[] = "In your preferred price range";
            }
        }

        if (empty($reasons)) {
            $reasons[] = "Recommended for you";
        }

        return $reasons;
    }

    protected function getArtistAffinities(): Collection
    {
        return FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('affinity_score', '>', 0)
            ->orderByDesc('affinity_score')
            ->get();
    }

    protected function getGenreAffinities(): Collection
    {
        return FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('affinity_score', '>', 0)
            ->orderByDesc('affinity_score')
            ->get();
    }

    protected function getAntiAffinities(): array
    {
        $artistAnti = FsPersonAntiAffinityArtist::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('anti_affinity_score', '>', 0.3)
            ->pluck('anti_affinity_score', 'artist_id')
            ->toArray();

        $genreAnti = FsPersonAntiAffinityGenre::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('anti_affinity_score', '>', 0.3)
            ->pluck('anti_affinity_score', 'genre_id')
            ->toArray();

        return [
            'artists' => $artistAnti,
            'genres' => $genreAnti,
        ];
    }

    protected function getPricePreferences(): Collection
    {
        return FsPersonTicketPref::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->orderByDesc('purchase_count')
            ->get();
    }

    protected function getPurchaseWindowPreference(): ?string
    {
        $window = FsPersonPurchaseWindow::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->orderByDesc('preference_score')
            ->first();

        return $window?->window_type;
    }

    protected function getUpcomingEvents(array $options = []): Collection
    {
        $query = DB::table('events')
            ->where('tenant_id', $this->tenantId)
            ->where('start_date', '>', now())
            ->where('status', 'published');

        if (isset($options['days_ahead'])) {
            $query->where('start_date', '<', now()->addDays($options['days_ahead']));
        }

        if (isset($options['genre_ids'])) {
            $query->whereIn('genre_id', $options['genre_ids']);
        }

        return $query->limit($options['max_events'] ?? 100)->get();
    }

    protected function getUserPurchasedEventIds(): array
    {
        return DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('event_type', 'purchase')
            ->pluck('event_id')
            ->unique()
            ->toArray();
    }

    protected function getEventPriceBand($event): int
    {
        $price = $event->min_price ?? $event->price ?? 50;

        return match (true) {
            $price < 25 => 1,
            $price < 50 => 2,
            $price < 100 => 3,
            $price < 200 => 4,
            default => 5,
        };
    }

    protected function matchesPurchaseWindow(int $daysUntilEvent, string $preferredWindow): float
    {
        $windows = [
            'last_minute' => [0, 1],
            'week' => [2, 7],
            'two_weeks' => [8, 14],
            'month' => [15, 30],
            'early_bird' => [31, 365],
        ];

        if (!isset($windows[$preferredWindow])) {
            return 0.5;
        }

        [$min, $max] = $windows[$preferredWindow];

        if ($daysUntilEvent >= $min && $daysUntilEvent <= $max) {
            return 1.0; // Perfect match
        }

        // Partial match for adjacent windows
        $distance = min(abs($daysUntilEvent - $min), abs($daysUntilEvent - $max));
        return max(0, 1 - ($distance / 30));
    }

    /**
     * Invalidate recommendation cache
     */
    public function invalidateCache(): void
    {
        Cache::forget("recs:events:{$this->tenantId}:{$this->personId}:*");
        Cache::forget("recs:artists:{$this->tenantId}:{$this->personId}");
    }
}
