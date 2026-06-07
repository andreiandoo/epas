<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Activity;
use App\Services\Activities\SlotResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Public marketplace API for the Activities module.
 *
 * Three endpoints:
 *   GET /activities                       — paginated list with filters
 *   GET /activities/{slug}                — single activity detail + variants
 *   GET /activities/{slug}/slots?date=…   — bookable time slots for one date
 *
 * Scoped by marketplace client (resolved from the API key by marketplace.auth
 * middleware). Activities for marketplaces that haven't enabled the
 * `activities-module` microservice are still served IF rows exist (the
 * microservice toggle gates admin write access, not public read access — same
 * pattern as events). In practice the table is empty for non-activated
 * marketplaces, so this is a no-op there.
 */
class ActivitiesController extends BaseController
{
    /**
     * List published activities for the marketplace, with optional filters.
     *
     * Query params:
     *   - city (slug)
     *   - category (slug — matches marketplace_category_id parent OR subcategory)
     *   - search (free text on title)
     *   - max_price_ron (filters cheapest_price_cents)
     *   - sort: 'recent' | 'cheapest' | 'soon' (default 'recent')
     *   - page, per_page (default 1, 20; max 50)
     *   - locale (default 'ro')
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $query = Activity::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->with([
                'venue:id,name,city,address,slug',
                'city:id,name,slug',
                'category:id,name,slug,parent_id',
                'subcategory:id,name,slug,parent_id',
                'organizer:id,name,slug,logo',
            ]);

        if ($citySlug = $request->query('city')) {
            $query->whereHas('city', fn ($q) => $q->where('slug', $citySlug));
        }

        if ($catSlug = $request->query('category')) {
            $query->where(function ($q) use ($catSlug) {
                $q->whereHas('category', fn ($qq) => $qq->where('slug', $catSlug))
                    ->orWhereHas('subcategory', fn ($qq) => $qq->where('slug', $catSlug));
            });
        }

        // Explicit id list (used by the guide "activities block" shortcode for
        // hand-picked activities). Preserves the editor's chosen order.
        $idsOrder = null;
        if ($idsParam = $request->query('ids')) {
            $idsOrder = array_values(array_filter(array_map('intval', explode(',', (string) $idsParam))));
            if ($idsOrder) {
                $query->whereIn('id', $idsOrder);
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            // Title is JSONB translatable; search the RO key with LIKE since it's
            // the primary locale on bilete.online. Loose match for partial words.
            $query->whereRaw("LOWER(title->>'ro') LIKE ?", ['%' . mb_strtolower($search) . '%']);
        }

        if ($maxPriceRon = (int) $request->query('max_price_ron', 0)) {
            $query->where('cheapest_price_cents', '<=', $maxPriceRon * 100);
        }

        $sort = $request->query('sort', 'recent');
        match ($sort) {
            'cheapest' => $query->orderByRaw('cheapest_price_cents IS NULL ASC')->orderBy('cheapest_price_cents', 'asc'),
            'soon'     => $query->orderByRaw('next_session_at IS NULL ASC')->orderBy('next_session_at', 'asc'),
            default    => $query->orderBy('is_featured', 'desc')->orderBy('updated_at', 'desc'),
        };

        // When a hand-picked id list is given, honour that exact order (Postgres).
        if ($idsOrder) {
            $query->reorder()->orderByRaw('array_position(ARRAY[' . implode(',', $idsOrder) . ']::bigint[], id)');
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $paginator = $query->paginate($perPage);

        return $this->success([
            'items' => $paginator->getCollection()->map(fn ($a) => $this->summarisePayload($a, $locale))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Detail page payload — everything the public /activitate/{slug} needs in
     * one round-trip, except slot availability (separate endpoint for caching).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $activity = Activity::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->with([
                'venue:id,name,city,state,address,slug,lat,lng,phone,email,website_url',
                'city:id,name,slug,latitude,longitude',
                'category:id,name,slug,parent_id',
                'subcategory:id,name,slug,parent_id',
                // commission_rate + default_commission_mode included so the
                // detail payload's organizer.commission_* fields populate
                // without triggering an extra round-trip to the model. The
                // helper safeOrganizerCommission() in turn falls back to
                // marketplace_clients.commission_rate when these are null.
                'organizer:id,name,slug,logo,description,website,commission_rate,default_commission_mode,marketplace_client_id',
                'organizer.marketplaceClient:id,commission_rate,commission_mode',
                'schedules',
                'scheduleExceptions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                // Cross-sell — only published siblings + their basic display info.
                // Capped at 6 in the controller transform; pulling more here is
                // cheap (single JOIN) so we can decide cap downstream.
                'relatedActivities' => fn ($q) => $q
                    ->where('is_published', true)
                    ->orderBy('activity_related.sort_order'),
                'relatedActivities.city:id,name,slug',
                'relatedActivities.category:id,name,slug,parent_id',
            ])
            ->first();

        if (! $activity) {
            return response()->json(['success' => false, 'message' => 'Activity not found'], 404);
        }

        // Track view count without bumping updated_at on the activity itself.
        \Illuminate\Support\Facades\DB::table('activities')
            ->where('id', $activity->id)
            ->increment('views_count');

        return $this->success([
            'activity' => $this->detailPayload($activity, $locale),
        ]);
    }

    /**
     * Bookable slots for one calendar date (Y-m-d). Returns even non-bookable
     * slots (greyed out client-side) with an `unavailable_reason`.
     */
    public function slots(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $activity = Activity::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->with(['schedules', 'scheduleExceptions'])
            ->first();

        if (! $activity) {
            return response()->json(['success' => false, 'message' => 'Activity not found'], 404);
        }

        $dateRaw = $request->query('date', now('Europe/Bucharest')->toDateString());
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $dateRaw, 'Europe/Bucharest');
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Invalid date'], 422);
        }

        $slots = SlotResolver::slotsFor($activity, $date);

        return $this->success([
            'date'  => $date->toDateString(),
            'slots' => $slots->values(),
        ]);
    }

    /**
     * List view of bookable dates over a horizon (defaults to next 14 days).
     * Lets the public calendar picker disable closed days without a fetch per day.
     */
    public function availableDates(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $activity = Activity::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->with(['schedules', 'scheduleExceptions'])
            ->first();

        if (! $activity) {
            return response()->json(['success' => false, 'message' => 'Activity not found'], 404);
        }

        $start = CarbonImmutable::now('Europe/Bucharest')->startOfDay();
        $horizon = max(1, min(90, (int) $request->query('days', 14)));
        $end = $start->copy()->addDays($horizon - 1);

        $dates = SlotResolver::bookableDatesBetween($activity, $start, $end);

        return $this->success([
            'start' => $start->toDateString(),
            'end'   => $end->toDateString(),
            'dates' => $dates->values(),
        ]);
    }

    // ============================================================
    // PAYLOAD SHAPERS
    // ============================================================

    /**
     * Compact card payload for listings.
     */
    private function summarisePayload(Activity $activity, string $locale): array
    {
        return [
            'id'              => $activity->id,
            'slug'            => $activity->slug,
            'title'           => $this->translate($activity->title, $locale),
            'subtitle'        => $this->translate($activity->subtitle, $locale),
            'short_description' => $this->translate($activity->short_description, $locale),
            'cover_image_url' => $this->resolveStorageUrl($activity->cover_image_url),
            'cheapest_price_cents' => $activity->cheapest_price_cents,
            'duration_minutes' => (int) $activity->duration_minutes,
            'capacity_per_slot' => (int) $activity->capacity_per_slot,
            'city' => $activity->city ? [
                'id'   => $activity->city->id,
                'slug' => $activity->city->slug,
                'name' => $this->translate($activity->city->name, $locale),
            ] : null,
            'category' => $activity->category ? [
                'id'   => $activity->category->id,
                'slug' => $activity->category->slug,
                'name' => $this->translate($activity->category->name, $locale),
            ] : null,
            'organizer' => $activity->organizer ? array_merge([
                'id'              => $activity->organizer->id,
                'slug'            => $activity->organizer->slug,
                'name'            => $activity->organizer->name,
            ], $this->safeOrganizerCommission($activity->organizer)) : null,
            'flags' => [
                'is_featured'    => (bool) $activity->is_featured,
                'is_indoor'      => (bool) $activity->is_indoor,
                'is_outdoor'     => (bool) $activity->is_outdoor,
                'is_kid_friendly' => (bool) $activity->is_kid_friendly,
                'is_accessible'  => (bool) $activity->is_accessible,
            ],
        ];
    }

    /**
     * Full detail payload for the activity page.
     */
    private function detailPayload(Activity $activity, string $locale): array
    {
        return array_merge(
            $this->summarisePayload($activity, $locale),
            [
                'description'       => $this->translate($activity->description, $locale),
                'hero_image_url'    => $this->resolveStorageUrl($activity->hero_image_url),
                'gallery'           => collect((array) $activity->gallery)
                    ->map(fn ($g) => $this->resolveStorageUrl($g))
                    ->filter()
                    ->values()
                    ->all(),
                'meeting_point'     => $activity->meeting_point,
                'cancellation_policy' => $activity->cancellation_policy,
                'included_items'    => (array) ($activity->included_items ?? []),
                'not_included'      => (array) ($activity->not_included ?? []),
                'requirements'      => (array) ($activity->requirements ?? []),
                'languages_offered' => (array) ($activity->languages_offered ?? []),
                'difficulty_level'  => $activity->difficulty_level,
                'age_min'           => $activity->age_min,
                'age_max'           => $activity->age_max,
                'venue' => $activity->venue ? [
                    'id'       => $activity->venue->id,
                    'name'     => is_array($activity->venue->name)
                        ? ($activity->venue->name[$locale] ?? $activity->venue->name['en'] ?? 'Venue')
                        : $activity->venue->name,
                    'slug'     => $activity->venue->slug,
                    'address'  => $activity->venue->address,
                    'city'     => $activity->venue->city,
                    'state'    => $activity->venue->state,
                    'lat'      => $activity->venue->lat,
                    'lng'      => $activity->venue->lng,
                ] : null,
                'subcategory' => $activity->subcategory ? [
                    'id'   => $activity->subcategory->id,
                    'slug' => $activity->subcategory->slug,
                    'name' => $this->translate($activity->subcategory->name, $locale),
                ] : null,
                'variants' => $activity->variants->map(fn ($v) => [
                    'id'             => $v->id,
                    'name'           => $this->translate($v->name, $locale),
                    'description'    => $this->translate($v->description, $locale),
                    'sku'            => $v->sku,
                    'price_cents'    => (int) $v->price_cents,
                    'currency'       => $v->currency,
                    'min_age'        => $v->min_age,
                    'max_age'        => $v->max_age,
                    'capacity_share' => (int) $v->capacity_share,
                    'min_per_order'  => (int) $v->min_per_order,
                    'max_per_order'  => (int) $v->max_per_order,
                    'perks'          => (array) ($v->perks ?? []),
                ])->values()->all(),
                // Schedules + exceptions are exposed so the front-end can render
                // a static "Program" panel without an extra round-trip.
                'schedule' => $activity->schedules
                    ->where('is_active', true)
                    ->map(fn ($s) => [
                        'day_of_week' => (int) $s->day_of_week,
                        'open_time'   => is_object($s->open_time) ? $s->open_time->format('H:i') : substr((string) $s->open_time, 0, 5),
                        'close_time'  => is_object($s->close_time) ? $s->close_time->format('H:i') : substr((string) $s->close_time, 0, 5),
                    ])
                    ->values()
                    ->all(),
                'schedule_exceptions' => $activity->scheduleExceptions->map(fn ($x) => [
                    'date'      => $x->exception_date instanceof \DateTimeInterface ? $x->exception_date->format('Y-m-d') : $x->exception_date,
                    'is_closed' => (bool) $x->is_closed,
                    'reason'    => $x->reason,
                ])->values()->all(),
                'booking_window' => [
                    'lead_time_hours'      => (int) $activity->booking_lead_time_hours,
                    'max_advance_days'     => (int) $activity->booking_max_advance_days,
                    'min_participants'     => (int) $activity->min_participants,
                    'max_participants'     => (int) $activity->max_participants,
                ],
                'seo' => [
                    'title'       => is_array($activity->seo) ? ($activity->seo['title_' . $locale] ?? null) : null,
                    'description' => is_array($activity->seo) ? ($activity->seo['description_' . $locale] ?? null) : null,
                    'body_title'  => $this->translate($activity->seo_body_title, $locale),
                    'body'        => $this->translate($activity->seo_body, $locale),
                ],
                'faqs' => (array) ($activity->faqs ?? []),
                // Admin-managed cross-sell (Conexiuni tab).
                'related' => $activity->relatedActivities
                    ->take(6)
                    ->map(fn (Activity $rel) => $this->relatedCardPayload($rel, $locale))
                    ->values()
                    ->all(),
                // Three independent rails on the public page, each rendered only
                // when it has ≥1 result:
                //   1. Same organizer (excl. self + already-linked)
                //   2. Same city + same category
                //   3. Same city + any other category
                'recommendations' => $this->buildRecommendationTiers($activity, $locale, 6),
                // Customer reviews summary + a few recent approved reviews.
                // Defensive: returns an empty/zeroed shape if the activity_id
                // column hasn't been migrated yet, so the page never breaks.
                'reviews' => $this->buildReviewsPayload($activity),
            ]
        );
    }

    /**
     * Aggregate + recent customer reviews for an activity.
     *
     * Reads marketplace_customer_reviews (status = approved) scoped to this
     * activity. Activity reviews are distinguished by activity_id (added in a
     * later migration); event reviews keep activity_id NULL and are never
     * counted here. Wrapped in try/catch so a missing column / empty table
     * yields a safe zeroed payload instead of a 500 on the public page.
     *
     * @return array{
     *   average: float, count: int,
     *   distribution: array<int,int>,
     *   detailed_averages: array<string,float>,
     *   recommend_pct: int,
     *   items: array<int, array>
     * }
     */
    private function buildReviewsPayload(Activity $activity): array
    {
        $empty = [
            'average'           => 0.0,
            'count'             => 0,
            'distribution'      => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            'detailed_averages' => [],
            'recommend_pct'     => 0,
            'items'             => [],
        ];

        try {
            $base = DB::table('marketplace_customer_reviews')
                ->where('marketplace_client_id', $activity->marketplace_client_id)
                ->where('activity_id', $activity->id)
                ->where('status', 'approved');

            $count = (clone $base)->count();
            if ($count === 0) {
                return $empty;
            }

            $average = round((float) (clone $base)->avg('rating'), 2);

            $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            foreach ((clone $base)->select('rating', DB::raw('COUNT(*) as c'))->groupBy('rating')->get() as $row) {
                $r = (int) $row->rating;
                if (isset($dist[$r])) {
                    $dist[$r] = (int) $row->c;
                }
            }

            // Portable boolean aggregate. A plain ->where('recommend', true) breaks
            // on Postgres because Laravel binds the bool as integer 1 (operator
            // does not exist: boolean = integer). CASE WHEN works on pg + mysql.
            $recommendCount = (int) (clone $base)->selectRaw('SUM(CASE WHEN recommend THEN 1 ELSE 0 END) AS c')->value('c');
            $recommendPct = $count > 0 ? (int) round(($recommendCount / $count) * 100) : 0;

            // Aggregate detailed_ratings (json) → per-aspect average. Resilient:
            // a parsing hiccup must not zero the whole summary.
            $detailedAverages = [];
            try {
                $detailedSums = [];
                $detailedCounts = [];
                foreach ((clone $base)->whereNotNull('detailed_ratings')->pluck('detailed_ratings') as $json) {
                    $data = is_array($json) ? $json : json_decode((string) $json, true);
                    if (! is_array($data)) {
                        continue;
                    }
                    foreach ($data as $aspect => $value) {
                        if (! is_numeric($value)) {
                            continue;
                        }
                        $detailedSums[$aspect] = ($detailedSums[$aspect] ?? 0) + (float) $value;
                        $detailedCounts[$aspect] = ($detailedCounts[$aspect] ?? 0) + 1;
                    }
                }
                foreach ($detailedSums as $aspect => $sum) {
                    $detailedAverages[$aspect] = round($sum / max(1, $detailedCounts[$aspect]), 1);
                }
            } catch (\Throwable $e) {
                $detailedAverages = [];
            }

            // Recent review cards. Resilient for the same reason (join / date
            // formatting must not blank out the rating summary).
            $items = [];
            try {
                $items = (clone $base)
                    ->leftJoin('marketplace_customers', 'marketplace_customers.id', '=', 'marketplace_customer_reviews.marketplace_customer_id')
                    ->orderByDesc('marketplace_customer_reviews.created_at')
                    ->limit(9)
                    ->get([
                        'marketplace_customer_reviews.rating',
                        'marketplace_customer_reviews.text',
                        'marketplace_customer_reviews.created_at',
                        'marketplace_customer_reviews.is_anonymous',
                        'marketplace_customers.first_name',
                        'marketplace_customers.last_name',
                    ])
                    ->map(function ($r) {
                        $anon = filter_var($r->is_anonymous, FILTER_VALIDATE_BOOLEAN);
                        $name = trim((string) ($r->first_name ?? '') . ' ' . substr((string) ($r->last_name ?? ''), 0, 1));
                        if ($anon || $name === '') {
                            $name = 'Client bilete.online';
                        }
                        $when = '';
                        try {
                            $when = $r->created_at ? \Carbon\Carbon::parse($r->created_at)->locale('ro')->isoFormat('MMMM YYYY') : '';
                        } catch (\Throwable $e) {
                            $when = '';
                        }

                        return [
                            'rating'   => (int) $r->rating,
                            'text'     => (string) $r->text,
                            'name'     => $name,
                            'initial'  => mb_strtoupper(mb_substr($name, 0, 1)),
                            'meta'     => trim($when . ' · rezervare verificată', ' ·'),
                        ];
                    })
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                $items = [];
            }

            return [
                'average'           => $average,
                'count'             => $count,
                'distribution'      => $dist,
                'detailed_averages' => $detailedAverages,
                'recommend_pct'     => $recommendPct,
                'items'             => $items,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Build the three independent recommendation rails for the public page.
     *
     *   same_organizer       — other activities by the same operator
     *   same_city_same_cat   — same city, same category (siblings)
     *   same_city            — same city, any other category
     *
     * Each tier is capped at $perTier. Tiers don't share items: anything
     * surfaced in tier N is excluded from tier N+1, plus self and admin-
     * managed Conexiuni links are excluded everywhere. The PHP page only
     * renders a section when the array isn't empty — empty tiers go silent.
     *
     * @return array{
     *   same_organizer: array<int, array>,
     *   same_city_same_cat: array<int, array>,
     *   same_city: array<int, array>,
     * }
     */
    private function buildRecommendationTiers(Activity $activity, string $locale, int $perTier = 6): array
    {
        $excluded = collect([$activity->id])
            ->merge($activity->relatedActivities->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $base = Activity::query()
            ->where('marketplace_client_id', $activity->marketplace_client_id)
            ->where('is_published', true)
            ->whereNotIn('id', $excluded)
            ->with(['city:id,name,slug', 'category:id,name,slug,parent_id']);

        $tiers = [
            'same_organizer'     => collect(),
            'same_city_same_cat' => collect(),
            'same_city'          => collect(),
        ];

        // Tier 1: same organizer
        if ($activity->marketplace_organizer_id) {
            $tiers['same_organizer'] = (clone $base)
                ->where('marketplace_organizer_id', $activity->marketplace_organizer_id)
                ->limit($perTier)
                ->get();
        }

        // Running exclusion set — anything in earlier tiers shouldn't repeat.
        $seenIds = $tiers['same_organizer']->pluck('id')->all();

        // Tier 2: same city + same category
        if ($activity->marketplace_city_id && $activity->marketplace_category_id) {
            $tiers['same_city_same_cat'] = (clone $base)
                ->where('marketplace_city_id', $activity->marketplace_city_id)
                ->where('marketplace_category_id', $activity->marketplace_category_id)
                ->whereNotIn('id', $seenIds)
                ->limit($perTier)
                ->get();
            $seenIds = array_merge($seenIds, $tiers['same_city_same_cat']->pluck('id')->all());
        }

        // Tier 3: same city + any other category
        if ($activity->marketplace_city_id) {
            $tiers['same_city'] = (clone $base)
                ->where('marketplace_city_id', $activity->marketplace_city_id)
                ->whereNotIn('id', $seenIds)
                ->limit($perTier)
                ->get();
        }

        return [
            'same_organizer'     => $tiers['same_organizer']->map(fn (Activity $rel) => $this->relatedCardPayload($rel, $locale))->values()->all(),
            'same_city_same_cat' => $tiers['same_city_same_cat']->map(fn (Activity $rel) => $this->relatedCardPayload($rel, $locale))->values()->all(),
            'same_city'          => $tiers['same_city']->map(fn (Activity $rel) => $this->relatedCardPayload($rel, $locale))->values()->all(),
        ];
    }

    /**
     * Shared card-shape payload used by `related` + `recommended` rails.
     */
    private function relatedCardPayload(Activity $rel, string $locale): array
    {
        return [
            'id' => $rel->id,
            'slug' => $rel->slug,
            'title' => $this->translate($rel->title, $locale),
            'cover_image_url' => $this->resolveStorageUrl($rel->cover_image_url),
            'cheapest_price_cents' => $rel->cheapest_price_cents,
            'duration_minutes' => (int) $rel->duration_minutes,
            'city' => $rel->city ? [
                'slug' => $rel->city->slug,
                'name' => $this->translate($rel->city->name, $locale),
            ] : null,
            'category' => $rel->category ? [
                'slug' => $rel->category->slug,
                'name' => $this->translate($rel->category->name, $locale),
            ] : null,
        ];
    }

    private function translate($value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? (reset($value) ?: null);
        }
        return $value !== '' && $value !== null ? (string) $value : null;
    }

    /**
     * Resolve organizer's effective commission settings DEFENSIVELY.
     *
     * The straight calls to getEffectiveCommissionRate() / Mode() can blow
     * up the entire detailPayload (→ /activities/{slug} 500 → activitate.php
     * 404) when:
     *   - the eager-loaded organizer was hydrated with only id/name/slug/...
     *     so commission_rate isn't on the model (returns null)
     *   - or the cascade falls to $organizer->marketplaceClient which itself
     *     isn't loaded → triggers a lazy-load that may resolve to null or to
     *     a row without commission_rate populated
     *
     * Wrap both calls in try/catch + fall back to zero (no commission). The
     * caller already treats organizer.commission_* as advisory data — if
     * zero, the cart simply doesn't add an on-top line, which is what
     * legacy items (before this field existed) did anyway. Non-breaking.
     */
    private function safeOrganizerCommission($organizer): array
    {
        try {
            return [
                'commission_rate' => method_exists($organizer, 'getEffectiveCommissionRate')
                    ? (float) $organizer->getEffectiveCommissionRate()
                    : (float) ($organizer->commission_rate ?? 0),
                'commission_mode' => method_exists($organizer, 'getEffectiveCommissionMode')
                    ? (string) $organizer->getEffectiveCommissionMode()
                    : (string) ($organizer->default_commission_mode ?? 'included'),
            ];
        } catch (\Throwable $e) {
            \Log::warning('Failed to resolve organizer commission', [
                'organizer_id' => $organizer->id ?? null,
                'error'        => $e->getMessage(),
            ]);
            return [
                'commission_rate' => 0.0,
                'commission_mode' => 'included',
            ];
        }
    }

    private function resolveStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return Storage::disk('public')->url($path);
    }
}
