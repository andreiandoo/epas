<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a UNIQUE constraint on seating_seats.seat_uid.
 *
 * Background: a bug in the layout designer (still under investigation —
 * suspected: "duplicate row" / "copy row" actions reusing the source
 * row's seat UIDs instead of regenerating) silently produced 352 duplicate
 * seat_uid values across 10 layouts. Same UID on two physical seats meant
 * clicking one selected both, sales were capped at one-per-UID, and the
 * second physical seat was effectively phantom-blocked.
 *
 * The data has already been deduped by an earlier one-shot script that
 * regenerated UIDs on all but the lowest-id seat per duplicate group,
 * using the format S{sec_id}-R{row_id}-{seat_label}. This constraint
 * locks that invariant in so the designer can't reintroduce duplicates.
 *
 * If the migration fails with a duplicate key error, run the dedup
 * tinker script first.
 */
return new class extends Migration {
    public function up(): void
    {
        // Defensive guard: refuse to install the constraint if the table
        // still has duplicates. Failing loud here beats failing inside
        // the index creation with a less useful error message.
        $remaining = DB::table('seating_seats')
            ->select('seat_uid')
            ->groupBy('seat_uid')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($remaining > 0) {
            throw new \RuntimeException(
                "Cannot add UNIQUE on seating_seats.seat_uid — {$remaining} duplicate values still present. "
                . 'Run the dedup script first.'
            );
        }

        Schema::table('seating_seats', function (Blueprint $table) {
            $table->unique('seat_uid', 'seating_seats_seat_uid_uq');
        });
    }

    public function down(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->dropUnique('seating_seats_seat_uid_uq');
        });
    }
};
