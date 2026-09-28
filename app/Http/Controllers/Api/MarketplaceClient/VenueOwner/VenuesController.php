<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Services\VenueOwner\VenueEventSales;
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

        $ids      = $venues->pluck('id')->toArray();

        // Same event set and "upcoming" rule as /venue/evenimente (EventsController)
        // and /venue/utilizare: this marketplace's events at the partner venues,
        // upcoming = Event::upcoming() (range / multi-day aware).
        $baseEvents = fn () => Event::query()
            ->whereIn('venue_id', $ids)
            ->where('marketplace_client_id', $client->id);
        $totalPerVenue = $baseEvents()
            ->selectRaw('venue_id, COUNT(*) AS c')
            ->groupBy('venue_id')
            ->pluck('c', 'venue_id');
        $upcomingPerVenue = $baseEvents()
            ->upcoming()
            ->selectRaw('venue_id, COUNT(*) AS c')
            ->groupBy('venue_id')
            ->pluck('c', 'venue_id');

        // Sold tickets per venue: the tickets actually sold (valid / used on a paid
        // order, no test sales), same count as /venue/utilizare and the organizer
        // reports. The old SUM(ticket_types.quota_sold) is a stock counter and ran
        // higher (single-ticket refunds, imported events, test POS, invitations).
        $eventVenue = $baseEvents()->pluck('venue_id', 'id');
        $soldPerVenue = [];
        foreach (VenueEventSales::forEvents($eventVenue->keys()) as $eventId => $sales) {
            $venueId = $eventVenue[$eventId] ?? null;
            if ($venueId) {
                $soldPerVenue[$venueId] = ($soldPerVenue[$venueId] ?? 0) + $sales->tickets_sold;
            }
        }

        $data = $venues->map(function ($v) use ($totalPerVenue, $upcomingPerVenue, $soldPerVenue) {
            $name = is_array($v->name) ? ($v->name['ro'] ?? $v->name['en'] ?? reset($v->name)) : $v->name;
            return [
                'id'              => $v->id,
                'name'            => is_string($name) ? $name : 'Venue',
                'city'            => $v->city,
                'state'           => $v->state,
                'capacity'        => (int) ($v->capacity ?? 0),
                'total_events'    => (int) ($totalPerVenue[$v->id] ?? 0),
                'upcoming_events' => (int) ($upcomingPerVenue[$v->id] ?? 0),
                'total_sold'      => (int) ($soldPerVenue[$v->id] ?? 0),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => ['venues' => $data],
        ]);
    }
}
