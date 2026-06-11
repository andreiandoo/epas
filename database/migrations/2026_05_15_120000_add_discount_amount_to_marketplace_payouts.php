<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an explicit discount_amount column to marketplace_payouts.
 *
 * Previously the manual-payout form had only gross / commission / fees
 * inputs — so when an event had any promo-code discount on its tickets,
 * the implied net (gross − commission − fees) overstated what should
 * actually be paid out by the discount amount. The form's "Suma netă"
 * field disagreed with the "Sold disponibil" header and with the event
 * "Vânzări" tab's net.
 *
 * Defaults to 0 so historical payouts and any imported rows are
 * unaffected.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)
                ->default(0)
                ->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
