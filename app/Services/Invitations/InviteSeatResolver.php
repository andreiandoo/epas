<?php

namespace App\Services\Invitations;

use App\Models\Invite;
use App\Models\Seating\EventSeat;
use App\Models\Seating\EventSeatingLayout;

/**
 * Seat of an invitation, resolved to section / row / seat.
 *
 * Invitations made in the marketplace panel only carried a free-text seat_ref
 * (e.g. "H-28"), so the ticket template took {{ticket.section}} and
 * {{ticket.row}} from the sample data ("Sectiunea A", "Randul 5").
 * Resolution order: seat_uid stored on the invite (meta or recipient.seat),
 * then seat_ref parsed and matched against the event's seat map, then the
 * parsed values alone.
 */
class InviteSeatResolver
{
    /**
     * @return array{section_name: string, row_label: string, seat_number: string, seat_uid: ?string, event_seating_id: ?int}|null
     */
    public function resolve(Invite $invite, ?int $eventId): ?array
    {
        $meta = is_array($invite->meta) ? $invite->meta : [];
        $recipientSeat = is_array($invite->recipient) && is_array($invite->recipient['seat'] ?? null)
            ? $invite->recipient['seat']
            : [];
        $seatUid = $meta['seat_uid'] ?? ($recipientSeat['uid'] ?? null);
        $seatingIds = $eventId ? $this->seatingIds($eventId) : [];

        if ($seatUid) {
            $seat = EventSeat::where('seat_uid', $seatUid)
                ->when($seatingIds, fn ($q) => $q->whereIn('event_seating_id', $seatingIds))
                ->first();
            if ($seat) {
                return $this->fromEventSeat($seat);
            }
        }

        $parsed = static::parseSeatRef((string) ($invite->seat_ref ?? ''));
        if (!$parsed) {
            return null;
        }

        if ($seatingIds && $parsed['row_label'] !== '' && $parsed['seat_number'] !== '') {
            $matches = EventSeat::whereIn('event_seating_id', $seatingIds)
                ->whereRaw('UPPER(row_label) = ?', [mb_strtoupper($parsed['row_label'])])
                ->where('seat_label', $parsed['seat_number'])
                ->when($parsed['section_name'] !== '', fn ($q) => $q->whereRaw('UPPER(section_name) = ?', [mb_strtoupper($parsed['section_name'])]))
                ->limit(2)
                ->get();
            if ($matches->count() === 1) {
                return $this->fromEventSeat($matches->first());
            }
        }

        return $parsed + ['seat_uid' => null, 'event_seating_id' => null];
    }

    /**
     * Template fields in the shape TicketVariableService::resolveTicketData
     * produces. Without a resolvable seat the raw seat_ref stays in
     * {{ticket.seat}} and section / row are empty, never the sample values.
     */
    public function templateFields(?array $seat, ?string $rawSeatRef): array
    {
        if (!$seat) {
            return ['section' => '', 'row' => '', 'seat' => trim((string) $rawSeatRef)];
        }

        return [
            'section' => $seat['section_name'] !== '' ? 'Sectiunea ' . $seat['section_name'] : '',
            'row' => $seat['row_label'] !== '' ? 'Randul ' . $seat['row_label'] : '',
            'seat' => $seat['seat_number'] !== '' ? 'Locul ' . $seat['seat_number'] : '',
        ];
    }

    /**
     * Readable label, same format as the organizer invitations flow.
     */
    public function label(array $seat): string
    {
        return implode(' · ', array_filter([
            $seat['section_name'],
            $seat['row_label'] !== '' ? 'Rând ' . $seat['row_label'] : '',
            $seat['seat_number'] !== '' ? 'Loc ' . $seat['seat_number'] : '',
        ]));
    }

    /**
     * Ticket / invite meta so Ticket::getSeatDetails() returns the structured seat.
     */
    public function ticketMeta(array $seat): array
    {
        return array_filter([
            'seat_uid' => $seat['seat_uid'],
            'event_seating_id' => $seat['event_seating_id'],
            'section_name' => $seat['section_name'],
            'row_label' => $seat['row_label'],
            'seat_number' => $seat['seat_number'],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Seats picked on the map ("Block Seats" → invitations), in the given order.
     *
     * @return list<array>
     */
    public function seatsByUid(int $eventId, array $seatUids): array
    {
        $seatUids = array_values(array_filter(array_map('trim', $seatUids)));
        if (!$seatUids) {
            return [];
        }

        $byUid = EventSeat::whereIn('event_seating_id', $this->seatingIds($eventId))
            ->whereIn('seat_uid', $seatUids)
            ->get()
            ->keyBy('seat_uid');

        $seats = [];
        foreach ($seatUids as $uid) {
            if ($byUid->has($uid)) {
                $seats[] = $this->fromEventSeat($byUid->get($uid));
            }
        }

        return $seats;
    }

    /**
     * "H-28", "G 27", "h28", "Rând H, Loc 28", "5-12", "SECTION · Rând H · Loc 28".
     *
     * @return array{section_name: string, row_label: string, seat_number: string}|null
     */
    public static function parseSeatRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (str_contains($ref, '·')) {
            $section = '';
            $row = '';
            $seat = '';
            foreach (array_map('trim', explode('·', $ref)) as $part) {
                if (preg_match('/^r(?:â|a)nd(?:ul)?\s+(.+)$/iu', $part, $m)) {
                    $row = trim($m[1]);
                } elseif (preg_match('/^loc(?:ul)?\s+(.+)$/iu', $part, $m)) {
                    $seat = trim($m[1]);
                } elseif ($section === '' && $part !== '') {
                    $section = $part;
                }
            }

            return ($row !== '' || $seat !== '')
                ? ['section_name' => $section, 'row_label' => $row, 'seat_number' => $seat]
                : null;
        }

        $prefix = '(?:r(?:â|a)nd(?:ul)?\s*)?';
        $seatPart = '(?:loc(?:ul)?\s*)?(\d{1,4})';
        if (preg_match('/^' . $prefix . '([a-z]{1,3})\s*[-–—,\/]?\s*' . $seatPart . '$/iu', $ref, $m)
            && !preg_match('/^loc(?:ul)?$/iu', $m[1])) {
            return ['section_name' => '', 'row_label' => mb_strtoupper($m[1]), 'seat_number' => $m[2]];
        }
        if (preg_match('/^' . $prefix . '(\d{1,3})\s*(?:[-–—,\/]\s*|\s+)' . $seatPart . '$/iu', $ref, $m)) {
            return ['section_name' => '', 'row_label' => $m[1], 'seat_number' => $m[2]];
        }

        return null;
    }

    private function fromEventSeat(EventSeat $seat): array
    {
        return [
            'section_name' => (string) ($seat->section_name ?? ''),
            'row_label' => (string) ($seat->row_label ?? ''),
            'seat_number' => (string) ($seat->seat_label ?? ''),
            'seat_uid' => $seat->seat_uid,
            'event_seating_id' => (int) $seat->event_seating_id,
        ];
    }

    private function seatingIds(int $eventId): array
    {
        return EventSeatingLayout::where('event_id', $eventId)->pluck('id')->all();
    }
}
