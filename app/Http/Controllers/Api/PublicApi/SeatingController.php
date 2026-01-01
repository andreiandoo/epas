<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Controller;
use App\Services\Seating\GeometryStorage;
use App\Services\Seating\SeatHoldService;
use App\Services\Seating\Pricing\Contracts\DynamicPricingEngine;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\PriceTier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * SeatingController - Public API
 *
 * Handles public-facing seating operations with rate limiting and session management
 */
class SeatingController extends Controller
{
    public function __construct(
        private GeometryStorage $geometry,
        private SeatHoldService $holdService,
        private DynamicPricingEngine $pricing
    ) {
        // Rate limiting applied in routes
    }

    /**
     * GET /api/public/events/{eventId}/seating
     *
     * Get seating geometry and metadata for an event
     */
    public function getSeating(int $eventId): JsonResponse
    {
        try {
            // Get latest published snapshot
            $layout = EventSeatingLayout::where('event_id', $eventId)
                ->published()
                ->latest('published_at')
                ->first();

            if (!$layout) {
                return response()->json([
                    'error' => 'No seating layout found for this event',
                ], 404);
            }

            // Get geometry
            $geometry = $this->geometry->getGeometry($layout->id);

            // Get price tiers
            $priceTiers = $this->getPriceTiersForEvent($layout->id);

            // Get seat counts
            $statusCounts = $layout->getSeatStatusCounts();

            return response()->json([
                'event_seating_id' => $layout->id,
                'event_id' => $eventId,
                'canvas' => $geometry['canvas'],
                'background_url' => $geometry['background_url'] ?? null,
                'price_tiers' => $priceTiers,
                'sections' => $geometry['sections'],
                'version' => $geometry['version'],
                'seat_counts' => $statusCounts,
                'published_at' => $layout->published_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('SeatingController: Error getting seating', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load seating layout',
            ], 500);
        }
    }

    /**
     * GET /api/public/events/{eventId}/seats
     *
     * Get seat availability and pricing
     * Optional: ?section=A&status=available&bbox=x1,y1,x2,y2
     */
    public function getSeats(Request $request, int $eventId): JsonResponse
    {
        try {
            $layout = EventSeatingLayout::where('event_id', $eventId)
                ->published()
                ->latest('published_at')
                ->first();

            if (!$layout) {
                return response()->json([
                    'error' => 'No seating layout found',
                ], 404);
            }

            $query = EventSeat::where('event_seating_id', $layout->id);

            // Filter by section
            if ($request->has('section')) {
                $query->where('section_name', $request->input('section'));
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Limit result count
            $limit = min($request->input('limit', 1000), 5000);
            $seats = $query->limit($limit)->get();

            // Build response with pricing
            $seatsData = $seats->map(function ($seat) use ($layout) {
                $priceDecision = $this->pricing->computeEffectivePrice($layout->id, $seat->seat_uid);

                return [
                    'seat_uid' => $seat->seat_uid,
                    'section_name' => $seat->section_name,
                    'row_label' => $seat->row_label,
                    'seat_label' => $seat->seat_label,
                    'status' => $seat->status,
                    'price_cents' => $priceDecision->effectivePriceCents,
                    'price_tier_id' => $seat->price_tier_id,
                    'version' => $seat->version,
                ];
            });

            return response()->json([
                'event_seating_id' => $layout->id,
                'seats' => $seatsData,
                'total' => $seatsData->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('SeatingController: Error getting seats', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load seats',
            ], 500);
        }
    }

    /**
     * POST /api/public/seats/hold
     *
     * Hold seats for current session
     */
    public function holdSeats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_seating_id' => 'required|integer|exists:event_seating_layouts,id',
            'seat_uids' => 'required|array|max:' . config('seating.max_held_seats_per_session', 10),
            'seat_uids.*' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionUid = $request->attributes->get('seating_session_uid');

        try {
            $result = $this->holdService->holdSeats(
                $request->input('event_seating_id'),
                $request->input('seat_uids'),
                $sessionUid
            );

            $statusCode = empty($result['held']) ? 409 : 200;

            return response()->json($result, $statusCode)
                ->header('X-Expires-At', $result['expires_at'])
                ->header('X-Hold-Count', count($result['held']));
        } catch (\Exception $e) {
            Log::error('SeatingController: Error holding seats', [
                'session_uid' => $sessionUid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to hold seats',
            ], 500);
        }
    }

    /**
     * DELETE /api/public/seats/hold
     *
     * Release held seats for current session
     */
    public function releaseSeats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_seating_id' => 'required|integer|exists:event_seating_layouts,id',
            'seat_uids' => 'required|array',
            'seat_uids.*' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionUid = $request->attributes->get('seating_session_uid');

        try {
            $result = $this->holdService->releaseSeats(
                $request->input('event_seating_id'),
                $request->input('seat_uids'),
                $sessionUid
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('SeatingController: Error releasing seats', [
                'session_uid' => $sessionUid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to release seats',
            ], 500);
        }
    }

    /**
     * POST /api/public/seats/confirm
     *
     * Confirm purchase (mark seats as sold)
     */
    public function confirmPurchase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_seating_id' => 'required|integer|exists:event_seating_layouts,id',
            'seat_uids' => 'required|array',
            'seat_uids.*' => 'required|string|max:32',
            'amount_cents' => 'required|integer|min:0',
            'idempotency_key' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionUid = $request->attributes->get('seating_session_uid');

        try {
            // Check idempotency (simple cache-based implementation)
            $idempotencyKey = $request->input('idempotency_key');
            $cacheKey = "seating:idempotency:{$idempotencyKey}";

            if (\Cache::has($cacheKey)) {
                // Return cached response
                return response()->json(\Cache::get($cacheKey));
            }

            $result = $this->holdService->confirmPurchase(
                $request->input('event_seating_id'),
                $request->input('seat_uids'),
                $sessionUid,
                $request->input('amount_cents')
            );

            $statusCode = empty($result['confirmed']) ? 409 : 200;

            $response = [
                'confirmed' => $result['confirmed'],
                'failed' => $result['failed'],
                'success' => empty($result['failed']),
            ];

            // Cache successful response for 5 minutes
            if ($response['success']) {
                \Cache::put($cacheKey, $response, 300);
            }

            return response()->json($response, $statusCode);
        } catch (\Exception $e) {
            Log::error('SeatingController: Error confirming purchase', [
                'session_uid' => $sessionUid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to confirm purchase',
            ], 500);
        }
    }

    /**
     * GET /api/public/seats/holds
     *
     * Get current session's holds
     */
    public function getSessionHolds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_seating_id' => 'required|integer|exists:event_seating_layouts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionUid = $request->attributes->get('seating_session_uid');

        try {
            $holds = $this->holdService->getSessionHolds(
                $request->input('event_seating_id'),
                $sessionUid
            );

            return response()->json([
                'session_uid' => $sessionUid,
                'held_seats' => $holds,
                'count' => count($holds),
            ]);
        } catch (\Exception $e) {
            Log::error('SeatingController: Error getting session holds', [
                'session_uid' => $sessionUid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get holds',
            ], 500);
        }
    }

    /**
     * Get price tiers for an event
     */
    private function getPriceTiersForEvent(int $eventSeatingId): array
    {
        // Get unique price tier IDs from event seats
        $tierIds = EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereNotNull('price_tier_id')
            ->distinct()
            ->pluck('price_tier_id');

        if ($tierIds->isEmpty()) {
            return [];
        }

        $tiers = PriceTier::whereIn('id', $tierIds)->get();

        return $tiers->map(function ($tier) {
            return [
                'id' => $tier->id,
                'name' => $tier->name,
                'currency' => $tier->currency,
                'price_cents' => $tier->price_cents,
                'color_hex' => $tier->color_hex,
                'description' => $tier->description,
            ];
        })->toArray();
    }
}
