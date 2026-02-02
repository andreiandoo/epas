<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change event_id foreign key from marketplace_events to events table
     * because organizer documents are created for events in the main events table
     */
    public function up(): void
    {
        Schema::table('organizer_documents', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['event_id']);

            // Add new foreign key referencing events table
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizer_documents', function (Blueprint $table) {
            // Drop the events foreign key
            $table->dropForeign(['event_id']);

            // Restore the marketplace_events foreign key
            $table->foreign('event_id')
                ->references('id')
                ->on('marketplace_events')
                ->cascadeOnDelete();
        });
    }
};
