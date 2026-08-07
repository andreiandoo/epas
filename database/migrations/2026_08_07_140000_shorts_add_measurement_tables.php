<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2 — measurement and scale (D4, D5).
 *
 *  - short_retention   : the drop-off curve, in watch deciles per short per day
 *  - short_impressions : cross-session seen store, so the ranker stops repeating
 *                        a short after the raw telemetry has been pruned
 *  - shorts.trending_score : velocity relative to baseline, recomputed on a schedule
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('short_retention')) {
            Schema::create('short_retention', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                // 0..9 — which tenth of the clip the viewer reached.
                $table->unsignedTinyInteger('bucket');
                $table->unsignedBigInteger('count')->default(0);

                $table->unique(['short_id', 'date', 'bucket'], 'short_retention_unique');
            });
        }

        if (! Schema::hasTable('short_impressions')) {
            Schema::create('short_impressions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_customer_id')->index();
                $table->foreignId('short_id')->index();
                $table->timestamp('last_seen')->nullable();

                $table->unique(['marketplace_customer_id', 'short_id'], 'short_impressions_unique');
            });
        }

        if (Schema::hasTable('shorts') && ! Schema::hasColumn('shorts', 'trending_score')) {
            Schema::table('shorts', function (Blueprint $table) {
                $table->decimal('trending_score', 8, 3)->default(0)->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('short_retention');
        Schema::dropIfExists('short_impressions');

        if (Schema::hasTable('shorts') && Schema::hasColumn('shorts', 'trending_score')) {
            Schema::table('shorts', fn (Blueprint $table) => $table->dropColumn('trending_score'));
        }
    }
};
