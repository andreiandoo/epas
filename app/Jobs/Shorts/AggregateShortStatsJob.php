<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use App\Models\ShortEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Rolls the raw short_events stream up into the denormalised counters on
 * `shorts`, so the feed and the admin tables never have to scan telemetry.
 *
 * Recomputes from scratch per short (rather than incrementing) — idempotent, so
 * a re-run after a failure can never double-count.
 */
class AggregateShortStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 300;

    /**
     * @param  array<int, int>|null  $shortIds  Limit the run; null recomputes every short touched recently.
     */
    public function __construct(
        protected ?array $shortIds = null,
        protected int $sinceMinutes = 60,
    ) {}

    public function handle(): void
    {
        $ids = $this->shortIds ?? $this->recentlyTouchedShortIds();

        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, 200) as $chunk) {
            $this->recompute($chunk);
        }

        Log::info('AggregateShortStatsJob: recomputed shorts stats', ['count' => count($ids)]);
    }

    /**
     * @return array<int, int>
     */
    protected function recentlyTouchedShortIds(): array
    {
        return ShortEvent::query()
            ->where('created_at', '>=', now()->subMinutes($this->sinceMinutes))
            ->distinct()
            ->pluck('short_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $shortIds
     */
    protected function recompute(array $shortIds): void
    {
        $rows = ShortEvent::query()
            ->selectRaw('short_id')
            ->selectRaw("SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN type = 'view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN type = 'complete' THEN 1 ELSE 0 END) as completions")
            ->selectRaw("SUM(CASE WHEN type = 'share' THEN 1 ELSE 0 END) as shares")
            ->selectRaw("SUM(CASE WHEN type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks")
            ->selectRaw('AVG(watch_ratio) as avg_watch_ratio')
            ->whereIn('short_id', $shortIds)
            ->groupBy('short_id')
            ->get();

        foreach ($rows as $row) {
            Short::query()->whereKey($row->short_id)->update([
                'impressions' => (int) $row->impressions,
                'views' => (int) $row->views,
                'completions' => (int) $row->completions,
                'shares' => (int) $row->shares,
                'cta_clicks' => (int) $row->cta_clicks,
                'avg_watch_ratio' => round((float) ($row->avg_watch_ratio ?? 0), 3),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AggregateShortStatsJob failed', ['error' => $e->getMessage()]);
    }
}
