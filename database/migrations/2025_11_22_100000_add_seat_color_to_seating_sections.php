<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            // Add seat_color - color used for seats when available
            $table->string('seat_color', 7)->after('color_hex')->default('#22C55E');

            // Add full_name field for display (e.g., "Sector A, rând 2, loc 10")
            $table->string('display_name_template')->after('name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropColumn(['seat_color', 'display_name_template']);
        });
    }
};
