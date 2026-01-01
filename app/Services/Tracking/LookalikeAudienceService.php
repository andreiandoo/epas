<?php

namespace App\Services\Tracking;

use App\Models\CoreCustomer;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LookalikeAudienceService
{
    protected int $tenantId;

    // Feature weights for similarity calculation
    protected const FEATURE_WEIGHTS = [
        'genre_overlap' => 0.25,
        'artist_overlap' => 0.20,
        'price_band_match' => 0.15,
        'purchase_frequency_match' => 0.10,
        'avg_order_value_match' => 0.10,
        'engagement_match' => 0.10,
        'location_match' => 0.05,
        'age_match' => 0.05,
    ];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    /**
     * Find lookalike audiences based on seed customers
     */
    public function findLookalikes(
        array $seedPersonIds,
        int $limit = 1000,
        float $minSimilarity = 0.5,
        array $options = []
    ): array {
        $cacheKey = "lookalike:{$this->tenantId}:" . md5(json_encode($seedPersonIds) . $limit . $minSimilarity);

        if (($options['cached'] ?? true) && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Build seed profile (aggregate of all seed customers)
        $seedProfile = $this->buildSeedProfile($seedPersonIds);

        if (empty($seedProfile['genre_affinities']) && empty($seedProfile['artist_affinities'])) {
            return [
                'seed_count' => count($seedPersonIds),
                'lookalikes' => [],
                'message' => 'Insufficient data in seed audience',
            ];
        }

        // Find candidates (exclude seed customers)
        $candidates = $this->findCandidates($seedPersonIds, $seedProfile, $options);

        // Score each candidate
        $scoredCandidates = [];
        foreach ($candidates as $candidate) {
            $similarity = $this->calculateSimilarity($candidate, $seedProfile);

            if ($similarity >= $minSimilarity) {
                $scoredCandidates[] = [
                    'person_id' => $candidate->id,
                    'similarity_score' => round($similarity, 4),
                    'match_breakdown' => $this->getMatchBreakdown($candidate, $seedProfile),
                ];
            }
        }

        // Sort by similarity and limit
        usort($scoredCandidates, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);
        $lookalikes = array_slice($scoredCandidates, 0, $limit);

        $result = [
            'seed_count' => count($seedPersonIds),
            'seed_profile' => [
                'top_genres' => array_slice($seedProfile['genre_affinities'], 0, 5),
                'top_artists' => array_slice($seedProfile['artist_affinities'], 0, 5),
                'avg_order_value' => $seedProfile['avg_order_value'],
                'purchase_frequency' => $seedProfile['purchase_frequency'],
            ],
            'lookalikes' => $lookalikes,
            'total_found' => count($scoredCandidates),
            'min_similarity' => $minSimilarity,
            'generated_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, 3600);

        return $result;
    }

    /**
     * Find lookalikes for a single person
     */
    public function findSimilarPersons(int $personId, int $limit = 20): array
    {
        return $this->findLookalikes([$personId], $limit, 0.4, ['cached' => true]);
    }

    /**
     * Create lookalike audience from segment
     */
    public function createFromSegment(int $segmentId, int $expansionFactor = 5): array
    {
        // Get segment members
        $seedIds = DB::table('audience_segment_members')
            ->where('tenant_id', $this->tenantId)
            ->where('segment_id', $segmentId)
            ->pluck('person_id')
            ->toArray();

        if (empty($seedIds)) {
            return ['error' => 'Segment has no members'];
        }

        $targetSize = count($seedIds) * $expansionFactor;

        return $this->findLookalikes($seedIds, $targetSize, 0.5);
    }

    /**
     * Create lookalike from high-value customers
     */
    public function createFromHighValueCustomers(
        float $minLtv = 500,
        int $limit = 1000
    ): array {
        $seedIds = CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('ltv', '>=', $minLtv)
            ->where('total_purchases', '>=', 2)
            ->pluck('id')
            ->toArray();

        return $this->findLookalikes($seedIds, $limit, 0.5);
    }

    /**
     * Create lookalike from event purchasers
     */
    public function createFromEventPurchasers(int $eventId, int $limit = 1000): array
    {
        $seedIds = DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('event_type', 'purchase')
            ->distinct()
            ->pluck('person_id')
            ->toArray();

        return $this->findLookalikes($seedIds, $limit, 0.5);
    }

    /**
     * Build aggregate profile from seed customers
     */
    protected function buildSeedProfile(array $seedPersonIds): array
    {
        // Genre affinities (averaged)
        $genreAffinities = FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
            ->whereIn('person_id', $seedPersonIds)
            ->groupBy('genre_id')
            ->selectRaw('genre_id, AVG(affinity_score) as avg_score, COUNT(*) as person_count')
            ->orderByDesc('avg_score')
            ->get()
            ->mapWithKeys(fn($row) => [$row->genre_id => $row->avg_score])
            ->toArray();

        // Artist affinities (averaged)
        $artistAffinities = FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->whereIn('person_id', $seedPersonIds)
            ->groupBy('artist_id')
            ->selectRaw('artist_id, AVG(affinity_score) as avg_score, COUNT(*) as person_count')
            ->orderByDesc('avg_score')
            ->get()
            ->mapWithKeys(fn($row) => [$row->artist_id => $row->avg_score])
            ->toArray();

        // Price preferences
        $priceBands = DB::table('fs_person_ticket_pref')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('person_id', $seedPersonIds)
            ->groupBy('price_band')
            ->selectRaw('price_band, SUM(purchase_count) as total')
            ->orderByDesc('total')
            ->pluck('total', 'price_band')
            ->toArray();

        // Customer metrics
        $metrics = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereIn('id', $seedPersonIds)
            ->selectRaw('
                AVG(avg_order_value) as avg_aov,
                AVG(total_purchases) as avg_purchases,
                AVG(engagement_score) as avg_engagement,
                AVG(TIMESTAMPDIFF(DAY, first_purchase_at, last_purchase_at) / NULLIF(total_purchases - 1, 0)) as avg_purchase_interval
            ')
            ->first();

        // Location distribution
        $locations = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereIn('id', $seedPersonIds)
            ->whereNotNull('country')
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'country')
            ->toArray();

        // Age distribution
        $ageRanges = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereIn('id', $seedPersonIds)
            ->whereNotNull('age_range')
            ->groupBy('age_range')
            ->selectRaw('age_range, COUNT(*) as count')
            ->orderByDesc('count')
            ->pluck('count', 'age_range')
            ->toArray();

        return [
            'genre_affinities' => $genreAffinities,
            'artist_affinities' => $artistAffinities,
            'price_bands' => $priceBands,
            'avg_order_value' => $metrics->avg_aov ?? 0,
            'purchase_frequency' => $metrics->avg_purchases ?? 0,
            'avg_engagement' => $metrics->avg_engagement ?? 0,
            'avg_purchase_interval' => $metrics->avg_purchase_interval ?? 0,
            'locations' => $locations,
            'age_ranges' => $ageRanges,
        ];
    }

    /**
     * Find candidate customers for similarity matching
     */
    protected function findCandidates(array $excludeIds, array $seedProfile, array $options): Collection
    {
        $query = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereNotIn('id', $excludeIds);

        // Must have marketing consent if specified
        if ($options['require_consent'] ?? true) {
            $query->where('marketing_consent', true)
                ->where(function ($q) {
                    $q->where('is_unsubscribed', false)
                        ->orWhereNull('is_unsubscribed');
                });
        }

        // Require some engagement
        if ($options['min_visits'] ?? 1) {
            $query->where('total_visits', '>=', $options['min_visits']);
        }

        // Optionally require purchase history
        if ($options['require_purchase'] ?? false) {
            $query->where('total_purchases', '>', 0);
        }

        // Limit candidates to process
        $maxCandidates = $options['max_candidates'] ?? 5000;

        return $query->limit($maxCandidates)->get();
    }

    /**
     * Calculate similarity score between candidate and seed profile
     */
    protected function calculateSimilarity($candidate, array $seedProfile): float
    {
        $scores = [];

        // Genre overlap (Jaccard-like with affinity weights)
        $scores['genre_overlap'] = $this->calculateGenreOverlap(
            $candidate->id,
            $seedProfile['genre_affinities']
        );

        // Artist overlap
        $scores['artist_overlap'] = $this->calculateArtistOverlap(
            $candidate->id,
            $seedProfile['artist_affinities']
        );

        // Price band match
        $scores['price_band_match'] = $this->calculatePriceBandMatch(
            $candidate->id,
            $seedProfile['price_bands']
        );

        // Purchase frequency match
        $scores['purchase_frequency_match'] = $this->calculateNumericMatch(
            $candidate->total_purchases ?? 0,
            $seedProfile['purchase_frequency'],
            10 // max difference for 0 score
        );

        // AOV match
        $scores['avg_order_value_match'] = $this->calculateNumericMatch(
            $candidate->avg_order_value ?? 0,
            $seedProfile['avg_order_value'],
            100 // max difference for 0 score
        );

        // Engagement match
        $scores['engagement_match'] = $this->calculateNumericMatch(
            $candidate->engagement_score ?? 0,
            $seedProfile['avg_engagement'],
            50
        );

        // Location match
        $scores['location_match'] = $this->calculateLocationMatch(
            $candidate->country,
            $seedProfile['locations']
        );

        // Age match
        $scores['age_match'] = $this->calculateAgeMatch(
            $candidate->age_range,
            $seedProfile['age_ranges']
        );

        // Weighted sum
        $totalScore = 0;
        foreach (self::FEATURE_WEIGHTS as $feature => $weight) {
            $totalScore += ($scores[$feature] ?? 0) * $weight;
        }

        return $totalScore;
    }

    /**
     * Calculate genre affinity overlap
     */
    protected function calculateGenreOverlap(int $personId, array $seedGenres): float
    {
        if (empty($seedGenres)) {
            return 0;
        }

        $personGenres = FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->where('affinity_score', '>', 0)
            ->pluck('affinity_score', 'genre_id')
            ->toArray();

        if (empty($personGenres)) {
            return 0;
        }

        // Calculate weighted overlap
        $overlapScore = 0;
        $maxScore = 0;

        foreach ($seedGenres as $genreId => $seedScore) {
            $maxScore += $seedScore;
            if (isset($personGenres[$genreId])) {
                // Score based on both having affinity (geometric mean)
                $overlapScore += sqrt($seedScore * $personGenres[$genreId]);
            }
        }

        return $maxScore > 0 ? $overlapScore / $maxScore : 0;
    }

    /**
     * Calculate artist affinity overlap
     */
    protected function calculateArtistOverlap(int $personId, array $seedArtists): float
    {
        if (empty($seedArtists)) {
            return 0;
        }

        $personArtists = FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->where('affinity_score', '>', 0)
            ->pluck('affinity_score', 'artist_id')
            ->toArray();

        if (empty($personArtists)) {
            return 0;
        }

        $overlapScore = 0;
        $maxScore = 0;

        foreach ($seedArtists as $artistId => $seedScore) {
            $maxScore += $seedScore;
            if (isset($personArtists[$artistId])) {
                $overlapScore += sqrt($seedScore * $personArtists[$artistId]);
            }
        }

        return $maxScore > 0 ? $overlapScore / $maxScore : 0;
    }

    /**
     * Calculate price band preference match
     */
    protected function calculatePriceBandMatch(int $personId, array $seedPriceBands): float
    {
        if (empty($seedPriceBands)) {
            return 0.5; // Neutral if no data
        }

        $personPriceBands = DB::table('fs_person_ticket_pref')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->pluck('purchase_count', 'price_band')
            ->toArray();

        if (empty($personPriceBands)) {
            return 0.3; // Low score if no purchase history
        }

        // Normalize both distributions
        $seedTotal = array_sum($seedPriceBands);
        $personTotal = array_sum($personPriceBands);

        $similarity = 0;
        $allBands = array_unique(array_merge(array_keys($seedPriceBands), array_keys($personPriceBands)));

        foreach ($allBands as $band) {
            $seedPct = ($seedPriceBands[$band] ?? 0) / $seedTotal;
            $personPct = ($personPriceBands[$band] ?? 0) / $personTotal;
            $similarity += min($seedPct, $personPct);
        }

        return $similarity;
    }

    /**
     * Calculate numeric value match with tolerance
     */
    protected function calculateNumericMatch(float $value, float $target, float $maxDiff): float
    {
        if ($target == 0) {
            return $value == 0 ? 1.0 : 0.5;
        }

        $diff = abs($value - $target);
        return max(0, 1 - ($diff / $maxDiff));
    }

    /**
     * Calculate location match
     */
    protected function calculateLocationMatch(?string $country, array $seedLocations): float
    {
        if (!$country || empty($seedLocations)) {
            return 0.5;
        }

        $total = array_sum($seedLocations);
        if ($total == 0) {
            return 0.5;
        }

        // Score based on how common this location is in seed
        $locationCount = $seedLocations[$country] ?? 0;
        return $locationCount / $total;
    }

    /**
     * Calculate age range match
     */
    protected function calculateAgeMatch(?string $ageRange, array $seedAgeRanges): float
    {
        if (!$ageRange || empty($seedAgeRanges)) {
            return 0.5;
        }

        $total = array_sum($seedAgeRanges);
        if ($total == 0) {
            return 0.5;
        }

        $ageCount = $seedAgeRanges[$ageRange] ?? 0;
        return $ageCount / $total;
    }

    /**
     * Get breakdown of match factors
     */
    protected function getMatchBreakdown($candidate, array $seedProfile): array
    {
        return [
            'genre_overlap' => round($this->calculateGenreOverlap($candidate->id, $seedProfile['genre_affinities']), 3),
            'artist_overlap' => round($this->calculateArtistOverlap($candidate->id, $seedProfile['artist_affinities']), 3),
            'price_match' => round($this->calculatePriceBandMatch($candidate->id, $seedProfile['price_bands']), 3),
        ];
    }

    /**
     * Export lookalike audience for ad platforms
     */
    public function exportForAdPlatform(array $lookalikes, string $platform): array
    {
        $personIds = array_column($lookalikes['lookalikes'] ?? [], 'person_id');

        $customers = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereIn('id', $personIds)
            ->get();

        return match ($platform) {
            'facebook' => $this->formatForFacebook($customers),
            'google' => $this->formatForGoogle($customers),
            'tiktok' => $this->formatForTikTok($customers),
            default => ['error' => 'Unsupported platform'],
        };
    }

    protected function formatForFacebook(Collection $customers): array
    {
        return [
            'schema' => ['EMAIL', 'PHONE', 'FN', 'LN', 'CT', 'ST', 'COUNTRY'],
            'data' => $customers->map(fn($c) => [
                $c->email_hash, // Already hashed
                $c->phone_hash,
                null, // First name - would need to hash
                null, // Last name
                $c->city,
                $c->region,
                $c->country,
            ])->toArray(),
        ];
    }

    protected function formatForGoogle(Collection $customers): array
    {
        return [
            'customerMatchUserListMetadata' => [
                'userList' => [
                    'name' => 'Lookalike Audience',
                    'membershipLifeSpan' => 365,
                ],
            ],
            'operations' => $customers->map(fn($c) => [
                'create' => [
                    'userIdentifier' => [
                        'hashedEmail' => $c->email_hash,
                        'hashedPhoneNumber' => $c->phone_hash,
                    ],
                ],
            ])->toArray(),
        ];
    }

    protected function formatForTikTok(Collection $customers): array
    {
        return [
            'advertiser_id' => null, // Would need to be provided
            'custom_audience_id' => null,
            'action' => 'ADD',
            'id_type' => 'EMAIL_SHA256',
            'id_list' => $customers->pluck('email_hash')->filter()->toArray(),
        ];
    }
}
