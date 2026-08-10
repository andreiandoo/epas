<?php

namespace App\Jobs\Shorts;

use App\Models\ShortEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Distils "who has seen what" out of raw telemetry into short_impressions (D5).
 *
 * The ranker's seen penalty reads this instead of short_events, for two reasons:
 * it is one row per (viewer, short) rather than one per playback, and it survives
 * the retention prune — otherwise a short would start reappearing in someone's
 * feed 90 days later purely because the evidence was deleted.
 */
class SyncShortImpressionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(protected int $sinceMinutes = 30) {}

    public function handle(): void
    {
        $since = now()->subMinutes($this->sinceMinutes);

        $seen = ShortEvent::query()
            ->selectRaw('marketplace_customer_id, short_id, MAX(created_at) as last_seen')
            ->whereNotNull('marketplace_customer_id')
            ->where('created_at', '>=', $since)
            ->whereIn('type', [ShortEvent::TYPE_VIEW, ShortEvent::TYPE_COMPLETE])
            ->groupBy('marketplace_customer_id', 'short_id')
            ->get();

        if ($seen->isEmpty()) {
            return;
        }

        foreach ($seen->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('short_impressions')->updateOrInsert(
                    [
                        'marketplace_customer_id' => $row->marketplace_customer_id,
                        'short_id' => $row->short_id,
                    ],
                    ['last_seen' => $row->last_seen],
                );
            }
        }

        Log::info('SyncShortImpressionsJob: refreshed the seen store', ['rows' => $seen->count()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncShortImpressionsJob failed', ['error' => $e->getMessage()]);
    }
}
