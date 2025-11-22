<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_tiers', function (Blueprint $table) {
            // Add tier_code column
            $table->string('tier_code', 50)->unique()->after('name');

            // Rename color_hex to color
            $table->renameColumn('color_hex', 'color');

            // Add is_active column
            $table->boolean('is_active')->default(true)->after('description');

            // Add sort_order column
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->dropColumn(['tier_code', 'is_active', 'sort_order']);
            $table->renameColumn('color', 'color_hex');
        });
    }
};
