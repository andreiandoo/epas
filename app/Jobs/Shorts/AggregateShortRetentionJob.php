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
 * Builds the drop-off curve from raw telemetry (D4).
 *
 * Buckets each reported watch_ratio into a decile, so the analytics page can show
 * exactly where viewers leave — the single most actionable number an organiser
 * gets about a short.
 *
 * Runs per day and replaces that day's rows, so a re-run cannot double-count.
 */
class AggregateShortRetentionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(protected ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?? now()->subDay()->toDateString();

        $rows = ShortEvent::query()
            ->whereNotNull('watch_ratio')
            ->whereDate('created_at', $date)
            ->whereIn('type', [ShortEvent::TYPE_VIEW, ShortEvent::TYPE_SKIP, ShortEvent::TYPE_COMPLETE])
            ->get(['short_id', 'watch_ratio']);

        if ($rows->isEmpty()) {
            return;
        }

        $buckets = [];

        foreach ($rows as $row) {
            // 1.0 belongs in the last bucket, not an eleventh one.
            $bucket = min(9, (int) floor(((float) $row->watch_ratio) * 10));
            $key = $row->short_id.':'.$bucket;
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        DB::transaction(function () use ($buckets, $date) {
            DB::table('short_retention')->where('date', $date)->delete();

            $insert = [];

            foreach ($buckets as $key => $count) {
                [$shortId, $bucket] = explode(':', $key);
                $insert[] = [
                    'short_id' => (int) $shortId,
                    'date' => $date,
                    'bucket' => (int) $bucket,
                    'count' => $count,
                ];
            }

            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('short_retention')->insert($chunk);
            }
        });

        Log::info('AggregateShortRetentionJob: built retention curve', [
            'date' => $date,
            'rows' => count($buckets),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AggregateShortRetentionJob failed', ['error' => $e->getMessage()]);
    }
}
