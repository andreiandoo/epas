<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module — A1 / step 6: link tickets to activity bookings.
 *
 * One activity booking emits N tickets (one per participant). Same
 * relationship pattern as `ticket_type_id` for events; the difference
 * is which FK is populated:
 *
 *   - Event ticket:    tickets.event_id + tickets.ticket_type_id        set
 *                      tickets.activity_booking_id                       null
 *   - Activity ticket: tickets.activity_booking_id                       set
 *                      tickets.event_id + tickets.ticket_type_id         null
 *
 * Enforcement is application-level (Ticket model + observer) for
 * portability; we don't add a CHECK constraint because:
 *   - on prod we need backward compatibility for already-existing rows
 *   - postgres CHECK constraints aren't reversible cleanly via Laravel
 *   - the model's `saving` hook can give a clearer error message
 *
 * Non-breaking guarantees:
 *   - Column is nullable, so existing tickets continue to validate.
 *   - tickets.event_id remains as-is (still nullable from prior
 *     migrations; we don't touch it).
 *   - tickets.ticket_type_id remains required for events; activity
 *     tickets that need a ticket_type_id will trigger a separate
 *     migration in A5 to make it nullable. Not done now — keeping
 *     the change set minimal.
 *   - No reports / payout / commission code reads activity_booking_id
 *     yet; they ignore the new column entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'activity_booking_id')) {
                // foreignId() places the column at table end by default; use after()
                // to keep the activity_booking_id sibling-of event_id for readability
                // in tools like TablePlus.
                $table->foreignId('activity_booking_id')
                    ->nullable()
                    ->after('event_id')
                    ->constrained('activity_bookings')
                    ->nullOnDelete();

                $table->index('activity_booking_id', 'tickets_activity_booking_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'activity_booking_id')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            // Drop FK first (Laravel auto-names it tickets_activity_booking_id_foreign).
            try {
                $table->dropForeign(['activity_booking_id']);
            } catch (\Throwable $e) {
                // ignore if it was named differently or already missing
            }
            $table->dropIndex('tickets_activity_booking_idx');
            $table->dropColumn('activity_booking_id');
        });
    }
};
