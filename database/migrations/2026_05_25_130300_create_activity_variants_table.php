<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module — A1 / step 4: pricing variants per activity.
 *
 * Equivalent of `ticket_types` for activities. One activity can have
 * multiple variants — e.g.:
 *   - "Adult" — 95 lei, takes 1 capacity slot, ages 14+
 *   - "Copil"  — 45 lei, takes 1 capacity slot, ages 4-13
 *   - "Grup 4 persoane" — 340 lei, takes 4 capacity slots
 *
 * Field shape intentionally mirrors `ticket_types` for the columns where
 * the semantics are the same (currency, sku, commission_*, perks,
 * min_per_order/max_per_order, is_active, sort_order). That keeps the
 * checkout layer reusable: a cart line item points at either a
 * `ticket_type_id` (event) or an `activity_variant_id` (activity); the
 * pricing / commission code branches only on which FK is non-null.
 *
 * Non-breaking: brand new table; cascade-delete from parent activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_variants')) {
            return;
        }

        Schema::create('activity_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')
                ->constrained('activities')
                ->cascadeOnDelete();

            $table->jsonb('name');           // translatable
            $table->jsonb('description')->nullable();

            $table->string('sku', 64)->nullable();

            // Pricing in cents, currency separately. Same convention as ticket_types.
            $table->integer('price_cents')->default(0);
            $table->string('currency', 3)->default('RON');

            // Age band — informational. Used to surface "Adult" vs "Copil" etc.
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();

            // capacity_share = how many slot seats this variant consumes per unit.
            // Default 1 (Adult/Copil = 1 person each). A "Grup 4 persoane" variant
            // sets this to 4, so buying 1 of it consumes 4 of the slot's capacity.
            $table->unsignedSmallInteger('capacity_share')->default(1);

            // Order quantity constraints.
            $table->unsignedSmallInteger('min_per_order')->default(0);
            $table->unsignedSmallInteger('max_per_order')->default(10);

            // Same commission override pattern as ticket_types (per-type override of
            // organizer/marketplace defaults). NULL = inherit.
            $table->string('commission_type', 16)->nullable();   // 'percentage' | 'fixed' | 'both'
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('commission_fixed', 10, 2)->nullable();
            $table->string('commission_mode', 16)->nullable();   // 'included' | 'added_on_top'

            // Perks (same RO label model as ticket_types.perks — JSON array of strings).
            $table->jsonb('perks')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_refundable')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activity_id', 'is_active', 'sort_order'], 'act_variants_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_variants');
    }
};
