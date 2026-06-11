<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;

class TicketEventResolver
{
    private static array $cache = [];

    public static function resolve(Ticket $ticket): ?Event
    {
        $key = $ticket->id;
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $event = null;

        // 1. Direct event_id on ticket
        if ($ticket->event_id) {
            $event = Event::find($ticket->event_id);
        }

        // 2. Via ticket type
        if (!$event && $ticket->ticketType?->event_id) {
            $event = $ticket->ticketType->event;
        }

        // 3. Marketplace event
        if (!$event && $ticket->marketplace_event_id) {
            $event = Event::find($ticket->marketplace_event_id);
        }

        // 4. Via ticket type marketplace
        if (!$event && $ticket->ticketType?->marketplace_event_id) {
            $event = Event::find($ticket->ticketType->marketplace_event_id);
        }

        self::$cache[$key] = $event;
        return $event;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
