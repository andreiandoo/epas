<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Models\Gamification\CustomerPoints;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceCustomerBeneficiary;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Personalised activity recommendations for /cont/recomandari.
 *
 * Pulls the candidate pool of recently published activities and scores them
 * server-side based on:
 *   - explicit preferences (settings.interests.preferred_cities + event_categories)
 *   - history (cities + categories from previous orders)
 *   - family signals (beneficiaries with kid ages)
 *   - lifestyle (budget bucket / radius / moment from settings)
 *   - eligibility for points redemption
 * Each card is annotated with a 0–99 match score plus a human-readable list
 * of reasons that the UI surfaces as "De ce ți-o recomandăm?".
 */
class RecommendationsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $locale  = $request->query('locale', 'ro');
        $limit   = max(1, min(40, (int) $request->query('limit', 24)));

        // ---- Read taste profile ----
        $settings  = (array) ($customer->settings ?? []);
        $interests = (array) ($settings['interests'] ?? []);
        $lifestyle = (array) ($interests['lifestyle'] ?? []);

        $prefCities      = array_values(array_filter((array) ($interests['preferred_cities'] ?? [])));
        $prefCategories  = array_values(array_filter((array) ($interests['event_categories'] ?? [])));
        $budget          = $lifestyle['budget'] ?? null;
        $radius          = $lifestyle['radius'] ?? null;
        $moment          = $lifestyle['moment'] ?? null;

        // ---- Read points balance ----
        $points = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
        $pointsBalance = $points ? (int) $points->current_balance : 0;
        $minRedeem = 100; // Fall back; real value comes via GamificationConfig at /customer/rewards/config

        // ---- Family signals ----
        $beneficiaries = MarketplaceCustomerBeneficiary::where('marketplace_customer_id', $customer->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();
        $hasKids = $beneficiaries->contains(fn ($b) => $b->birth_date && $b->birth_date->age <= 14);
        $familyAges = $beneficiaries
            ->filter(fn ($b) => $b->birth_date && $b->birth_date->age <= 14)
            ->map(fn ($b) => $b->birth_date->age)
            ->values()
            ->all();

        // ---- History signals ----
        [$historyCities, $historyCategories] = $this->buildHistorySignals($customer);

        // ---- Candidate pool ----
        $candidatePool = Activity::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->with([
                'venue:id,name,city,slug',
                'city:id,name,slug',
                'category:id,name,slug,parent_id',
                'organizer:id,name,slug',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get();

        // ---- Score & rank ----
        $scored = $candidatePool->map(function (Activity $a) use (
            $locale, $prefCities, $prefCategories, $budget, $moment,
            $historyCities, $historyCategories,
            $hasKids, $familyAges, $pointsBalance, $minRedeem
        ) {
            return $this->scoreActivity(
                $a, $locale,
                $prefCities, $prefCategories, $budget, $moment,
                $historyCities, $historyCategories,
                $hasKids, $familyAges, $pointsBalance, $minRedeem
            );
        })->sortByDesc('match_score')->values()->take($limit);

        // ---- Aggregate stats ----
        $stats = [
            'good_match_count' => $scored->filter(fn ($r) => $r['match_score'] >= 70)->count(),
            'with_points_count'=> $scored->filter(fn ($r) => $r['can_use_points'])->count(),
            'family_count'     => $scored->filter(fn ($r) => $r['is_family'])->count(),
            'pool_size'        => $candidatePool->count(),
        ];

        // ---- Profile signals card (right-hero) ----
        $primaryCity = $prefCities[0] ?? ($historyCities[0] ?? '');
        $primaryInterest = $prefCategories[0] ?? ($historyCategories[0] ?? '');
        $familyLabel = $hasKids
            ? ('copii ' . (count($familyAges) > 1
                ? (min($familyAges) . '–' . max($familyAges))
                : (string) $familyAges[0]))
            : ($beneficiaries->count() > 0 ? $beneficiaries->count() . ' beneficiari' : 'doar tu');

        return $this->success([
            'items' => $scored->values()->all(),
            'stats' => $stats,
            'signals' => [
                'city'     => $primaryCity,
                'interest' => $primaryInterest,
                'family'   => $familyLabel,
                'points'   => $pointsBalance,
            ],
            'engine' => [
                'pref_cities'      => $prefCities,
                'pref_categories'  => $prefCategories,
                'budget'           => $budget,
                'radius'           => $radius,
                'moment'           => $moment,
                'has_history'      => ! empty($historyCities) || ! empty($historyCategories),
            ],
        ]);
    }

    /**
     * Score a single activity 0–99 with human reasons.
     */
    protected function scoreActivity(
        Activity $a, string $locale,
        array $prefCities, array $prefCategories, ?string $budget, ?string $moment,
        array $historyCities, array $historyCategories,
        bool $hasKids, array $familyAges, int $pointsBalance, int $minRedeem
    ): array {
        $score = 30;       // base — everything starts equally interesting
        $reasons = [];
        $reasonTag = 'profile';

        $citySlug   = $a->city?->slug ?? '';
        $cityName   = $this->translate($a->city?->name, $locale);
        $catSlug    = $a->category?->slug ?? '';
        $catName    = $this->translate($a->category?->name, $locale);
        $priceLei   = $a->cheapest_price_cents ? (int) round($a->cheapest_price_cents / 100) : null;

        // Explicit city preference
        if (in_array($citySlug, $prefCities, true) || in_array($cityName, $prefCities, true)) {
            $score += 28;
            $reasons[] = 'Orașul tău preferat (' . $cityName . ')';
        }

        // Explicit category preference (highest weight — stated intent)
        if (in_array($catSlug, $prefCategories, true) || in_array($catName, $prefCategories, true)) {
            $score += 32;
            $reasons[] = 'Categorie pe care ai marcat-o ca preferată';
        }

        // History — same city / category from past orders
        if (! in_array($citySlug, $prefCities, true) && in_array($citySlug, $historyCities, true)) {
            $score += 12;
            $reasons[] = 'Ai fost la activități în ' . $cityName;
            $reasonTag = 'history';
        }
        if (! in_array($catSlug, $prefCategories, true) && in_array($catSlug, $historyCategories, true)) {
            $score += 18;
            $reasons[] = 'Ai mai cumpărat în categoria ' . mb_strtolower($catName);
            $reasonTag = 'history';
        }

        // Family signal
        $isFamily = (bool) $a->is_kid_friendly;
        if ($isFamily && $hasKids) {
            $score += 15;
            $ageHint = ! empty($familyAges)
                ? ' (potrivit pentru ' . min($familyAges) . '–' . max($familyAges) . ' ani)'
                : '';
            $reasons[] = 'Activitate pentru copii' . $ageHint;
            $reasonTag = 'family';
        }

        // Points-redeem signal
        $canUsePoints = $pointsBalance >= $minRedeem;
        if ($canUsePoints) {
            $score += 4;
            if (empty($reasons)) {
                $reasons[] = 'Poți aplica reducere din punctele bonus';
                $reasonTag = 'points';
            }
        }

        // Budget bucket
        $bucket = null;
        if ($priceLei !== null) {
            if ($priceLei < 50)        $bucket = 'low';
            elseif ($priceLei <= 120)  $bucket = 'mid';
            else                        $bucket = 'high';
        }
        if ($budget && $bucket === $budget) {
            $score += 6;
            $reasons[] = 'Se încadrează în bugetul tău';
        }

        // Moment / weekend / weather
        if ($moment === 'weekend' && $a->is_outdoor) {
            $score += 4;
            if (empty($reasons)) { $reasons[] = 'Bun pentru weekend'; $reasonTag = 'weather'; }
        } elseif ($moment === 'afterwork' && $a->is_indoor) {
            $score += 3;
            if (empty($reasons)) { $reasons[] = 'Indoor — bun după program'; }
        }

        // Featured nudge (small — never decisive)
        if ($a->is_featured) $score += 3;

        // Default reason when nothing matched
        if (empty($reasons)) {
            $reasons[] = 'Activitate populară pe care credem că o vei aprecia.';
        }

        $score = min(99, max(15, $score));

        return [
            'id'             => $a->id,
            'slug'           => $a->slug,
            'title'          => $this->translate($a->title, $locale),
            'short_description' => $this->translate($a->short_description, $locale),
            'image'          => $a->cover_image_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($a->cover_image_url) : null,
            'url'            => $a->slug ? '/activitate/' . $a->slug : '#',
            'city'           => $cityName ?: '',
            'city_slug'      => $citySlug,
            'category'       => $catName ?: '',
            'category_slug'  => $catSlug,
            'price_from_lei' => $priceLei,
            'price_label'    => $priceLei !== null ? ('de la ' . $priceLei . ' lei') : '',
            'budget_bucket'  => $bucket,
            'match_score'    => (int) $score,
            'reasons'        => $reasons,
            'reason_primary' => $reasonTag,
            'can_use_points' => $canUsePoints,
            'is_family'      => $isFamily,
            'is_outdoor'     => (bool) $a->is_outdoor,
            'is_indoor'      => (bool) $a->is_indoor,
        ];
    }

    /**
     * Collect distinct cities + categories the customer has bought from in
     * the past 12 months. Cached per customer (5 min).
     */
    protected function buildHistorySignals(MarketplaceCustomer $customer): array
    {
        $cacheKey = 'mc_history_signals:' . $customer->id;
        return Cache::remember($cacheKey, 300, function () use ($customer) {
            $cities = [];
            $cats   = [];
            try {
                Order::where('marketplace_customer_id', $customer->id)
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->with(['orderItems.event.venue', 'orderItems.event.category'])
                    ->limit(200)
                    ->get()
                    ->each(function ($order) use (&$cities, &$cats) {
                        foreach ($order->orderItems ?? [] as $item) {
                            $ev = $item->event ?? null;
                            if (! $ev) continue;
                            $city = $ev->venue?->city ?? null;
                            if (is_string($city) && $city !== '') $cities[] = $city;
                            $catSlug = $ev->category?->slug ?? null;
                            if (is_string($catSlug) && $catSlug !== '') $cats[] = $catSlug;
                        }
                    });
            } catch (\Throwable $e) {}
            return [array_values(array_unique($cities)), array_values(array_unique($cats))];
        });
    }

    protected function translate($value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? reset($value) ?: null;
        }
        return $value === null ? null : (string) $value;
    }
}
