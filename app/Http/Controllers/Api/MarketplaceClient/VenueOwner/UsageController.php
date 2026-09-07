<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Controller;
use App\Services\VenueOwner\VenueOwnerUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Venue usage API for the /venue/utilizare web shell on ambilet.ro.
 *
 * Mirrors the Filament tenant page /tenant/venue-usage: KPI strip +
 * events table across all partnered venues, filterable by venue and
 * status. Single flat endpoint (no tabs) matching the source page
 * shape.
 *
 * Auth: inherits auth:sanctum + venue.owner from parent group. The
 * middleware already asserted the caller is a real venue owner with
 * at least one partnered venue at the current marketplace, so we only
 * need to resolve the venue-id set for scoping.
 */
class UsageController extends Controller
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

        // Only expose venues that are actually partnered with the calling
        // marketplace — same rule as elsewhere on the venue-owner surface.
        $venues = $tenant->venues()
            ->partnerOfMarketplace($client->id)
            ->get();
        $venueIds = $venues->pluck('id')->toArray();

        $venueFilter  = (string) $request->query('venue_filter', 'all');
        $statusFilter = (string) $request->query('status_filter', 'all');

        try {
            $payload = app(VenueOwnerUsageService::class)->build(
                $venueIds,
                $venueFilter,
                $statusFilter
            );

            // Venue picker options — the shell needs a stable list to render
            // its dropdown even when venue_filter narrows the events set.
            $venueOptions = $venues->mapWithKeys(function ($v) {
                $name = $v->getTranslation('name', 'ro') ?: $v->getTranslation('name', 'en');
                if (is_array($name)) {
                    $name = $name['ro'] ?? $name['en'] ?? (reset($name) ?: 'Venue');
                }
                return [$v->id => $name . ($v->city ? ' (' . $v->city . ')' : '')];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'venues'          => $venueOptions,
                    'showVenueFilter' => count($venueIds) > 1,
                    'venueFilter'     => $venueFilter,
                    'statusFilter'    => $statusFilter,
                    'events'          => $payload['events']->values(),
                    'stats'           => $payload['stats'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('VenueOwner usage failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Usage report could not be computed. Please try again.',
            ], 500);
        }
    }
}
