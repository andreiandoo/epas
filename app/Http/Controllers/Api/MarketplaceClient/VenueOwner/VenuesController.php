<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lists the partnered venues the authenticated venue-owner user is
 * responsible for, with lightweight quick-stats per venue so the
 * /venue/locatii page can render its cards without a per-venue
 * follow-up round-trip.
 *
 * All numbers here are computed inline via DB::table joins to avoid
 * dragging the Filament VenueAnalytics trait for a top-level count.
 * When a caller drills into a venue we hand off to the analytics
 * endpoints in AnalyticsController which use the shared service.
 */
class VenuesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('venue_owner_tenant');
        $client = $request->attributes->get('marketplace_client');

        if (!$tenant || !$client) {
            return response()->json([
                'success' => false,
                'message' => 'Missing tenant / marketplace context.',
            ], 401);
        }

        $venues = $tenant->venues()
            ->partnerOfMarketplace($client->id)
            ->get(['venues.id', 'venues.name', 'venues.city', 'venues.state', 'venues.capacity']);

        if ($venues->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => ['venues' => []],
            ]);
        }

        $now      = now()->toDateString();
        $ids      = $venues->pluck('id')->toArray();

        // Aggregate per-venue counts in ONE query, then attach to the
        // Eloquent collection — cheaper than N separate hasCount calls.
        $aggregates = DB::table('events')
            ->whereIn('venue_id', $ids)
            ->selectRaw(
                'venue_id,'
                . ' COUNT(*) AS total_events,'
                . ' SUM(CASE WHEN event_date >= ? THEN 1 ELSE 0 END) AS upcoming_events',
                [$now]
            )
            ->groupBy('venue_id')
            ->get()
            ->keyBy('venue_id');

        // Sold tickets per venue (denormalised counter kept in sync by
        // TicketType::quota_sold — the same source the Filament venue
        // pages read, so numbers match one-to-one).
        $soldPerVenue = DB::table('events')
            ->leftJoin('ticket_types', 'ticket_types.event_id', '=', 'events.id')
            ->whereIn('events.venue_id', $ids)
            ->selectRaw('events.venue_id, COALESCE(SUM(ticket_types.quota_sold), 0) AS sold')
            ->groupBy('events.venue_id')
            ->get()
            ->keyBy('venue_id');

        $data = $venues->map(function ($v) use ($aggregates, $soldPerVenue) {
            $name = is_array($v->name) ? ($v->name['ro'] ?? $v->name['en'] ?? reset($v->name)) : $v->name;
            $agg  = $aggregates->get($v->id);
            $sold = $soldPerVenue->get($v->id);
            return [
                'id'              => $v->id,
                'name'            => is_string($name) ? $name : 'Venue',
                'city'            => $v->city,
                'state'           => $v->state,
                'capacity'        => (int) ($v->capacity ?? 0),
                'total_events'    => (int) ($agg->total_events ?? 0),
                'upcoming_events' => (int) ($agg->upcoming_events ?? 0),
                'total_sold'      => (int) ($sold->sold ?? 0),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => ['venues' => $data],
        ]);
    }
}
