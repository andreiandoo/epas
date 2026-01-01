<?php

namespace App\Services\Tracking;

use App\Models\FeatureStore\FsPersonActivityPattern;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use App\Models\FeatureStore\FsPersonAntiAffinityArtist;
use App\Models\FeatureStore\FsPersonAntiAffinityGenre;
use App\Models\FeatureStore\FsPersonChannelAffinity;
use App\Models\FeatureStore\FsPersonDaily;
use App\Models\FeatureStore\FsPersonEmailMetrics;
use App\Models\FeatureStore\FsPersonPurchaseWindow;
use App\Models\FeatureStore\FsPersonTicketPref;
use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\PersonTagAssignment;
use App\Models\Tracking\TxEvent;
use Illuminate\Support\Facades\Cache;

class PersonProfileService
{
    protected int $tenantId;
    protected int $personId;
    protected ?CoreCustomer $person = null;

    public function __construct(int $tenantId, int $personId)
    {
        $this->tenantId = $tenantId;
        $this->personId = $personId;
    }

    /**
     * Create a new profile service for a person.
     */
    public static function for(int $tenantId, int $personId): self
    {
        return new self($tenantId, $personId);
    }

    /**
     * Get the complete person profile.
     */
    public function getFullProfile(bool $cached = true): array
    {
        $cacheKey = "person_profile:{$this->tenantId}:{$this->personId}";

        if ($cached) {
            return Cache::remember($cacheKey, now()->addMinutes(15), fn() => $this->buildProfile());
        }

        return $this->buildProfile();
    }

    /**
     * Build the complete profile.
     */
    protected function buildProfile(): array
    {
        $this->person = CoreCustomer::find($this->personId);

        if (!$this->person) {
            return ['error' => 'Person not found'];
        }

        return [
            'person_id' => $this->personId,
            'tenant_id' => $this->tenantId,
            'generated_at' => now()->toIso8601String(),

            // Core identity
            'identity' => $this->getIdentity(),

            // Lifecycle & segments
            'lifecycle' => $this->getLifecycle(),

            // Content affinities
            'affinities' => $this->getAffinities(),

            // Anti-affinities (what to avoid)
            'anti_affinities' => $this->getAntiAffinities(),

            // Purchase preferences
            'purchase_preferences' => $this->getPurchasePreferences(),

            // Temporal patterns
            'temporal_patterns' => $this->getTemporalPatterns(),

            // Channel preferences
            'channel_preferences' => $this->getChannelPreferences(),

            // Email engagement
            'email_engagement' => $this->getEmailEngagement(),

            // Tags
            'tags' => $this->getTags(),

            // Activity summary
            'activity_summary' => $this->getActivitySummary(),

            // Scores
            'scores' => $this->getScores(),

            // Recommendations
            'recommendations' => $this->getRecommendations(),
        ];
    }

    protected function getIdentity(): array
    {
        return [
            'uuid' => $this->person->uuid,
            'display_name' => $this->person->getDisplayName(),
            'has_email' => !empty($this->person->email_hash),
            'has_phone' => !empty($this->person->phone_hash),
            'country' => $this->person->country_code,
            'city' => $this->person->city,
            'language' => $this->person->language,
            'primary_device' => $this->person->primary_device,
        ];
    }

    protected function getLifecycle(): array
    {
        return [
            'segment' => $this->person->customer_segment,
            'rfm_segment' => $this->person->rfm_segment,
            'rfm_scores' => [
                'recency' => $this->person->rfm_recency_score,
                'frequency' => $this->person->rfm_frequency_score,
                'monetary' => $this->person->rfm_monetary_score,
                'total' => $this->person->getRfmTotalScore(),
            ],
            'first_seen' => $this->person->first_seen_at?->toIso8601String(),
            'last_seen' => $this->person->last_seen_at?->toIso8601String(),
            'first_purchase' => $this->person->first_purchase_at?->toIso8601String(),
            'last_purchase' => $this->person->last_purchase_at?->toIso8601String(),
            'days_since_last_purchase' => $this->person->days_since_last_purchase,
            'cohort_month' => $this->person->cohort_month,
        ];
    }

    protected function getAffinities(): array
    {
        $artistAffinities = FsPersonAffinityArtist::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->orderByDesc('affinity_score')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'artist_id' => $a->artist_id,
                'score' => round($a->affinity_score, 2),
                'views' => $a->views_count,
                'purchases' => $a->purchases_count,
                'attended' => $a->attendance_count,
            ])
            ->toArray();

        $genreAffinities = FsPersonAffinityGenre::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->orderByDesc('affinity_score')
            ->limit(10)
            ->get()
            ->map(fn($g) => [
                'genre' => $g->genre,
                'score' => round($g->affinity_score, 2),
                'views' => $g->views_count,
                'purchases' => $g->purchases_count,
            ])
            ->toArray();

        return [
            'top_artists' => $artistAffinities,
            'top_genres' => $genreAffinities,
        ];
    }

    protected function getAntiAffinities(): array
    {
        $excludedArtists = FsPersonAntiAffinityArtist::getExcludedArtists(
            $this->tenantId,
            $this->personId
        );

        $excludedGenres = FsPersonAntiAffinityGenre::getExcludedGenres(
            $this->tenantId,
            $this->personId
        );

        return [
            'excluded_artist_ids' => $excludedArtists,
            'excluded_genres' => $excludedGenres,
            'count' => count($excludedArtists) + count($excludedGenres),
        ];
    }

    protected function getPurchasePreferences(): array
    {
        $ticketPrefs = FsPersonTicketPref::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->get();

        $categoryPrefs = $ticketPrefs->where('ticket_category', '!=', null)
            ->sortByDesc('preference_score')
            ->map(fn($p) => [
                'category' => $p->ticket_category,
                'purchases' => $p->purchases_count,
                'avg_price' => $p->avg_price,
                'preference_pct' => round($p->preference_score * 100, 1),
            ])
            ->values()
            ->toArray();

        $priceBandPrefs = $ticketPrefs->where('price_band', '!=', null)
            ->sortByDesc('preference_score')
            ->map(fn($p) => [
                'band' => $p->price_band,
                'purchases' => $p->purchases_count,
                'preference_pct' => round($p->preference_score * 100, 1),
            ])
            ->values()
            ->toArray();

        // Determine dominant preferences
        $dominantCategory = $categoryPrefs[0]['category'] ?? null;
        $dominantPriceBand = $priceBandPrefs[0]['band'] ?? null;

        return [
            'total_orders' => $this->person->total_orders,
            'total_spent' => $this->person->total_spent,
            'avg_order_value' => $this->person->average_order_value,
            'dominant_category' => $dominantCategory,
            'dominant_price_band' => $dominantPriceBand,
            'category_preferences' => $categoryPrefs,
            'price_band_preferences' => $priceBandPrefs,
        ];
    }

    protected function getTemporalPatterns(): array
    {
        $activityPattern = FsPersonActivityPattern::getProfile($this->tenantId, $this->personId);
        $purchaseWindows = FsPersonPurchaseWindow::getProfile($this->tenantId, $this->personId);

        return [
            'activity' => $activityPattern,
            'purchase_windows' => $purchaseWindows,
        ];
    }

    protected function getChannelPreferences(): array
    {
        return FsPersonChannelAffinity::getProfile($this->tenantId, $this->personId);
    }

    protected function getEmailEngagement(): array
    {
        $metrics = FsPersonEmailMetrics::getProfile($this->tenantId, $this->personId);

        if (!$metrics) {
            return [
                'subscribed' => $this->person->email_subscribed,
                'total_sent' => $this->person->emails_sent,
                'total_opened' => $this->person->emails_opened,
                'total_clicked' => $this->person->emails_clicked,
                'open_rate' => $this->person->email_open_rate,
                'click_rate' => $this->person->email_click_rate,
            ];
        }

        return array_merge($metrics, [
            'subscribed' => $this->person->email_subscribed,
        ]);
    }

    protected function getTags(): array
    {
        return PersonTagAssignment::getTagsForPerson($this->tenantId, $this->personId);
    }

    protected function getActivitySummary(): array
    {
        // Get recent activity from feature store
        $recentStats = FsPersonDaily::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('date', '>=', now()->subDays(30))
            ->get();

        return [
            'total_visits' => $this->person->total_visits,
            'total_pageviews' => $this->person->total_pageviews,
            'events_viewed' => $this->person->total_events_viewed,
            'events_attended' => $this->person->total_events_attended,
            'last_30_days' => [
                'views' => $recentStats->sum('views_count'),
                'carts' => $recentStats->sum('carts_count'),
                'checkouts' => $recentStats->sum('checkouts_count'),
                'purchases' => $recentStats->sum('purchases_count'),
                'spent' => $recentStats->sum('gross_amount'),
            ],
            'has_cart_abandoned' => $this->person->has_cart_abandoned,
            'last_cart_abandoned' => $this->person->last_cart_abandoned_at?->toIso8601String(),
        ];
    }

    protected function getScores(): array
    {
        return [
            'health_score' => $this->person->health_score,
            'health_label' => $this->person->getHealthScoreLabel(),
            'engagement_score' => $this->person->engagement_score,
            'purchase_likelihood' => $this->person->purchase_likelihood_score,
            'churn_risk' => $this->person->churn_risk_score,
            'predicted_ltv' => $this->person->predicted_ltv,
            'lifetime_value' => $this->person->lifetime_value,
        ];
    }

    protected function getRecommendations(): array
    {
        $recommendations = [];

        // Based on fatigue
        $emailMetrics = FsPersonEmailMetrics::forTenant($this->tenantId)
            ->forPerson($this->personId)
            ->first();

        if ($emailMetrics) {
            if ($emailMetrics->fatigue_score > 70) {
                $recommendations[] = [
                    'type' => 'email',
                    'action' => 'reduce_frequency',
                    'reason' => 'High email fatigue detected',
                ];
            }

            if ($emailMetrics->preferred_send_hours) {
                $recommendations[] = [
                    'type' => 'email',
                    'action' => 'optimize_timing',
                    'best_hours' => $emailMetrics->preferred_send_hours,
                ];
            }
        }

        // Based on activity patterns
        $activityPattern = FsPersonActivityPattern::forTenant($this->tenantId)
            ->forPerson($this->personId)
            ->first();

        if ($activityPattern?->is_weekend_buyer) {
            $recommendations[] = [
                'type' => 'campaign',
                'action' => 'target_weekend',
                'reason' => 'User is a weekend buyer',
            ];
        }

        // Based on purchase windows
        $preferredWindow = FsPersonPurchaseWindow::getPreferredWindow($this->tenantId, $this->personId);
        if ($preferredWindow) {
            $recommendations[] = [
                'type' => 'campaign',
                'action' => "target_{$preferredWindow}",
                'reason' => "User prefers {$preferredWindow} purchases",
            ];
        }

        // Based on channel
        $channelProfile = FsPersonChannelAffinity::getProfile($this->tenantId, $this->personId);
        if ($channelProfile['primary_channel']) {
            $recommendations[] = [
                'type' => 'channel',
                'action' => 'prioritize',
                'channel' => $channelProfile['primary_channel'],
                'reason' => 'Highest converting channel for this user',
            ];
        }

        return $recommendations;
    }

    /**
     * Get a summary profile (lighter version).
     */
    public function getSummaryProfile(): array
    {
        $this->person = CoreCustomer::find($this->personId);

        if (!$this->person) {
            return ['error' => 'Person not found'];
        }

        return [
            'person_id' => $this->personId,
            'display_name' => $this->person->getDisplayName(),
            'segment' => $this->person->customer_segment,
            'rfm_segment' => $this->person->rfm_segment,
            'health_score' => $this->person->health_score,
            'total_orders' => $this->person->total_orders,
            'total_spent' => $this->person->total_spent,
            'lifetime_value' => $this->person->lifetime_value,
            'last_seen' => $this->person->last_seen_at?->diffForHumans(),
            'last_purchase' => $this->person->last_purchase_at?->diffForHumans(),
            'tags_count' => PersonTagAssignment::forTenant($this->tenantId)
                ->forPerson($this->personId)
                ->active()
                ->count(),
        ];
    }

    /**
     * Invalidate cached profile.
     */
    public function invalidateCache(): void
    {
        Cache::forget("person_profile:{$this->tenantId}:{$this->personId}");
    }
}
