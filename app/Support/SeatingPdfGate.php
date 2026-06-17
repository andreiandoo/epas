<?php

namespace App\Support;

use App\Models\Ticket;

/**
 * Decides whether a given ticket should get the second "seating map" page
 * appended to its PDF. Triple-gated for safety during rollout:
 *
 *   1. Global config flag `seating-pdf.enabled` must be true.
 *   2. The event must be in the `seating-pdf.test_event_ids` allowlist.
 *   3. The ticket must actually have a `seat_uid`.
 *
 * When any gate fails, returns false → PDF stays exactly as before. The
 * renderer itself also wraps everything in a try/catch so even a passing
 * gate cannot break a customer ticket.
 */
class SeatingPdfGate
{
    public static function shouldRenderFor(?Ticket $ticket): bool
    {
        if (!$ticket) {
            return false;
        }

        if (!config('seating-pdf.enabled', false)) {
            return false;
        }

        $eventId = (int) $ticket->event_id;
        if ($eventId <= 0) {
            return false;
        }

        $allowed = config('seating-pdf.test_event_ids', []);
        if (empty($allowed) || !in_array($eventId, $allowed, true)) {
            return false;
        }

        if (empty($ticket->seat_uid)) {
            return false;
        }

        return true;
    }
}
