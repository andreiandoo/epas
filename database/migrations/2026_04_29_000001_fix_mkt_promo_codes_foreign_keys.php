<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mkt_promo_codes')) {
            return;
        }

        Schema::table('mkt_promo_codes', function (Blueprint $table) {
            try {
                $table->dropForeign('mkt_promo_codes_marketplace_event_id_foreign');
            } catch (\Throwable $e) {
            }

            $table->foreign('marketplace_event_id')
                ->references('id')
                ->on('events')
                ->nullOnDelete();
        });

        Schema::table('mkt_promo_codes', function (Blueprint $table) {
            try {
                $table->dropForeign('mkt_promo_codes_ticket_type_id_foreign');
            } catch (\Throwable $e) {
            }

            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mkt_promo_codes')) {
            return;
        }

        Schema::table('mkt_promo_codes', function (Blueprint $table) {
            try {
                $table->dropForeign(['marketplace_event_id']);
            } catch (\Throwable $e) {
            }

            $table->foreign('marketplace_event_id')
                ->references('id')
                ->on('marketplace_events')
                ->nullOnDelete();
        });

        Schema::table('mkt_promo_codes', function (Blueprint $table) {
            try {
                $table->dropForeign(['ticket_type_id']);
            } catch (\Throwable $e) {
            }

            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('marketplace_ticket_types')
                ->nullOnDelete();
        });
    }
};
