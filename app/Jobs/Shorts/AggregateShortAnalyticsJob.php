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
 * Daily per-short rollup for the organiser analytics page (B5).
 *
 * Separate from AggregateShortStatsJob: that one keeps the live counters on
 * `shorts` current, this one builds the time series the funnel and the charts
 * read. Both recompute rather than increment, so a re-run is safe — and this one
 * has to survive the telemetry prune, which is why it runs daily and stores the
 * result rather than querying raw events at page load.
 */
class AggregateShortAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(protected ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?? now()->subDay()->toDateString();

        $rows = ShortEvent::query()
            ->selectRaw('short_id')
            ->selectRaw("SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN type = 'view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN type = 'complete' THEN 1 ELSE 0 END) as completions")
            ->selectRaw("SUM(CASE WHEN type = 'like' THEN 1 ELSE 0 END) as likes")
            ->selectRaw("SUM(CASE WHEN type = 'save' THEN 1 ELSE 0 END) as saves")
            ->selectRaw("SUM(CASE WHEN type = 'share' THEN 1 ELSE 0 END) as shares")
            ->selectRaw("SUM(CASE WHEN type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks")
            ->selectRaw('COUNT(DISTINCT marketplace_customer_id) as unique_viewers')
            ->selectRaw('AVG(watch_ratio) as avg_watch_ratio')
            ->whereDate('created_at', $date)
            ->groupBy('short_id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $conversions = $this->conversionsByShort($date);

        foreach ($rows as $row) {
            $sales = $conversions[$row->short_id] ?? ['count' => 0, 'cents' => 0];

            DB::table('short_analytics_daily')->updateOrInsert(
                ['short_id' => $row->short_id, 'date' => $date],
                [
                    'impressions' => (int) $row->impressions,
                    'views' => (int) $row->views,
                    'completions' => (int) $row->completions,
                    'unique_viewers' => (int) $row->unique_viewers,
                    'avg_watch_ratio' => round((float) ($row->avg_watch_ratio ?? 0), 3),
                    'likes' => (int) $row->likes,
                    'saves' => (int) $row->saves,
                    'shares' => (int) $row->shares,
                    'cta_clicks' => (int) $row->cta_clicks,
                    'conversions' => $sales['count'],
                    'revenue_cents' => $sales['cents'],
                ],
            );
        }

        Log::info('AggregateShortAnalyticsJob: built daily analytics', [
            'date' => $date,
            'shorts' => $rows->count(),
        ]);
    }

    /**
     * Orders attributed to each short on that day.
     *
     * @return array<int, array{count: int, cents: int}>
     */
    protected function conversionsByShort(string $date): array
    {
        try {
            return DB::table('orders')
                ->selectRaw('source_short_id, COUNT(*) as sales, SUM(total) as revenue')
                ->whereNotNull('source_short_id')
                ->whereDate('short_attributed_at', $date)
                ->groupBy('source_short_id')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (int) $row->source_short_id => [
                        'count' => (int) $row->sales,
                        'cents' => (int) round(((float) $row->revenue) * 100),
                    ],
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::debug('AggregateShortAnalyticsJob: conversion lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AggregateShortAnalyticsJob failed', ['error' => $e->getMessage()]);
    }
}
