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
        // Add organizer to invite batches
        Schema::table('inv_batches', function (Blueprint $table) {
            $table->foreignId('marketplace_organizer_id')
                ->nullable()
                ->after('marketplace_client_id')
                ->constrained('marketplace_organizers')
                ->nullOnDelete();

            $table->foreignId('marketplace_event_id')
                ->nullable()
                ->after('marketplace_organizer_id')
                ->constrained('marketplace_events')
                ->nullOnDelete();

            $table->index('marketplace_organizer_id');
            $table->index('marketplace_event_id');
        });

        // Add organizer reference to invites
        Schema::table('invites', function (Blueprint $table) {
            $table->foreignId('marketplace_organizer_id')
                ->nullable()
                ->after('marketplace_client_id')
                ->constrained('marketplace_organizers')
                ->nullOnDelete();

            $table->index('marketplace_organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_batches', function (Blueprint $table) {
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropForeign(['marketplace_event_id']);
            $table->dropColumn(['marketplace_organizer_id', 'marketplace_event_id']);
        });

        Schema::table('invites', function (Blueprint $table) {
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropColumn('marketplace_organizer_id');
        });
    }
};
