<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\Order;
use App\Models\Seating\EventSeat;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatHold;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bucati comune pentru comenzile create din aplicatia de POS
 * (organizator si cont de locatie).
 *
 * Idempotenta: aplicatia trimite aceeasi cheie la fiecare incercare a
 * aceluiasi cos (dublu tap, retry dupa timeout, sincronizare offline).
 * Cererile cu aceeasi cheie se asteapta una pe alta (lock in tranzactie),
 * iar a doua primeste comanda primei in loc sa creeze alta.
 *
 * Locuri: fiecare linie din cos poate aduce propriile locuri
 * (tickets[].seat_uids), ca biletele de tip A sa primeasca locurile
 * alese pentru A. Varianta veche (seat_uids pe toata comanda) ramane.
 */
class PosOrderSupport
{
    public static function validKey($key): ?string
    {
        return is_string($key) && strlen($key) >= 8 && strlen($key) <= 64 ? $key : null;
    }

    /**
     * Apelat ca prima instructiune din tranzactie. Intoarce comanda deja
     * creata cu aceeasi cheie, sau null.
     */
    public static function lockAndFind(int $clientId, ?string $key): ?Order
    {
        if (!$key) {
            return null;
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['pos-order:' . $clientId . ':' . $key]);
        }
        $existingId = DB::table('orders')
            ->where('marketplace_client_id', $clientId)
            ->whereRaw("meta->>'idempotency_key' = ?", [$key])
            ->value('id');

        return $existingId ? Order::find($existingId) : null;
    }

    /**
     * Locurile cerute, pe linii. [indexLinie => [seat_uid, ...]]
     */
    public static function lineSeats(array $ticketsInput): array
    {
        $out = [];
        foreach (array_values($ticketsInput) as $i => $line) {
            $uids = array_values(array_filter((array) ($line['seat_uids'] ?? []), fn ($u) => is_string($u) && $u !== ''));
            if ($uids) {
                $out[$i] = $uids;
            }
        }
        return $out;
    }

    /**
     * Marcheaza locurile ca vandute si le pune pe bilete.
     *
     * @param array $ticketIdsByLine [indexLinie => [ticket_id, ...]] in ordinea crearii
     * @param array $lineSeats       din lineSeats()
     * @param array $legacySeatUids  seat_uids vechi, pe toata comanda
     */
    public static function applySeats(Order $order, Event $event, array $ticketIdsByLine, array $lineSeats, array $legacySeatUids = []): void
    {
        $perLine = !empty($lineSeats);
        $seatUids = $perLine ? array_merge(...array_values($lineSeats)) : array_values($legacySeatUids);
        if (empty($seatUids)) {
            return;
        }

        if (count(array_unique($seatUids)) !== count($seatUids)) {
            throw new \Exception('Același loc a fost ales de două ori');
        }

        if ($perLine) {
            foreach ($lineSeats as $i => $uids) {
                $available = count($ticketIdsByLine[$i] ?? []);
                if (count($uids) > $available) {
                    throw new \Exception('Sunt mai multe locuri decât bilete pe o linie din coș');
                }
            }
        }

        $layout = EventSeatingLayout::where('event_id', $event->id)
            ->published()
            ->latest('published_at')
            ->first();
        if (!$layout) {
            throw new \Exception('No seating layout found for this event');
        }

        $seats = EventSeat::where('event_seating_id', $layout->id)
            ->whereIn('seat_uid', $seatUids)
            ->lockForUpdate()
            ->get();

        if ($seats->count() !== count($seatUids)) {
            $missing = array_diff($seatUids, $seats->pluck('seat_uid')->toArray());
            throw new \Exception('Seats not found: ' . implode(', ', $missing));
        }

        $unavailable = $seats->filter(fn ($s) => $s->status !== 'available' && $s->status !== 'held');
        if ($unavailable->isNotEmpty()) {
            $labels = $unavailable->map(fn ($s) => "{$s->section_name} {$s->row_label}-{$s->seat_label}")->join(', ');
            throw new \Exception("Locuri indisponibile: {$labels}");
        }

        EventSeat::where('event_seating_id', $layout->id)
            ->whereIn('seat_uid', $seatUids)
            ->update([
                'status' => 'sold',
                'last_change_at' => now(),
                'version' => DB::raw('version + 1'),
            ]);

        try {
            event(new \App\Events\Seating\SeatStatusChanged(
                (int) $event->id,
                array_map(fn ($uid) => ['seat_uid' => $uid, 'status' => 'sold'], $seatUids)
            ));
        } catch (\Throwable $e) {
            Log::warning('Seat sold broadcast failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }

        SeatHold::where('event_seating_id', $layout->id)->whereIn('seat_uid', $seatUids)->delete();

        // loc -> bilet
        $pairs = [];
        if ($perLine) {
            foreach ($lineSeats as $i => $uids) {
                foreach ($uids as $k => $uid) {
                    $pairs[] = [$ticketIdsByLine[$i][$k], $uid];
                }
            }
        } else {
            $ticketIds = $order->tickets()->orderBy('id')->pluck('id')->all();
            foreach ($seatUids as $k => $uid) {
                if (isset($ticketIds[$k])) {
                    $pairs[] = [$ticketIds[$k], $uid];
                }
            }
        }

        foreach ($pairs as [$ticketId, $uid]) {
            $seat = $seats->firstWhere('seat_uid', $uid);
            $ticket = Ticket::find($ticketId);
            if (!$seat || !$ticket) {
                continue;
            }
            $ticket->update([
                'seat_label' => $seat->section_name . ' ' . $seat->row_label . '-' . $seat->seat_label,
                'meta' => array_merge($ticket->meta ?? [], [
                    'seat_uid' => $seat->seat_uid,
                    'event_seating_id' => $layout->id,
                    'section_name' => $seat->section_name,
                    'row_label' => $seat->row_label,
                    'seat_number' => $seat->seat_label,
                ]),
            ]);
        }

        $order->update([
            'meta' => array_merge($order->meta ?? [], [
                'seated_items' => [[
                    'event_seating_id' => $layout->id,
                    'seat_uids' => $seatUids,
                ]],
            ]),
        ]);
    }
}
