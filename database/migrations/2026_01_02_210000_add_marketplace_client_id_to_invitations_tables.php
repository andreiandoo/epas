<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add marketplace_client_id to invitations tables to support marketplace panel
     */
    public function up(): void
    {
        // Add marketplace_client_id to inv_batches
        Schema::table('inv_batches', function (Blueprint $table) {
            // Make tenant_id nullable since marketplace batches won't have a tenant
            $table->foreignId('tenant_id')->nullable()->change();

            // Add marketplace columns after tenant_id
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                ->constrained('marketplace_clients')->onDelete('cascade');
            $table->foreignId('marketplace_organizer_id')->nullable()->after('marketplace_client_id')
                ->constrained('marketplace_organizers')->onDelete('cascade');
            $table->foreignId('marketplace_event_id')->nullable()->after('marketplace_organizer_id')
                ->constrained('marketplace_events')->onDelete('cascade');

            // Add index for marketplace queries
            $table->index(['marketplace_client_id', 'status']);
        });

        // Add marketplace_client_id to inv_invites
        Schema::table('inv_invites', function (Blueprint $table) {
            // Make tenant_id nullable since marketplace invites won't have a tenant
            $table->foreignId('tenant_id')->nullable()->change();

            // Add marketplace_client_id after tenant_id
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                ->constrained('marketplace_clients')->onDelete('cascade');

            // Add index for marketplace queries
            $table->index(['marketplace_client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_invites', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropIndex(['marketplace_client_id', 'status']);
            $table->dropColumn('marketplace_client_id');
        });

        Schema::table('inv_batches', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropForeign(['marketplace_event_id']);
            $table->dropIndex(['marketplace_client_id', 'status']);
            $table->dropColumn(['marketplace_client_id', 'marketplace_organizer_id', 'marketplace_event_id']);
        });
    }
};
