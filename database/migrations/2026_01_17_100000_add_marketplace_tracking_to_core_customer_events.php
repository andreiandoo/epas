<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            // Add marketplace tracking columns
            $table->unsignedBigInteger('marketplace_event_id')->nullable()->after('event_id')
                ->comment('FK to marketplace_events table');
            $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('tenant_id')
                ->comment('FK to marketplace_clients table');

            // Add indexes for marketplace queries
            $table->index(['marketplace_event_id', 'event_type', 'created_at'], 'idx_mp_event_tracking');
            $table->index(['marketplace_client_id', 'event_type', 'created_at'], 'idx_mp_client_tracking');
        });

        // Also add to core_sessions for marketplace tracking
        Schema::table('core_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_event_id')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->dropIndex('idx_mp_event_tracking');
            $table->dropIndex('idx_mp_client_tracking');
            $table->dropColumn(['marketplace_event_id', 'marketplace_client_id']);
        });

        Schema::table('core_sessions', function (Blueprint $table) {
            $table->dropColumn(['marketplace_event_id', 'marketplace_client_id']);
        });
    }
};
