<?php

namespace App\Observers;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;

/**
 * Observer for the Activity model.
 *
 * Today its only job is to maintain auto-related siblings under the same
 * organizer (Conexiuni tab) — every new activity that shares an organizer
 * with existing ones gets bidirectional 'auto' rows added in
 * `activity_related`. Manual rows are never touched here:
 * `insertOrIgnore` skips any pair that already exists, regardless of source.
 *
 * Behavior is one-way: created → sync. Deleting an auto row in the admin
 * UI is intentional and persists — we don't re-sync on every update of
 * an existing activity. If the admin wants a removed sibling to stay
 * gone permanently, they should also rename it from source='auto' to
 * 'manual' (or just leave it deleted; observer only fires on create).
 *
 * Edge cases:
 *   - Soft-deleted siblings are excluded from sync (no point linking to
 *     a hidden activity).
 *   - Activities without an organizer (rare but possible) skip sync
 *     entirely — there's no group to link to.
 */
class ActivityObserver
{
    public function created(Activity $activity): void
    {
        $this->syncAutoSiblings($activity);
    }

    /**
     * If the organizer changes on update, re-run sync. The previous
     * organizer's siblings stay linked (we don't unlink old auto rows —
     * admin can clean those up manually).
     */
    public function updated(Activity $activity): void
    {
        if ($activity->wasChanged('marketplace_organizer_id')) {
            $this->syncAutoSiblings($activity);
        }
    }

    /**
     * Insert bidirectional auto-pairs for every other published or draft
     * activity that shares this activity's organizer.
     */
    protected function syncAutoSiblings(Activity $activity): void
    {
        if (! $activity->marketplace_organizer_id) {
            return;
        }

        $siblingIds = Activity::query()
            ->where('marketplace_client_id', $activity->marketplace_client_id)
            ->where('marketplace_organizer_id', $activity->marketplace_organizer_id)
            ->where('id', '<>', $activity->id)
            ->pluck('id');

        if ($siblingIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($siblingIds as $siblingId) {
            // X → sibling
            $rows[] = [
                'activity_id' => $activity->id,
                'related_activity_id' => $siblingId,
                'source' => 'auto',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            // sibling → X (bidirectional)
            $rows[] = [
                'activity_id' => $siblingId,
                'related_activity_id' => $activity->id,
                'source' => 'auto',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // insertOrIgnore — skip pairs that already exist (whether manual or
        // a previous auto run). Never overwrites an admin's manual link.
        DB::table('activity_related')->insertOrIgnore($rows);
    }
}
