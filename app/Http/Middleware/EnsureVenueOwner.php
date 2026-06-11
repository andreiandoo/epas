<?php

namespace App\Http\Middleware;

use App\Enums\TenantType;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVenueOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Venue owner authentication required',
            ], 401);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant associated with this account',
            ], 403);
        }

        $tenantType = $tenant->tenant_type;
        $tenantTypeValue = $tenantType instanceof \BackedEnum ? $tenantType->value : $tenantType;

        if ($tenantTypeValue !== TenantType::Venue->value) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is not a venue operator',
            ], 403);
        }

        $client = $request->attributes->get('marketplace_client');

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace client required',
            ], 401);
        }

        $hasPartnership = $tenant->venues()
            ->partnerOfMarketplace($client->id)
            ->exists();

        if (!$hasPartnership) {
            return response()->json([
                'success' => false,
                'message' => 'Your venue is not a partner of this marketplace',
            ], 403);
        }

        // Cache tenant on request to avoid refetch
        $request->attributes->set('venue_owner_tenant', $tenant);

        // If route has {event}, authorize event scope
        $eventParam = $request->route('event');
        if ($eventParam !== null) {
            $event = $eventParam instanceof Event
                ? $eventParam
                : Event::find($eventParam);

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found',
                ], 404);
            }

            if ((int) $event->marketplace_client_id !== (int) $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event does not belong to this marketplace',
                ], 403);
            }

            $venue = $event->venue;
            if (!$venue || (int) $venue->tenant_id !== (int) $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event is not hosted at your venue',
                ], 403);
            }

            // Also enforce partnership at event-level (defensive)
            $venueIsPartner = \App\Models\Venue::where('id', $venue->id)
                ->partnerOfMarketplace($client->id)
                ->exists();

            if (!$venueIsPartner) {
                return response()->json([
                    'success' => false,
                    'message' => 'This venue is not partnered with this marketplace',
                ], 403);
            }

            $request->attributes->set('venue_owner_event', $event);
        }

        // If route has {ticket}, authorize ticket belongs to an event at this venue
        $ticketParam = $request->route('ticket');
        if ($ticketParam !== null) {
            $ticket = $ticketParam instanceof Ticket
                ? $ticketParam
                : Ticket::find($ticketParam);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $ticketEvent = Event::with('venue')->find($ticket->event_id);
            if (!$ticketEvent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket event not found',
                ], 404);
            }

            if ((int) $ticketEvent->marketplace_client_id !== (int) $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket does not belong to this marketplace',
                ], 403);
            }

            $venue = $ticketEvent->venue;
            if (!$venue || (int) $venue->tenant_id !== (int) $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket is not for an event at your venue',
                ], 403);
            }

            $request->attributes->set('venue_owner_ticket', $ticket);
            $request->attributes->set('venue_owner_event', $ticketEvent);
        }

        return $next($request);
    }
}
