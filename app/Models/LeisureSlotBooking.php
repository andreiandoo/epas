<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-slot booking counter pentru produsele leisure cu sloturi orare.
 *
 * Un rând per (event_id, ticket_type_id, visit_date, slot_time) — rândul e
 * creat la prima rezervare și incrementat la fiecare următoare. Citirea în
 * DateAvailabilityController returnează `capacity_per_slot - bookings_count`
 * ca număr de locuri rămase; UI-ul public dezactivează slot-urile pline.
 *
 * Concurența este protejată cu:
 *   1. Unique constraint pe (event_id, ticket_type_id, visit_date, slot_time)
 *      — INSERT concurrent → un singur rând în DB.
 *   2. SELECT … FOR UPDATE (lockForUpdate) în SlotBookingService::reserve()
 *      → 2 cassieri care vând în același moment se serializează la rândul lor.
 *
 * Anulările (refund / order cancel) trebuie să apeleze SlotBookingService::release()
 * ca să decrementeze contorul. Fără asta, slot-ul rămâne "consumat" în DB.
 */
class LeisureSlotBooking extends Model
{
    use HasFactory;

    protected $table = 'leisure_slot_bookings';

    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'visit_date',
        'slot_time',
        'bookings_count',
        'capacity_per_slot',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'bookings_count' => 'integer',
        'capacity_per_slot' => 'integer',
    ];

    /** Locuri rămase în slot, sau 0 dacă e plin. */
    public function getRemainingAttribute(): int
    {
        return max(0, ($this->capacity_per_slot ?? 0) - ($this->bookings_count ?? 0));
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->remaining <= 0;
    }
}
