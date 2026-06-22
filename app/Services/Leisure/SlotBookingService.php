<?php

namespace App\Services\Leisure;

use App\Models\LeisureSlotBooking;
use Illuminate\Support\Facades\DB;

/**
 * Centralizes write-path pentru tracking-ul per-slot al rezervărilor leisure.
 * Toate apelurile rulează în tranzacție cu lockForUpdate ca să garanteze
 * atomicitatea în fața vânzărilor concurente (online checkout + 2 cassieri POS
 * vânzând în același minut).
 *
 * Flow utilizare:
 *   - SlotBookingService::reserve($eventId, $ttId, $date, $time, $capacity, $qty)
 *     → la confirmarea unui order/POS-sale care conține un slot bookable.
 *     → throws SlotSoldOutException dacă nu mai sunt locuri.
 *   - SlotBookingService::release($eventId, $ttId, $date, $time, $qty)
 *     → la anularea unei comenzi / refund.
 *
 * Tabelul `leisure_slot_bookings` are UNIQUE(event_id, ticket_type_id, visit_date,
 * slot_time) — INSERT concurrent → un singur rând final.
 */
class SlotBookingService
{
    /**
     * Rezervă $qty locuri în slot. Atomic + safe la concurență.
     *
     * @throws SlotSoldOutException dacă slot-ul nu are suficient stoc.
     */
    public function reserve(int $eventId, int $ticketTypeId, string $visitDate, string $slotTime, int $capacityPerSlot, int $qty = 1): LeisureSlotBooking
    {
        if ($qty < 1) {
            throw new \InvalidArgumentException('Quantity must be >= 1');
        }
        if ($capacityPerSlot < 1) {
            throw new \InvalidArgumentException('Capacity per slot must be >= 1');
        }

        return DB::transaction(function () use ($eventId, $ticketTypeId, $visitDate, $slotTime, $capacityPerSlot, $qty) {
            // Lock pe rândul existent SAU creează-l. Folosim firstOrCreate cu
            // protecție UNIQUE la nivel DB pentru race-ul "ambii thread-uri
            // încearcă să creeze rândul în același moment".
            $booking = LeisureSlotBooking::where([
                'event_id' => $eventId,
                'ticket_type_id' => $ticketTypeId,
                'visit_date' => $visitDate,
                'slot_time' => $slotTime,
            ])->lockForUpdate()->first();

            if (!$booking) {
                // Race posibil aici: thread A nu găsește rândul → încearcă INSERT.
                // Thread B la fel — UNIQUE constraint blochează al doilea.
                // Catch-and-retry: dacă INSERT eșuează cu duplicate key, re-fetch
                // și update.
                try {
                    $booking = LeisureSlotBooking::create([
                        'event_id' => $eventId,
                        'ticket_type_id' => $ticketTypeId,
                        'visit_date' => $visitDate,
                        'slot_time' => $slotTime,
                        'bookings_count' => 0,
                        'capacity_per_slot' => $capacityPerSlot,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Duplicate key — re-fetch cu lock
                    $booking = LeisureSlotBooking::where([
                        'event_id' => $eventId,
                        'ticket_type_id' => $ticketTypeId,
                        'visit_date' => $visitDate,
                        'slot_time' => $slotTime,
                    ])->lockForUpdate()->firstOrFail();
                }
            }

            // Update capacity_per_slot dacă s-a schimbat în meta-ul TicketType
            // (operatorul a redus capacity-ul după ce au fost deja vânzări).
            // În acest caz, slot-ul poate fi deja overbooked — nu reducem
            // bookings_count, doar refuzăm noi rezervări.
            if ($booking->capacity_per_slot !== $capacityPerSlot) {
                $booking->capacity_per_slot = $capacityPerSlot;
            }

            if ($booking->bookings_count + $qty > $booking->capacity_per_slot) {
                throw new SlotSoldOutException(
                    "Slot $slotTime full ($booking->bookings_count/$booking->capacity_per_slot), tried +$qty"
                );
            }

            $booking->bookings_count += $qty;
            $booking->save();
            return $booking;
        });
    }

    /**
     * Eliberează $qty locuri (refund / cancel). Nu coboară sub 0.
     */
    public function release(int $eventId, int $ticketTypeId, string $visitDate, string $slotTime, int $qty = 1): void
    {
        if ($qty < 1) return;

        DB::transaction(function () use ($eventId, $ticketTypeId, $visitDate, $slotTime, $qty) {
            $booking = LeisureSlotBooking::where([
                'event_id' => $eventId,
                'ticket_type_id' => $ticketTypeId,
                'visit_date' => $visitDate,
                'slot_time' => $slotTime,
            ])->lockForUpdate()->first();

            if (!$booking) return; // nimic de eliberat

            $booking->bookings_count = max(0, $booking->bookings_count - $qty);
            $booking->save();
        });
    }

    /**
     * Returnează un map { 'HH:MM' => bookings_count } pentru o anumită
     * combinație event + ticket_type + visit_date. Folosit de
     * DateAvailabilityController ca să calculeze remaining per slot.
     */
    public function getBookingsMap(int $eventId, int $ticketTypeId, string $visitDate): array
    {
        return LeisureSlotBooking::where([
            'event_id' => $eventId,
            'ticket_type_id' => $ticketTypeId,
            'visit_date' => $visitDate,
        ])->pluck('bookings_count', 'slot_time')->all();
    }
}
