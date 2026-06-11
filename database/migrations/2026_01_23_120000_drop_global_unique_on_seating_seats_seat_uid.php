<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            // Drop the global unique constraint on seat_uid.
            // The seat_uid format (SECTIONCODE-ROW-SEAT) does not include layout_id,
            // so different layouts with similar section names produce duplicate UIDs.
            // The composite unique ['row_id', 'seat_uid'] already ensures per-row uniqueness.
            $table->dropUnique('seating_seats_seat_uid_unique');

            // Keep seat_uid indexed for fast lookups
            $table->index('seat_uid');
        });
    }

    public function down(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->dropIndex(['seat_uid']);
            $table->unique('seat_uid');
        });
    }
};
