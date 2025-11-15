<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            // Add fields for decorative zones (stage, etc.)
            $table->string('background_color', 7)->nullable()->after('color_hex');
            $table->integer('corner_radius')->default(0)->after('rotation');
            $table->string('background_image')->nullable()->after('corner_radius');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropColumn(['background_color', 'corner_radius', 'background_image']);
        });
    }
};
