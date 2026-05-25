<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\ActivityVariant;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the parent activity's `cheapest_price_cents` in sync after every
 * variant write. Without this observer the cache only updates when the
 * scheduled `activities:refresh-intent-aggregates` runs (hourly).
 *
 * Why an observer instead of a model boot listener:
 *   - Cleaner separation: model stays focused on persistence.
 *   - Easier to unit-test (mock the observer in feature tests).
 *   - Matches the existing pattern (every model that needs side-effects
 *     uses an Observer registered in AppServiceProvider).
 *
 * Why DB::update instead of $activity->update():
 *   - Avoids triggering Activity::saved hooks (we don't have any yet, but
 *     defensively this is a pure denormalisation pass, not a domain edit).
 *   - Skips the `updated_at` bump on the activity row — saving a variant
 *     shouldn't make the parent activity look newer in the admin listing.
 *
 * Why all three hooks (saved / deleted / restored):
 *   - `saved` fires on both INSERT and UPDATE → catches new variants AND
 *     edits to existing ones (price changes, deactivation, etc.).
 *   - `deleted` → soft-delete or hard-delete: variant disappears from the
 *     active pool, so the cheapest must be recomputed.
 *   - `restored` → soft-deleted variant restored: it may now be the new
 *     cheapest.
 */
class ActivityVariantObserver
{
    public function saved(ActivityVariant $variant): void
    {
        $this->refreshParentCheapest($variant->activity_id);
    }

    public function deleted(ActivityVariant $variant): void
    {
        $this->refreshParentCheapest($variant->activity_id);
    }

    public function restored(ActivityVariant $variant): void
    {
        $this->refreshParentCheapest($variant->activity_id);
    }

    protected function refreshParentCheapest(?int $activityId): void
    {
        if (! $activityId) {
            return;
        }

        // SUM-of-row aggregate in SQL — much faster than loading all variants
        // into PHP when an activity has many of them.
        $cheapest = DB::table('activity_variants')
            ->where('activity_id', $activityId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('price_cents', '>', 0)
            ->min('price_cents');

        // The activity might be soft-deleted; still update its cached price so the
        // value is correct if it ever gets restored. Filter by id only.
        DB::table('activities')
            ->where('id', $activityId)
            ->update(['cheapest_price_cents' => $cheapest]);
    }
}
