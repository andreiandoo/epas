<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3 — Interests + Traveler types (GYG-style discovery taxonomies).
 *
 * Two per-marketplace-client taxonomies attachable to activities (many-to-many):
 *   - interests        — thematic angle (mystery, adventure, culture, food…)
 *   - traveler_types   — who it's for (couples, families, solo, groups…)
 *
 * Same shape as other marketplace taxonomies (slug + translatable name/desc +
 * icon/color + visibility). Guarded with hasTable so re-runs are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['interests', 'traveler_types'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) use ($table) {
                    $t->id();
                    $t->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
                    $t->string('slug', 191);
                    $t->json('name');
                    $t->json('description')->nullable();
                    $t->string('icon_emoji', 16)->nullable();
                    $t->string('color', 16)->nullable();
                    $t->json('seo')->nullable();
                    $t->unsignedInteger('sort_order')->default(0);
                    $t->boolean('is_visible')->default(true);
                    $t->timestamps();
                    $t->unique(['marketplace_client_id', 'slug'], $table . '_mp_slug_unique');
                    $t->index(['marketplace_client_id', 'is_visible'], $table . '_mp_vis_idx');
                });
            }
        }

        if (! Schema::hasTable('activity_interest')) {
            Schema::create('activity_interest', function (Blueprint $t) {
                $t->id();
                $t->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $t->foreignId('interest_id')->constrained('interests')->cascadeOnDelete();
                $t->unsignedInteger('sort_order')->default(0);
                $t->unique(['activity_id', 'interest_id'], 'activity_interest_unique');
            });
        }

        if (! Schema::hasTable('activity_traveler_type')) {
            Schema::create('activity_traveler_type', function (Blueprint $t) {
                $t->id();
                $t->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $t->foreignId('traveler_type_id')->constrained('traveler_types')->cascadeOnDelete();
                $t->unsignedInteger('sort_order')->default(0);
                $t->unique(['activity_id', 'traveler_type_id'], 'activity_traveler_type_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_traveler_type');
        Schema::dropIfExists('activity_interest');
        Schema::dropIfExists('traveler_types');
        Schema::dropIfExists('interests');
    }
};
