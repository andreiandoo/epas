<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            // Add display_name for human-readable seat identification
            // Format: "Sector A, rând 2, loc 10"
            $table->string('display_name')->after('label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
