<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceEvent;
use App\Models\VenueStaffMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVenueStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof VenueStaffMember) {
            return response()->json([
                'success' => false,
                'message' => 'Venue staff authentication required',
            ], 401);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active',
            ], 403);
        }

        $eventParam = $request->route('event');

        if ($eventParam !== null) {
            $event = $eventParam instanceof MarketplaceEvent
                ? $eventParam
                : MarketplaceEvent::find($eventParam);

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found',
                ], 404);
            }

            $client = $request->attributes->get('marketplace_client');
            if ($client && (int) $event->marketplace_client_id !== (int) $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event does not belong to this marketplace',
                ], 403);
            }

            $venue = $event->venue;
            if (!$venue || (int) $venue->tenant_id !== (int) $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event is not hosted at your venue',
                ], 403);
            }

            // Cache resolved event on the request so controllers don't refetch
            $request->attributes->set('venue_staff_event', $event);
        }

        return $next($request);
    }
}
