<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prices in the operator's currency (Viaqui: pounds in the United Kingdom, francs in Switzerland…).
 *
 * - marketplace_clients.currency: the marketplace's own currency. The model and the checkout already read it
 *   (falling back to 'RON'), but the column was missing on production.
 * - marketplace_organizers.currency: the currency the operator sells in. Null = the marketplace's currency, which
 *   is what every existing operator keeps.
 * - activities.currency + cheapest_price_eur_cents: the currency of `cheapest_price_cents` and its value in euro,
 *   which is what price filters and sorting compare across countries. Both are filled by
 *   App\Services\Activities\ActivityCurrency; null until then.
 *
 * Every column is nullable and nothing is back-filled here: existing marketplaces behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_clients', 'currency')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->string('currency', 3)->nullable();
            });
        }
        if (! Schema::hasColumn('marketplace_organizers', 'currency')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                $table->string('currency', 3)->nullable();
            });
        }
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'currency')) {
                $table->string('currency', 3)->nullable();
            }
            if (! Schema::hasColumn('activities', 'cheapest_price_eur_cents')) {
                $table->unsignedInteger('cheapest_price_eur_cents')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'cheapest_price_eur_cents')) {
                $table->dropIndex(['cheapest_price_eur_cents']);
                $table->dropColumn('cheapest_price_eur_cents');
            }
            if (Schema::hasColumn('activities', 'currency')) {
                $table->dropColumn('currency');
            }
        });
        if (Schema::hasColumn('marketplace_organizers', 'currency')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
        // marketplace_clients.currency is left in place: other code has read it since before this migration.
    }
};
