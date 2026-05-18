<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase B of the discount-aware tax declaration work — materializes the
     * "fabricated" series allocations that until now were computed on the
     * fly inside MarketplaceTaxTemplate.
     *
     * One row per (event, ticket_type, promo_code, is_intrinsic_red) tuple.
     * Carries the auto-derived series prefix (e.g. "GA-HAILAQFEEL!") and
     * the allocated quantity at that tier. qty_sold is denormalised here
     * for fast reads from tax / cerere avizare / PV distrugere templates;
     * the canonical source remains the orders/tickets data and can be
     * recomputed via SeriesAllocator::syncForEvent().
     */
    public function up(): void
    {
        Schema::create('event_ticket_type_promo_series', function (Blueprint $table) {
            $table->id();

            // Nullable for "all_events" promos that aren't anchored to one
            // marketplace event. Per-row uniqueness is enforced on the
            // (ticket_type_id, promo_code_id, is_intrinsic_red) triplet
            // below — the event id is informational only.
            $table->unsignedBigInteger('marketplace_event_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_type_id');
            // Promo FK — null when this row is the parent (full-price) or
            // the intrinsic RED tier. SET NULL on delete so a deleted promo
            // doesn't take its historical series row with it (the row turns
            // into a "stale" record we can clean up via maintenance).
            $table->unsignedBigInteger('promo_code_id')->nullable();
            // True only for the RED/sale_price intrinsic discount on the
            // ticket type. Differentiates from parent (full-price) and from
            // promo rows when both have promo_code_id=NULL.
            $table->boolean('is_intrinsic_red')->default(false);

            $table->string('series_prefix', 255)->default('');
            $table->unsignedInteger('qty_allocated')->default(0);
            $table->unsignedInteger('qty_sold')->default(0);

            $table->timestamps();

            // Uniqueness across the tier identity — same ticket_type can't
            // have two rows for the same promo (or two RED rows, or two
            // parent rows). Promo_code_id NULL is fine for parent vs RED
            // because is_intrinsic_red distinguishes them.
            $table->unique(
                ['ticket_type_id', 'promo_code_id', 'is_intrinsic_red'],
                'ettps_unique_tier'
            );

            // FK to ticket_types — CASCADE because a ticket type going away
            // takes its series with it.
            $table->foreign('ticket_type_id')
                ->references('id')->on('ticket_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ticket_type_promo_series');
    }
};
