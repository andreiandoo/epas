<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Activity;
use App\Services\Activities\SlotResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                'organizer:id,name,slug,logo,description,website',
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
            'organizer' => $activity->organizer ? [
                'id'   => $activity->organizer->id,
                'slug' => $activity->organizer->slug,
                'name' => $activity->organizer->name,
            ] : null,
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
                // Cross-sell shortlist (capped at 6) for the "Other experiences" rail.
                'related' => $activity->relatedActivities
                    ->take(6)
                    ->map(fn (Activity $rel) => [
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
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private function translate($value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? (reset($value) ?: null);
        }
        return $value !== '' && $value !== null ? (string) $value : null;
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
