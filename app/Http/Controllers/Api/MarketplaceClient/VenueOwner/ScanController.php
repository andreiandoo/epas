<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns\FormatsVenueOwnerTicket;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends BaseController
{
    use FormatsVenueOwnerTicket;

    /**
     * Look up a ticket by scanned code or barcode. READ-ONLY: never mutates
     * ticket status, check-in flags, or anything else. Returns the ticket,
     * customer identity (names only), and parent event context — plus any
     * private notes (Phase 3).
     */
    public function lookup(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');

        if (!$tenant instanceof Tenant) {
            return $this->error('Venue owner tenant not resolved', 500);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $code = trim($validated['code']);

        $ticket = Ticket::with([
            'order:id,order_number,customer_name,customer_phone,customer_id,marketplace_customer_id,paid_at,created_at,status,total,currency',
            'order.customer:id,first_name,last_name,phone',
            'order.marketplaceCustomer:id,first_name,last_name,phone',
            'ticketType:id,name',
        ])
            ->where(function ($q) use ($code) {
                $q->where('code', $code)->orWhere('barcode', $code);
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found for code', 404);
        }

        $event = Event::with('venue:id,name,city,address,tenant_id')->find($ticket->event_id);
        if (!$event) {
            return $this->error('Ticket event not found', 404);
        }

        if ((int) $event->marketplace_client_id !== (int) $client->id) {
            return $this->error('Ticket does not belong to this marketplace', 403);
        }

        $venue = $event->venue;
        if (!$venue || (int) $venue->tenant_id !== (int) $tenant->id) {
            return $this->error('Ticket is not for an event at your venue', 403);
        }

        $ticketData = $this->formatTicket($ticket, $event, includeEvent: true);
        $ticketData['notes'] = $this->notesForTicket((int) $tenant->id, $ticket);

        if (!empty($ticketData['customer'])) {
            $ticketData['customer']['tickets_at_event_count'] = $this->customerTicketsAtEventCount(
                $ticketData['customer']['type'] ?? null,
                $ticketData['customer']['id'] ?? null,
                (int) $event->id
            );
        }

        return $this->success(['ticket' => $ticketData]);
    }
}
