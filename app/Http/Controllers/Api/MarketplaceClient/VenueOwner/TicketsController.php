<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns\FormatsVenueOwnerTicket;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketsController extends BaseController
{
    use FormatsVenueOwnerTicket;

    /**
     * Return full details for a single ticket.
     * Middleware has already authorized the ticket belongs to this venue's tenant.
     */
    public function show(Request $request, int $ticket): JsonResponse
    {
        /** @var Ticket|null $ticketModel */
        $ticketModel = $request->attributes->get('venue_owner_ticket');
        if (!$ticketModel instanceof Ticket) {
            $ticketModel = Ticket::find($ticket);
        }

        if (!$ticketModel) {
            return $this->error('Ticket not found', 404);
        }

        $ticketModel->load([
            'order:id,order_number,customer_name,customer_id,marketplace_customer_id,paid_at,created_at,status,total,currency',
            'order.customer:id,first_name,last_name',
            'order.marketplaceCustomer:id,first_name,last_name',
            'ticketType:id,name',
        ]);

        /** @var Event|null $event */
        $event = $request->attributes->get('venue_owner_event')
            ?: Event::with('venue:id,name,city,address')->find($ticketModel->event_id);

        if ($event && !$event->relationLoaded('venue')) {
            $event->load('venue:id,name,city,address');
        }

        return $this->success([
            'ticket' => $this->formatTicket($ticketModel, $event, includeEvent: true),
        ]);
    }
}
