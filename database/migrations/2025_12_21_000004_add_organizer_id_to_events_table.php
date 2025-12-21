<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds organizer_id to events table.
     * For marketplace tenants, events are created by organizers.
     * For standard tenants, organizer_id remains null.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Link event to organizer (for marketplace tenants)
            // Nullable because standard tenant events don't have organizers
            $table->foreignId('organizer_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('marketplace_organizers')
                ->nullOnDelete();

            // Index for efficient queries
            $table->index('organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['organizer_id']);
            $table->dropIndex(['organizer_id']);
            $table->dropColumn('organizer_id');
        });
    }
};
