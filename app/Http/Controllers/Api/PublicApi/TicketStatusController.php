<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class TicketStatusController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $ticket = Ticket::where('code', $code)->first();

        if (!$ticket) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Ticket not found',
            ], 404);
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

        return response()->json([
            'status' => $status,
            'ticket_code' => $ticket->code,
            'event_title' => $eventTitle,
            'event_date' => $event?->event_date?->format('Y-m-d'),
            'ticket_type' => $ticket->resolveTicketTypeName(),
            'seat_label' => $ticket->seat_label,
            'attendee_name' => $ticket->attendee_name ?? $order?->customer_name,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
        ]);
    }
}
