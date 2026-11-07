<?php

namespace App\Support;

use App\Models\Event;

/**
 * Verifica la check-in ca biletul scanat e pentru evenimentul ales pe
 * telefon. Aplicatiile vechi nu trimit event_id, iar atunci nu blocam nimic.
 *
 * Parinte si reprezentatii (parent_id) se considera acelasi eveniment, ca
 * sa nu respingem bilete valide la evenimentele cu mai multe date.
 */
class ScanEventGuard
{
    /**
     * Mesajul de eroare cand biletul e pentru alt eveniment, sau null cand e ok.
     */
    public static function mismatchMessage(mixed $selectedEventId, mixed $ticketEventId): ?string
    {
        $selected = (int) $selectedEventId;
        $ticket = (int) $ticketEventId;

        if ($selected <= 0 || $ticket <= 0 || $selected === $ticket) {
            return null;
        }

        $events = Event::whereIn('id', [$selected, $ticket])->get()->keyBy('id');
        $selectedEvent = $events->get($selected);
        $ticketEvent = $events->get($ticket);

        if ($selectedEvent && $ticketEvent) {
            $selectedRoot = $selectedEvent->parent_id ?: $selectedEvent->id;
            $ticketRoot = $ticketEvent->parent_id ?: $ticketEvent->id;
            if ((int) $selectedRoot === (int) $ticketRoot) {
                return null;
            }
        }

        $title = $ticketEvent ? trim((string) $ticketEvent->getTranslation('title', 'ro')) : '';
        $date = $ticketEvent?->displayDateLabel();

        $label = $title !== '' ? $title : '#' . $ticket;
        if ($date) {
            $label .= ' (' . $date . ')';
        }

        return 'Bilet pentru alt eveniment: ' . $label;
    }
}
