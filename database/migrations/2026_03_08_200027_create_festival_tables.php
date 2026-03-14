<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Stages (scene) ──────────────────────────────────
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('stage_type')->default('main')->comment('main|secondary|acoustic|dj|workshop|kids');
            $table->json('technical_specs')->nullable()->comment('JSON: sound system, lighting rig, stage dimensions');
            $table->json('location_coordinates')->nullable()->comment('JSON: {lat, lng} within venue');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // ── Festival days ───────────────────────────────────
        Schema::create('festival_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete()->comment('Parent festival event');
            $table->string('name')->comment('e.g. Day 1, Friday, Pre-Party');
            $table->date('date');
            $table->time('gates_open')->nullable();
            $table->time('gates_close')->nullable();
            $table->string('status')->default('scheduled')->comment('scheduled|active|completed|cancelled');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('capacity_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'event_id']);
        });

        // ── Lineup slots (artist on stage at time) ──────────
        Schema::create('festival_lineup_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_artist_name')->nullable()->comment('For guest artists not in system');
            $table->text('description')->nullable()->comment('Performance description for this slot');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('slot_type')->default('performance')->comment('performance|dj_set|workshop|talk|ceremony|special');
            $table->boolean('is_headliner')->default(false);
            $table->boolean('is_secret_guest')->default(false);
            $table->string('status')->default('confirmed')->comment('tentative|confirmed|cancelled|rescheduled');
            $table->integer('display_position')->default(0)->comment('Position order on poster/lineup display');
            $table->string('display_tier')->default('regular')->comment('headliner|sub_headliner|regular|support|emerging');
            $table->string('image_override_url')->nullable()->comment('Custom image for this appearance');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['festival_day_id', 'stage_id', 'start_time']);
            $table->index(['tenant_id', 'display_position']);
        });

        // ── Festival passes (multi-day / full-fest) ─────────
        Schema::create('festival_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('pass_type')->comment('full_festival|multi_day|single_day|vip|backstage');
            $table->integer('price_cents');
            $table->integer('compare_at_price_cents')->nullable()->comment('Original price for showing discount');
            $table->string('currency', 3)->default('RON');
            $table->json('included_day_ids')->nullable()->comment('Null = all days; array of festival_day IDs');
            $table->json('included_stage_ids')->nullable()->comment('Null = all stages; array for VIP areas');
            $table->json('included_addon_ids')->nullable()->comment('Addons bundled with this pass');
            $table->integer('quota_total')->nullable();
            $table->integer('quota_sold')->default(0);
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->string('status')->default('active')->comment('draft|active|paused|sold_out|expired');
            $table->boolean('is_refundable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('perks')->nullable()->comment('JSON: list of perks/benefits');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // ── Festival add-ons (camping, parking, etc.) ───────
        Schema::create('festival_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('category')->comment('camping|parking|accommodation|food|merch|transport|locker|shower|vip_upgrade|experience');
            $table->string('addon_type')->default('per_person')->comment('per_person|per_unit|per_day|flat');
            $table->integer('price_cents');
            $table->integer('compare_at_price_cents')->nullable();
            $table->string('currency', 3)->default('RON');
            $table->integer('quota_total')->nullable();
            $table->integer('quota_sold')->default(0);
            $table->integer('max_per_order')->nullable()->comment('Null = unlimited');
            $table->json('options')->nullable()->comment('JSON: size variants, zones, date options');
            $table->json('included_day_ids')->nullable()->comment('Which festival days this applies to');
            $table->string('image_url')->nullable();
            $table->string('status')->default('active')->comment('draft|active|paused|sold_out');
            $table->boolean('requires_pass')->default(true)->comment('Must also have a festival pass');
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // ── Festival bundles (ticket combinations/offers) ───
        Schema::create('festival_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('bundle_price_cents')->comment('Discounted bundle price');
            $table->integer('original_price_cents')->comment('Sum of individual items');
            $table->string('currency', 3)->default('RON');
            $table->json('items')->comment('JSON: [{type: "pass"|"addon", id: X, quantity: N}]');
            $table->integer('quota_total')->nullable();
            $table->integer('quota_sold')->default(0);
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->string('status')->default('active')->comment('draft|active|paused|sold_out|expired');
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // ── Incremental offers (early bird tiers) ───────────
        Schema::create('festival_incremental_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('offerable_type')->comment('FestivalPass or FestivalAddon');
            $table->unsignedBigInteger('offerable_id');
            $table->string('tier_name')->comment('e.g. Super Early Bird, Early Bird, Regular, Late');
            $table->integer('price_cents');
            $table->string('currency', 3)->default('RON');
            $table->integer('quota')->nullable()->comment('Tickets available at this price tier');
            $table->integer('quota_sold')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0)->comment('Lower = earlier tier');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['offerable_type', 'offerable_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_incremental_offers');
        Schema::dropIfExists('festival_bundles');
        Schema::dropIfExists('festival_addons');
        Schema::dropIfExists('festival_passes');
        Schema::dropIfExists('festival_lineup_slots');
        Schema::dropIfExists('festival_days');
        Schema::dropIfExists('stages');
    }
};
