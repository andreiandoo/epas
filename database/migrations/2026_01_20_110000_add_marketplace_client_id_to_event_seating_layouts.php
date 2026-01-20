<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_seating_layouts', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')
                ->nullable()
                ->after('event_id')
                ->constrained('marketplace_clients')
                ->onDelete('set null');

            // marketplace_event_id for marketplace clients (alternative to event_id)
            $table->foreignId('marketplace_event_id')
                ->nullable()
                ->after('marketplace_client_id')
                ->constrained('marketplace_events')
                ->onDelete('cascade');

            $table->boolean('is_partner')->default(false)->after('marketplace_event_id');
            $table->text('partner_notes')->nullable()->after('is_partner');

            $table->index('marketplace_client_id');
            $table->index('marketplace_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_seating_layouts', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropForeign(['marketplace_event_id']);
            $table->dropIndex(['marketplace_client_id']);
            $table->dropIndex(['marketplace_event_id']);
            $table->dropColumn(['marketplace_client_id', 'marketplace_event_id', 'is_partner', 'partner_notes']);
        });
    }
};
