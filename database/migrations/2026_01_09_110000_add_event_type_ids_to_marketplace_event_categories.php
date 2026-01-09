<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add event_type_ids field to marketplace_event_categories
     * to allow linking custom categories with core event types.
     */
    public function up(): void
    {
        Schema::table('marketplace_event_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_event_categories', 'event_type_ids')) {
                $table->json('event_type_ids')->nullable()
                    ->comment('Array of EventType IDs this category maps to');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_event_categories', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_event_categories', 'event_type_ids')) {
                $table->dropColumn('event_type_ids');
            }
        });
    }
};
