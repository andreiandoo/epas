<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds IP + user-agent audit fields to marketplace_ticket_transfers so
 * the customer-facing transfer flow can record who/from-where the move
 * happened. These are independent of the existing pending-accept flow
 * fields — they apply to both the old and the new instant-transfer
 * code paths.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_ticket_transfers')) {
            return;
        }

        Schema::table('marketplace_ticket_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_transfers', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('marketplace_ticket_transfers', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_ticket_transfers')) {
            return;
        }

        Schema::table('marketplace_ticket_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_transfers', 'user_agent')) {
                $table->dropColumn('user_agent');
            }
            if (Schema::hasColumn('marketplace_ticket_transfers', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};
