<?php

namespace App\Services\Tracking;

use App\Models\CoreCustomer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WinBackCampaignService
{
    protected int $tenantId;

    // Win-back tiers with configurations
    protected const TIERS = [
        'early_warning' => [
            'days_inactive' => [30, 60],
            'churn_risk' => ['medium', 'high'],
            'priority' => 1,
            'offer_type' => 'reminder',
            'discount_percent' => 0,
        ],
        'gentle_nudge' => [
            'days_inactive' => [61, 90],
            'churn_risk' => ['high', 'critical'],
            'priority' => 2,
            'offer_type' => 'soft_offer',
            'discount_percent' => 10,
        ],
        'win_back' => [
            'days_inactive' => [91, 180],
            'churn_risk' => ['high', 'critical'],
            'priority' => 3,
            'offer_type' => 'discount',
            'discount_percent' => 15,
        ],
        'last_chance' => [
            'days_inactive' => [181, 365],
            'churn_risk' => ['critical'],
            'priority' => 4,
            'offer_type' => 'aggressive_discount',
            'discount_percent' => 25,
        ],
    ];

    // Minimum LTV to qualify for win-back efforts
    protected const MIN_LTV_THRESHOLD = 50;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    /**
     * Identify customers eligible for win-back campaigns
     */
    public function identifyWinBackCandidates(): array
    {
        $candidates = [
            'early_warning' => [],
            'gentle_nudge' => [],
            'win_back' => [],
            'last_chance' => [],
        ];

        foreach (self::TIERS as $tier => $config) {
            $candidates[$tier] = $this->getCandidatesForTier($tier, $config);
        }

        return [
            'tenant_id' => $this->tenantId,
            'candidates' => $candidates,
            'summary' => [
                'early_warning' => count($candidates['early_warning']),
                'gentle_nudge' => count($candidates['gentle_nudge']),
                'win_back' => count($candidates['win_back']),
                'last_chance' => count($candidates['last_chance']),
                'total' => array_sum(array_map('count', $candidates)),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get candidates for a specific tier
     */
    protected function getCandidatesForTier(string $tier, array $config): array
    {
        [$minDays, $maxDays] = $config['days_inactive'];

        $query = CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('total_purchases', '>', 0)
            ->where('ltv', '>=', self::MIN_LTV_THRESHOLD)
            ->where('marketing_consent', true)
            ->where(function ($q) {
                $q->where('is_unsubscribed', false)
                    ->orWhereNull('is_unsubscribed');
            })
            ->whereNotNull('last_seen_at')
            ->whereRaw('last_seen_at < NOW() - INTERVAL ? DAY', [$minDays])
            ->whereRaw('last_seen_at >= NOW() - INTERVAL ? DAY', [$maxDays]);

        if (!empty($config['churn_risk'])) {
            $query->whereIn('churn_risk', $config['churn_risk']);
        }

        // Exclude recently contacted
        $query->where(function ($q) {
            $q->whereNull('last_winback_at')
                ->orWhereRaw('last_winback_at < NOW() - INTERVAL 14 DAY');
        });

        return $query->orderByDesc('ltv')
            ->limit(500)
            ->get()
            ->map(function ($customer) use ($tier, $config) {
                return $this->buildCandidateProfile($customer, $tier, $config);
            })
            ->toArray();
    }

    /**
     * Build candidate profile with personalized offer
     */
    protected function buildCandidateProfile(CoreCustomer $customer, string $tier, array $config): array
    {
        $daysSinceLastSeen = now()->diffInDays($customer->last_seen_at);
        $daysSinceLastPurchase = $customer->last_purchase_at
            ? now()->diffInDays($customer->last_purchase_at)
            : null;

        // Get their preferences for personalization
        $preferences = $this->getCustomerPreferences($customer->id);

        // Calculate optimal offer
        $offer = $this->calculateOptimalOffer($customer, $tier, $config);

        return [
            'person_id' => $customer->id,
            'email_hash' => $customer->email_hash,
            'tier' => $tier,
            'priority' => $config['priority'],

            // Inactivity metrics
            'days_inactive' => $daysSinceLastSeen,
            'days_since_purchase' => $daysSinceLastPurchase,
            'churn_risk' => $customer->churn_risk,

            // Value metrics
            'ltv' => $customer->ltv,
            'total_spent' => $customer->total_spent,
            'total_purchases' => $customer->total_purchases,
            'avg_order_value' => $customer->avg_order_value,

            // Engagement history
            'rfm_segment' => $customer->rfm_segment,
            'previous_rfm' => $this->getPreviousRfmSegment($customer->id),

            // Personalization
            'preferences' => $preferences,
            'recommended_events' => $this->getRecommendedEvents($customer->id, 3),

            // Offer details
            'offer' => $offer,

            // Optimal contact
            'preferred_channel' => $this->getPreferredChannel($customer->id),
            'optimal_send_time' => $this->getOptimalSendTime($customer->id),
        ];
    }

    /**
     * Calculate optimal offer based on customer value and tier
     */
    protected function calculateOptimalOffer(CoreCustomer $customer, string $tier, array $config): array
    {
        $baseDiscount = $config['discount_percent'];

        // Adjust discount based on LTV
        $ltvMultiplier = match (true) {
            $customer->ltv >= 1000 => 1.5, // VIP gets better offer
            $customer->ltv >= 500 => 1.3,
            $customer->ltv >= 200 => 1.1,
            default => 1.0,
        };

        $adjustedDiscount = min(40, $baseDiscount * $ltvMultiplier);

        // Determine offer type
        $offerType = $config['offer_type'];
        $offerDetails = match ($offerType) {
            'reminder' => [
                'type' => 'content',
                'headline' => 'We miss you!',
                'cta' => 'See what\'s new',
            ],
            'soft_offer' => [
                'type' => 'early_access',
                'headline' => 'Exclusive early access for you',
                'cta' => 'Get early access',
                'benefit' => 'Pre-sale access to upcoming events',
            ],
            'discount' => [
                'type' => 'percentage',
                'headline' => "Welcome back! Here's {$adjustedDiscount}% off",
                'cta' => 'Claim your discount',
                'discount_percent' => round($adjustedDiscount),
                'valid_days' => 14,
            ],
            'aggressive_discount' => [
                'type' => 'percentage',
                'headline' => "We really miss you! {$adjustedDiscount}% off any event",
                'cta' => 'Come back to us',
                'discount_percent' => round($adjustedDiscount),
                'valid_days' => 30,
                'bonus' => 'Free shipping on merchandise',
            ],
            default => [
                'type' => 'generic',
                'headline' => 'We\'d love to see you again',
                'cta' => 'Browse events',
            ],
        };

        return array_merge($offerDetails, [
            'expires_at' => now()->addDays($offerDetails['valid_days'] ?? 14)->toIso8601String(),
            'code' => $this->generateOfferCode($customer->id, $tier),
        ]);
    }

    /**
     * Generate unique offer code
     */
    protected function generateOfferCode(int $personId, string $tier): string
    {
        $prefix = match ($tier) {
            'early_warning' => 'MISSYOU',
            'gentle_nudge' => 'COMEBACK',
            'win_back' => 'WINBACK',
            'last_chance' => 'LASTCHANCE',
            default => 'OFFER',
        };

        return $prefix . strtoupper(substr(md5($personId . now()->timestamp), 0, 6));
    }

    /**
     * Get customer preferences for personalization
     */
    protected function getCustomerPreferences(int $personId): array
    {
        // Top artists
        $topArtists = DB::table('fs_person_affinity_artist')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('affinity_score')
            ->limit(3)
            ->pluck('artist_id')
            ->toArray();

        // Top genres
        $topGenres = DB::table('fs_person_affinity_genre')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('affinity_score')
            ->limit(3)
            ->pluck('genre_id')
            ->toArray();

        // Price preference
        $pricePrefs = DB::table('fs_person_ticket_pref')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('purchase_count')
            ->first();

        return [
            'top_artist_ids' => $topArtists,
            'top_genre_ids' => $topGenres,
            'preferred_price_band' => $pricePrefs->price_band ?? null,
        ];
    }

    /**
     * Get recommended events for win-back email
     */
    protected function getRecommendedEvents(int $personId, int $limit = 3): array
    {
        try {
            $recs = RecommendationService::for($this->tenantId, $personId)
                ->getEventRecommendations($limit);

            return array_map(fn($r) => [
                'event_id' => $r['event_id'],
                'score' => $r['score'],
            ], $recs['recommendations'] ?? []);
        } catch (\Exception $e) {
            Log::warning("Failed to get recommendations for win-back: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get preferred contact channel
     */
    protected function getPreferredChannel(int $personId): string
    {
        $channel = DB::table('fs_person_channel_affinity')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->orderByDesc('conversion_rate')
            ->first();

        return $channel->channel ?? 'email';
    }

    /**
     * Get optimal send time based on activity patterns
     */
    protected function getOptimalSendTime(int $personId): array
    {
        $pattern = DB::table('fs_person_activity_pattern')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->first();

        if ($pattern) {
            return [
                'hour' => $pattern->preferred_hour ?? 10,
                'day' => $pattern->is_weekend_buyer ? 'saturday' : 'tuesday',
            ];
        }

        return [
            'hour' => 10,
            'day' => 'tuesday',
        ];
    }

    /**
     * Get previous RFM segment (to show decline)
     */
    protected function getPreviousRfmSegment(int $personId): ?string
    {
        // This would query a history table if available
        return null;
    }

    /**
     * Execute win-back campaign (mark as contacted)
     */
    public function markAsContacted(array $personIds, string $tier, string $campaignId): int
    {
        return CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereIn('id', $personIds)
            ->update([
                'last_winback_at' => now(),
                'last_winback_tier' => $tier,
                'last_winback_campaign_id' => $campaignId,
            ]);
    }

    /**
     * Track win-back conversion
     */
    public function trackConversion(int $personId, string $campaignId, float $orderValue): void
    {
        DB::table('winback_conversions')->insert([
            'tenant_id' => $this->tenantId,
            'person_id' => $personId,
            'campaign_id' => $campaignId,
            'order_value' => $orderValue,
            'converted_at' => now(),
        ]);

        // Reset churn risk
        CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('id', $personId)
            ->update([
                'churn_risk' => 'low',
                'last_winback_converted_at' => now(),
            ]);
    }

    /**
     * Get win-back campaign analytics
     */
    public function getCampaignAnalytics(string $campaignId): array
    {
        $sent = DB::table('core_customers')
            ->where('tenant_id', $this->tenantId)
            ->where('last_winback_campaign_id', $campaignId)
            ->count();

        $conversions = DB::table('winback_conversions')
            ->where('tenant_id', $this->tenantId)
            ->where('campaign_id', $campaignId)
            ->get();

        $totalRevenue = $conversions->sum('order_value');
        $conversionCount = $conversions->count();

        return [
            'campaign_id' => $campaignId,
            'sent' => $sent,
            'conversions' => $conversionCount,
            'conversion_rate' => $sent > 0 ? round($conversionCount / $sent * 100, 2) : 0,
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $conversionCount > 0 ? round($totalRevenue / $conversionCount, 2) : 0,
            'roi' => $this->calculateCampaignRoi($campaignId, $sent, $totalRevenue),
        ];
    }

    protected function calculateCampaignRoi(string $campaignId, int $sent, float $revenue): float
    {
        // Assume $0.01 per email sent
        $cost = $sent * 0.01;
        if ($cost == 0) {
            return 0;
        }

        return round(($revenue - $cost) / $cost * 100, 2);
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStats(): array
    {
        $atRisk = CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('total_purchases', '>', 0)
            ->whereIn('churn_risk', ['high', 'critical'])
            ->count();

        $lapsed = CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('total_purchases', '>', 0)
            ->whereNotNull('last_seen_at')
            ->whereRaw('last_seen_at < NOW() - INTERVAL 90 DAY')
            ->count();

        $recentlyWonBack = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereNotNull('last_winback_converted_at')
            ->whereRaw('last_winback_converted_at > NOW() - INTERVAL 30 DAY')
            ->count();

        $potentialRevenue = CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('total_purchases', '>', 0)
            ->whereIn('churn_risk', ['high', 'critical'])
            ->sum('avg_order_value');

        return [
            'at_risk_customers' => $atRisk,
            'lapsed_customers' => $lapsed,
            'recently_won_back' => $recentlyWonBack,
            'potential_revenue_at_risk' => $potentialRevenue,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
