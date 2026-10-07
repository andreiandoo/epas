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
    /**
     * One currency per operator: when the operator has chosen one, every variant of his is saved in it, whatever
     * the form sent. Operators without a choice (all of them before the column existed) are left alone.
     */
    public function saving(ActivityVariant $variant): void
    {
        if (! \App\Services\Activities\ActivityCurrency::ready() || ! $variant->activity_id) {
            return;
        }
        $row = DB::table('activities')
            ->leftJoin('marketplace_organizers', 'marketplace_organizers.id', '=', 'activities.marketplace_organizer_id')
            ->leftJoin('marketplace_clients', 'marketplace_clients.id', '=', 'activities.marketplace_client_id')
            ->where('activities.id', $variant->activity_id)
            ->first(['marketplace_organizers.currency as organizer_currency', 'marketplace_clients.currency as client_currency']);
        $own = strtoupper((string) ($row?->organizer_currency ?? ''));
        if ($own !== '') {
            $variant->currency = $own;
        } elseif (! $variant->exists && ! $variant->isDirty('currency')) {
            // a new variant whose form did not say: the marketplace's currency rather than the column default (RON)
            $variant->currency = strtoupper((string) ($row?->client_currency ?? '')) ?: \App\Services\Activities\ActivityCurrency::FALLBACK;
        }
    }

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
        // The cheapest active price, its currency and its value in euro, in one place
        // (also run daily, because the euro value follows the exchange rate).
        \App\Services\Activities\ActivityCurrency::refresh($activityId);
    }
}
