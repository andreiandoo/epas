<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity ↔ Activity many-to-many pivot for cross-sell / upsell on the
 * public detail page and admin Conexiuni tab.
 *
 * Two flavors of connection:
 *   - source='auto'    — added by ActivityObserver when a new activity is
 *                        created under the same organizer. Bidirectional
 *                        siblings get auto-linked.
 *   - source='manual'  — added explicitly by the admin in the Filament UI.
 *                        Survives the auto-sync (insertOrIgnore never
 *                        overwrites an existing row regardless of source).
 *
 * `UNIQUE(activity_id, related_activity_id)` guarantees no duplicates per
 * direction; we store both directions explicitly so reads don't have to
 * union (A → B AND B → A on every page render).
 *
 * Non-breaking: brand new table; cascade-deleted from either activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_related')) {
            return;
        }

        Schema::create('activity_related', function (Blueprint $table) {
            $table->id();

            // Bare unsignedBigInteger + explicit foreign() so we can reference
            // the SAME parent table twice (Laravel's constrained() auto-naming
            // would collide).
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('related_activity_id');

            $table->foreign('activity_id', 'activity_related_main_fk')
                ->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('related_activity_id', 'activity_related_target_fk')
                ->references('id')->on('activities')->cascadeOnDelete();

            // 'auto' = inserted by observer; 'manual' = added in admin form.
            $table->string('source', 16)->default('manual');

            // Lets admin reorder cards on the public detail page if desired.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['activity_id', 'related_activity_id'], 'activity_related_pair_unique');
            $table->index(['related_activity_id'], 'activity_related_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_related');
    }
};
