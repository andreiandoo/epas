<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            // Drop the wrong FK that points to marketplace_events
            $table->dropForeign('service_orders_marketplace_event_id_foreign');

            // Add correct FK pointing to events table
            $table->foreign('marketplace_event_id')
                ->references('id')
                ->on('events')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['marketplace_event_id']);

            $table->foreign('marketplace_event_id')
                ->references('id')
                ->on('marketplace_events')
                ->onDelete('set null');
        });
    }
};
