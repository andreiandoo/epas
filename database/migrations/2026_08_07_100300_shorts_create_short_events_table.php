<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw telemetry stream for shorts. Append-only, batched from the mobile client,
 * rolled up into shorts.* aggregates by AggregateShortStatsJob and pruned by
 * retention (see D6).
 *
 * created_at only (no updated_at) — rows are never mutated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('short_events')) {
            return;
        }

        Schema::create('short_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_customer_id')->nullable()->index();
            $table->string('session_id', 64)->nullable();

            // impression|view|complete|like|unlike|save|unsave|share|cta_click|skip
            $table->string('type', 32);

            $table->unsignedInteger('watch_ms')->nullable();
            $table->decimal('watch_ratio', 4, 3)->nullable();
            $table->string('feed', 32)->nullable(); // for_you|following|nearby|featured|event|artist
            $table->json('meta')->nullable();       // poster_variant_id, promotion_id, ...
            $table->timestamp('created_at')->useCurrent();

            $table->index(['short_id', 'type']);
            $table->index(['created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_events');
    }
};
