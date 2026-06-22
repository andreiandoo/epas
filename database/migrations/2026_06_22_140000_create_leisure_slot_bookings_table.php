<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking per-slot pentru produsele leisure cu sloturi orare (Ghidaj, Vaporașe,
 * Tour ghidat, etc.). Un rând pe (eveniment, tip bilet, dată, oră) ține numărul
 * curent de rezervări confirmate. Combinat cu un unique constraint și un lock
 * pesimist în logica de booking, garantează că nu se depășește capacitatea per
 * slot chiar la concurență mare (2 cassieri vând în același moment).
 *
 * Nu afectează produsele leisure fără sloturi — pentru ele tabelul rămâne gol.
 * Lookup-ul în DateAvailabilityController e by slot_times array din meta, nu
 * by tabel; rezultatul `available_remaining` cade înapoi la `capacity_per_slot`
 * când nu există încă rânduri pentru data respectivă.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_slot_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->date('visit_date');
            $table->string('slot_time', 8); // 'HH:MM'
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('capacity_per_slot');
            $table->timestamps();

            $table->unique(['event_id', 'ticket_type_id', 'visit_date', 'slot_time'], 'leisure_slot_unique');
            $table->index(['event_id', 'visit_date'], 'leisure_slot_event_date_idx');
            $table->index('ticket_type_id', 'leisure_slot_tt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_slot_bookings');
    }
};
