<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Add seating_layout_id to link event to a specific seating layout
            $table->foreignId('seating_layout_id')
                ->after('venue_id')
                ->nullable()
                ->constrained('seating_layouts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['seating_layout_id']);
            $table->dropColumn('seating_layout_id');
        });
    }
};
