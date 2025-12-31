<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerSegment;
use App\Models\Event;
use App\Services\Tracking\TxAudienceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Audience Builder API Controller
 *
 * Provides endpoints for building and querying audiences
 * based on tracking data and user affinities.
 */
class TxAudienceController extends Controller
{
    /**
     * Build an audience based on criteria.
     *
     * POST /api/tx/audiences/build
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function build(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'criteria' => 'required|array|min:1',
            'criteria.*.type' => 'required|string|in:artist_affinity,genre_affinity,price_band,ticket_preference,min_attendance,min_purchases,min_spent,active_days,viewed_events,not_purchased_events,abandoned_checkout',
            'limit' => 'nullable|integer|min:1|max:10000',
            'return_persons' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $builder = TxAudienceBuilder::forTenant($request->input('tenant_id'));

        // Apply criteria
        foreach ($request->input('criteria') as $criterion) {
            $builder = $this->applyCriterion($builder, $criterion);
        }

        if ($request->filled('limit')) {
            $builder->limit($request->input('limit'));
        }

        // Return results
        if ($request->boolean('return_persons')) {
            $persons = $builder->getPersons();
            return response()->json([
                'success' => true,
                'count' => $persons->count(),
                'persons' => $persons->map(fn ($p) => [
                    'id' => $p->id,
                    'email' => $p->email,
                    'first_name' => $p->first_name,
                    'last_name' => $p->last_name,
                ]),
            ]);
        }

        $personIds = $builder->getPersonIds();
        return response()->json([
            'success' => true,
            'count' => $personIds->count(),
            'person_ids' => $personIds,
        ]);
    }

    /**
     * Get propensity scores for an event.
     *
     * POST /api/tx/audiences/propensity
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function propensity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'event_id' => 'required|integer|exists:events,id',
            'limit' => 'nullable|integer|min:1|max:10000',
            'min_score' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Load event with artists and genres
        $event = Event::with(['artists:id', 'eventGenres:id,name'])
            ->find($request->input('event_id'));

        if (!$event) {
            return response()->json([
                'success' => false,
                'error' => 'Event not found',
            ], 404);
        }

        // Get average ticket price
        $avgPrice = $event->ticketTypes()->avg('price') ?? 50.0;

        $builder = TxAudienceBuilder::forTenant($request->input('tenant_id'));

        $scores = $builder->getPropensityScores(
            $event->id,
            $event->artists->pluck('id')->toArray(),
            $event->eventGenres->pluck('name')->toArray(),
            (float) $avgPrice
        );

        // Apply filters
        if ($request->filled('min_score')) {
            $scores = $scores->filter(fn ($s) => $s['propensity_score'] >= $request->input('min_score'));
        }

        if ($request->filled('limit')) {
            $scores = $scores->take($request->input('limit'));
        }

        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'count' => $scores->count(),
            'scores' => $scores->values(),
        ]);
    }

    /**
     * Save an audience as a segment.
     *
     * POST /api/tx/audiences/save
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function save(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'criteria' => 'required|array|min:1',
            'criteria.*.type' => 'required|string',
            'is_dynamic' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $builder = TxAudienceBuilder::forTenant($request->input('tenant_id'));

        foreach ($request->input('criteria') as $criterion) {
            $builder = $this->applyCriterion($builder, $criterion);
        }

        $segment = $builder->saveAsSegment(
            $request->input('name'),
            $request->input('description'),
            $request->boolean('is_dynamic', true)
        );

        return response()->json([
            'success' => true,
            'segment' => [
                'id' => $segment->id,
                'name' => $segment->name,
                'description' => $segment->description,
                'member_count' => $segment->member_count,
                'is_dynamic' => $segment->is_dynamic,
                'created_at' => $segment->created_at,
            ],
        ], 201);
    }

    /**
     * Recalculate a dynamic segment.
     *
     * POST /api/tx/audiences/segments/{id}/recalculate
     *
     * @param int $id
     * @return JsonResponse
     */
    public function recalculate(int $id): JsonResponse
    {
        $segment = CustomerSegment::find($id);

        if (!$segment) {
            return response()->json([
                'success' => false,
                'error' => 'Segment not found',
            ], 404);
        }

        if (!$segment->is_dynamic) {
            return response()->json([
                'success' => false,
                'error' => 'Only dynamic segments can be recalculated',
            ], 400);
        }

        $newCount = TxAudienceBuilder::recalculateSegment($segment);

        return response()->json([
            'success' => true,
            'segment_id' => $segment->id,
            'previous_count' => $segment->getOriginal('member_count'),
            'new_count' => $newCount,
            'recalculated_at' => $segment->last_calculated_at,
        ]);
    }

    /**
     * Get audience statistics.
     *
     * GET /api/tx/audiences/stats
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->input('tenant_id');

        // Get counts for common audience types
        $stats = [
            'total_identified' => TxAudienceBuilder::forTenant($tenantId)
                ->activeInLastDays(365)
                ->count(),
            'active_30_days' => TxAudienceBuilder::forTenant($tenantId)
                ->activeInLastDays(30)
                ->count(),
            'purchasers' => TxAudienceBuilder::forTenant($tenantId)
                ->withMinPurchases(1)
                ->count(),
            'repeat_purchasers' => TxAudienceBuilder::forTenant($tenantId)
                ->withMinPurchases(2)
                ->count(),
            'attendees' => TxAudienceBuilder::forTenant($tenantId)
                ->withMinAttendance(1)
                ->count(),
            'abandoned_checkout_7d' => TxAudienceBuilder::forTenant($tenantId)
                ->abandonedCheckout(7)
                ->count(),
            'vip_purchasers' => TxAudienceBuilder::forTenant($tenantId)
                ->withTicketPreference(['VIP', 'Premium'])
                ->count(),
            'high_spenders' => TxAudienceBuilder::forTenant($tenantId)
                ->withMinSpent(500)
                ->count(),
        ];

        // Get segment counts
        $segments = CustomerSegment::where('tenant_id', $tenantId)
            ->where('is_dynamic', true)
            ->select('id', 'name', 'member_count', 'last_calculated_at')
            ->orderByDesc('member_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'top_segments' => $segments,
        ]);
    }

    /**
     * Find similar audiences (lookalike).
     *
     * POST /api/tx/audiences/lookalike
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function lookalike(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'source_person_ids' => 'required|array|min:1|max:1000',
            'source_person_ids.*' => 'integer',
            'limit' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->input('tenant_id');
        $sourcePersonIds = $request->input('source_person_ids');

        // Get common characteristics of source audience
        $artistAffinities = \DB::table('fs_person_affinity_artist')
            ->where('tenant_id', $tenantId)
            ->whereIn('person_id', $sourcePersonIds)
            ->select('artist_id', \DB::raw('AVG(affinity_score) as avg_score'))
            ->groupBy('artist_id')
            ->having('avg_score', '>=', 5)
            ->orderByDesc('avg_score')
            ->limit(10)
            ->pluck('artist_id')
            ->toArray();

        $genreAffinities = \DB::table('fs_person_affinity_genre')
            ->where('tenant_id', $tenantId)
            ->whereIn('person_id', $sourcePersonIds)
            ->select('genre', \DB::raw('AVG(affinity_score) as avg_score'))
            ->groupBy('genre')
            ->having('avg_score', '>=', 5)
            ->orderByDesc('avg_score')
            ->limit(5)
            ->pluck('genre')
            ->toArray();

        // Build lookalike audience
        $builder = TxAudienceBuilder::forTenant($tenantId);

        if (!empty($artistAffinities)) {
            $builder->withArtistAffinity($artistAffinities, 3.0);
        }

        if (!empty($genreAffinities)) {
            $builder->withGenreAffinity($genreAffinities, 3.0);
        }

        $builder->activeInLastDays(90);

        if ($request->filled('limit')) {
            $builder->limit($request->input('limit'));
        }

        $personIds = $builder->getPersonIds();

        // Exclude source persons
        $personIds = $personIds->diff($sourcePersonIds);

        return response()->json([
            'success' => true,
            'source_count' => count($sourcePersonIds),
            'lookalike_count' => $personIds->count(),
            'person_ids' => $personIds->values(),
            'characteristics' => [
                'top_artists' => $artistAffinities,
                'top_genres' => $genreAffinities,
            ],
        ]);
    }

    /**
     * Apply a criterion to the builder.
     */
    protected function applyCriterion(TxAudienceBuilder $builder, array $criterion): TxAudienceBuilder
    {
        return match ($criterion['type']) {
            'artist_affinity' => $builder->withArtistAffinity(
                $criterion['artist_ids'] ?? [],
                $criterion['min_score'] ?? 5.0
            ),
            'genre_affinity' => $builder->withGenreAffinity(
                $criterion['genres'] ?? [],
                $criterion['min_score'] ?? 5.0
            ),
            'price_band' => $builder->withPriceBand($criterion['price_band'] ?? 'mid'),
            'ticket_preference' => $builder->withTicketPreference($criterion['categories'] ?? []),
            'min_attendance' => $builder->withMinAttendance($criterion['min_count'] ?? 1),
            'min_purchases' => $builder->withMinPurchases($criterion['min_count'] ?? 1),
            'min_spent' => $builder->withMinSpent($criterion['min_amount'] ?? 0),
            'active_days' => $builder->activeInLastDays($criterion['days'] ?? 30),
            'viewed_events' => $builder->viewedEvents($criterion['event_ids'] ?? []),
            'not_purchased_events' => $builder->notPurchasedEvents($criterion['event_ids'] ?? []),
            'abandoned_checkout' => $builder->abandonedCheckout($criterion['days_ago'] ?? 7),
            default => $builder,
        };
    }
}
