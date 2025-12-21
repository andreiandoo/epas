<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds commission tracking fields to orders table.
     * For marketplace orders, we track:
     * - Tixello's 1% platform fee (always applied)
     * - Marketplace's commission (configurable)
     * - Organizer's revenue (remainder)
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Link order to organizer (for marketplace tenants)
            $table->foreignId('organizer_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('marketplace_organizers')
                ->nullOnDelete();

            // Commission breakdown (in the order's currency)
            $table->decimal('tixello_commission', 10, 2)->nullable()->after('promo_discount');
            $table->decimal('marketplace_commission', 10, 2)->nullable()->after('tixello_commission');
            $table->decimal('organizer_revenue', 10, 2)->nullable()->after('marketplace_commission');

            // Payout tracking
            $table->foreignId('payout_id')
                ->nullable()
                ->after('organizer_revenue')
                ->constrained('marketplace_payouts')
                ->nullOnDelete();

            // Indexes
            $table->index('organizer_id');
            $table->index('payout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['organizer_id']);
            $table->dropForeign(['payout_id']);
            $table->dropIndex(['organizer_id']);
            $table->dropIndex(['payout_id']);
            $table->dropColumn([
                'organizer_id',
                'tixello_commission',
                'marketplace_commission',
                'organizer_revenue',
                'payout_id',
            ]);
        });
    }
};
