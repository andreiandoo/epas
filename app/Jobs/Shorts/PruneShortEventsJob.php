<?php

namespace App\Jobs\Shorts;

use App\Models\ShortEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Prunes raw telemetry past its retention window (D6).
 *
 * short_events is by far the fastest-growing table in the feature: a single
 * scroll session writes dozens of rows. Everything worth keeping has already
 * been rolled up into shorts.* aggregates, short_retention and (once B5 lands)
 * short_analytics_daily, so the raw rows only need to survive long enough for
 * those jobs to have run and for a human to debug a bad day.
 *
 * Deletes in chunks: one unbounded DELETE over months of rows locks the table
 * and can time out on the queue worker.
 */
class PruneShortEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK = 5000;

    private const MAX_CHUNKS = 200;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(): void
    {
        $days = max(1, (int) config('shorts.telemetry.retention_days', 90));
        $cutoff = now()->subDays($days);

        $deleted = 0;

        for ($i = 0; $i < self::MAX_CHUNKS; $i++) {
            $batch = ShortEvent::query()
                ->where('created_at', '<', $cutoff)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $batch;

            if ($batch < self::CHUNK) {
                break;
            }
        }

        if ($deleted > 0) {
            Log::info('PruneShortEventsJob: pruned raw telemetry', [
                'deleted' => $deleted,
                'older_than' => $cutoff->toDateTimeString(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PruneShortEventsJob failed', ['error' => $e->getMessage()]);
    }
}
