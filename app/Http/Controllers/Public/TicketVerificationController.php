<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use App\Models\Ticket;

class TicketVerificationController extends Controller
{
    public function show(string $code)
    {
        $ticket = Ticket::where('code', $code)->first();

        if (!$ticket) {
            return view('public.ticket-verify', ['status' => 'not_found']);
        }

        $event = $ticket->resolveEvent();
        $order = $ticket->order;
        $orderPaid = $order && in_array($order->status, ['paid', 'confirmed', 'completed']);

        if ($ticket->is_cancelled || $ticket->status === 'cancelled') {
            $status = 'cancelled';
        } elseif ($ticket->status === 'refunded') {
            $status = 'refunded';
        } elseif ($ticket->checked_in_at) {
            $status = 'used';
        } elseif ($orderPaid && $ticket->status === 'valid') {
            $status = 'valid';
        } else {
            $status = 'invalid';
        }

        $eventTitle = null;
        if ($event) {
            if (method_exists($event, 'getTranslation')) {
                $eventTitle = $event->getTranslation('title', 'ro')
                    ?? $event->getTranslation('title', 'en');
            }
            if (!$eventTitle) {
                $eventTitle = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: null)
                    : $event->title;
            }
        }

        $client = MarketplaceClient::find($ticket->marketplace_client_id);

        return view('public.ticket-verify', [
            'status' => $status,
            'eventTitle' => $eventTitle,
            'eventDate' => $event?->event_date,
            'ticketType' => $ticket->resolveTicketTypeName(),
            'seatLabel' => $ticket->seat_label,
            'checkedInAt' => $ticket->checked_in_at,
            'marketplaceName' => $client?->name,
            'code' => $ticket->code,
        ]);
    }
}
