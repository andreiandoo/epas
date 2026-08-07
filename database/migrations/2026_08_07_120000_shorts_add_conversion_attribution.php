<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shoppable shorts (B1) + feed-segment attribution (D4).
 *
 * `shorts` gains the conversion aggregates; `orders` gains the last-touch
 * attribution columns. Both additive and nullable — nothing existing changes
 * shape, and an order with no short attached simply carries nulls.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shorts')) {
            Schema::table('shorts', function (Blueprint $table) {
                if (! Schema::hasColumn('shorts', 'conversions')) {
                    $table->unsignedBigInteger('conversions')->default(0);
                }

                if (! Schema::hasColumn('shorts', 'revenue_cents')) {
                    $table->unsignedBigInteger('revenue_cents')->default(0);
                }

                if (! Schema::hasColumn('shorts', 'revenue_currency')) {
                    $table->string('revenue_currency', 3)->nullable();
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'source_short_id')) {
                    $table->unsignedBigInteger('source_short_id')->nullable()->index();
                }

                // Which feed segment sold it — for_you|following|nearby|... (D4).
                if (! Schema::hasColumn('orders', 'source_feed')) {
                    $table->string('source_feed', 32)->nullable();
                }

                // Stamped when the conversion was counted, so a re-run of the
                // attribution path can tell "already credited" from "new".
                if (! Schema::hasColumn('orders', 'short_attributed_at')) {
                    $table->timestamp('short_attributed_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shorts')) {
            Schema::table('shorts', function (Blueprint $table) {
                $table->dropColumn(['conversions', 'revenue_cents', 'revenue_currency']);
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['source_short_id', 'source_feed', 'short_attributed_at']);
            });
        }
    }
};
