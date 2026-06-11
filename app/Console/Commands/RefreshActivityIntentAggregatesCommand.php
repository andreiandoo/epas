<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes cached aggregates on activities for intent landing pages.
 *
 * A3 scope (current):
 *   - cheapest_price_cents — min of active variant prices on each activity
 *
 * A4 scope (planned, when SlotResolver lands):
 *   - next_session_at
 *   - has_session_today / _tomorrow / _this_weekend
 *
 * Designed to run frequently (default: hourly via scheduler). Idempotent
 * and safe to invoke on demand. Updates happen via direct DB::update so
 * model observers don't fire (these are pure denormalised aggregates and
 * shouldn't loop back into the observer chain).
 *
 * Usage:
 *   php artisan activities:refresh-intent-aggregates
 *   php artisan activities:refresh-intent-aggregates --marketplace=3
 *   php artisan activities:refresh-intent-aggregates --activity=42
 *   php artisan activities:refresh-intent-aggregates --include-unpublished
 */
class RefreshActivityIntentAggregatesCommand extends Command
{
    protected $signature = 'activities:refresh-intent-aggregates
        {--marketplace= : Scope to a single marketplace_client_id}
        {--activity= : Refresh a single activity by id}
        {--include-unpublished : Also process draft activities (default: published only)}';

    protected $description = 'Recompute cheapest_price_cents (and, in A4, session aggregates) on activities for intent landing pages.';

    public function handle(): int
    {
        $query = Activity::query()->with(['variants']);

        // Default scope: published only — that's the working set the public site sees.
        // Admin can opt in to draft activities with --include-unpublished (useful when
        // an organizer is finalising prices before going live).
        if (! $this->option('include-unpublished')) {
            $query->where('is_published', true);
        }

        if ($marketplaceId = $this->option('marketplace')) {
            $query->where('marketplace_client_id', $marketplaceId);
        }
        if ($activityId = $this->option('activity')) {
            $query->where('id', $activityId);
        }

        $updated = 0;
        $unchanged = 0;

        $query->chunkById(200, function ($activities) use (&$updated, &$unchanged) {
            foreach ($activities as $activity) {
                $newCheapest = $this->computeCheapestPriceCents($activity);

                if ($newCheapest === $activity->cheapest_price_cents) {
                    $unchanged++;
                    continue;
                }

                // Direct UPDATE — bypasses model observers so this denormalisation
                // pass doesn't trigger ActivityVariantObserver in a loop.
                DB::table('activities')
                    ->where('id', $activity->id)
                    ->update(['cheapest_price_cents' => $newCheapest]);

                $updated++;
            }
        });

        $this->info("Refreshed cheapest_price_cents on {$updated} activities ({$unchanged} unchanged).");
        return self::SUCCESS;
    }

    /**
     * Smallest price_cents among active variants. NULL when the activity has
     * no active variants — public listings then hide the "de la X lei" badge.
     */
    protected function computeCheapestPriceCents(Activity $activity): ?int
    {
        if (! $activity->relationLoaded('variants')) {
            $activity->load('variants');
        }

        $prices = $activity->variants
            ->where('is_active', true)
            ->pluck('price_cents')
            ->filter(fn ($p) => $p !== null && $p > 0)
            ->all();

        return empty($prices) ? null : (int) min($prices);
    }
}
