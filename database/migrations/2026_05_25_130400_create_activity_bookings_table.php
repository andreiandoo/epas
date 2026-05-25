<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module — A1 / step 5: a confirmed (or pending) booking.
 *
 * Sits between `orders` (one Order per checkout — can hold multiple
 * bookings if a future feature ships) and `tickets` (one Ticket per
 * actual participant, emitted only after payment).
 *
 * Lifecycle:
 *   pending_payment → paid → confirmed → checked_in
 *                         ↘ cancelled
 *                         ↘ no_show
 *
 * The held_until timestamp implements a 10-minute soft hold during
 * checkout — once an Order line item references this booking,
 * SlotResolver excludes the slot's capacity for other shoppers until
 * the hold expires (analogous to SeatHoldService for seating events).
 * Expired holds are released by a scheduled job (defined in A5).
 *
 * Non-breaking: brand new table. tickets gets activity_booking_id in
 * the next migration so seat-emission can target a booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_bookings')) {
            return;
        }

        Schema::create('activity_bookings', function (Blueprint $table) {
            $table->id();

            // Marketplace scope (denormalized for cheap WHERE clauses on listing).
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->foreignId('activity_id')
                ->constrained('activities')
                ->cascadeOnDelete();

            // Marketplace customer — nullable for guest checkout (same pattern as orders).
            $table->foreignId('marketplace_customer_id')
                ->nullable()
                ->constrained('marketplace_customers')
                ->nullOnDelete();

            // The order this booking belongs to. Set when checkout begins; remains
            // null only for legacy/imported bookings (none expected initially).
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            // The reserved slot. Stored as separate date + times rather than a single
            // datetime so timezone weirdness on date-only boundaries doesn't bite.
            $table->date('booking_date');
            $table->time('slot_start_time');
            $table->time('slot_end_time');

            // Total head-count occupying this slot (sum of variants × capacity_share × qty).
            // Used by SlotResolver to compute remaining capacity.
            $table->unsignedSmallInteger('participants_count');

            // pending_payment | paid | confirmed | cancelled | checked_in | no_show
            $table->string('status', 24)->default('pending_payment');

            // Financials snapshot at booking time (don't re-derive from variant prices).
            $table->integer('total_cents')->default(0);
            $table->integer('commission_cents')->default(0);
            $table->string('currency', 3)->default('RON');

            // Public-facing reference. Short, human-readable; what the customer brings
            // to the venue if QR fails. Unique per marketplace.
            $table->string('confirmation_code', 32);

            $table->text('notes')->nullable();   // customer special requests
            $table->text('qr_payload')->nullable(); // signed QR string for staff scan

            // 10-minute soft hold during checkout. NULL once paid (no need to expire).
            $table->dateTime('held_until')->nullable();

            // When status transitioned to checked_in.
            $table->dateTime('checked_in_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // SlotResolver's hottest query: for a given (activity, date),
            // sum participants_count of all non-cancelled bookings.
            $table->index(['activity_id', 'booking_date', 'status'], 'act_bookings_slot_idx');

            // Listing queries from organizer side: my activity's upcoming bookings.
            $table->index(['marketplace_client_id', 'booking_date'], 'act_bookings_mp_date_idx');

            // Confirmation code lookup (customer enters at venue if QR is unreadable).
            $table->unique(['marketplace_client_id', 'confirmation_code'], 'act_bookings_confcode_unique');

            // Released holds sweep target.
            $table->index(['status', 'held_until'], 'act_bookings_hold_sweep_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_bookings');
    }
};
