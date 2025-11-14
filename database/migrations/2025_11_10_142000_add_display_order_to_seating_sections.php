<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            // Add display_order column for sorting sections
            $table->integer('display_order')->default(0)->after('name');

            // Add composite index for efficient ordering queries
            $table->index(['layout_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropIndex(['layout_id', 'display_order']);
            $table->dropColumn('display_order');
        });
    }
};
