<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add block_reason field to seating_seats table to track why a seat is blocked.
     * Possible values: stricat (damaged), lipsa (missing), indisponibil (unavailable)
     */
    public function up(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->string('block_reason', 50)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->dropColumn('block_reason');
        });
    }
};
