<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Short;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Per-short performance for the organiser (B5).
 *
 * Reads the daily rollup (short_analytics_daily) rather than raw telemetry: the
 * raw rows get pruned, and a page that scans them would get slower every week.
 *
 * The funnel is the point of this page — impression → view → CTA → sale is what
 * tells an organiser whether the problem is the cover, the clip or the offer.
 */
class ShortsAnalytics extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Shorts analytics';

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.tenant.pages.shorts-analytics';

    /** Days of history the page covers. */
    public int $days = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->tenant_id !== null;
    }

    /**
     * Funnel totals across the window, for this tenant only.
     *
     * @return array<string, int|float>
     */
    public function getFunnelProperty(): array
    {
        $totals = DB::table('short_analytics_daily')
            ->join('shorts', 'shorts.id', '=', 'short_analytics_daily.short_id')
            ->where('shorts.tenant_id', auth()->user()?->tenant_id)
            ->where('short_analytics_daily.date', '>=', now()->subDays($this->days)->toDateString())
            ->selectRaw('
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(views), 0) as views,
                COALESCE(SUM(cta_clicks), 0) as cta_clicks,
                COALESCE(SUM(conversions), 0) as conversions,
                COALESCE(SUM(revenue_cents), 0) as revenue_cents
            ')
            ->first();

        $impressions = (int) ($totals->impressions ?? 0);
        $views = (int) ($totals->views ?? 0);
        $clicks = (int) ($totals->cta_clicks ?? 0);
        $sales = (int) ($totals->conversions ?? 0);

        return [
            'impressions' => $impressions,
            'views' => $views,
            'cta_clicks' => $clicks,
            'conversions' => $sales,
            'revenue_cents' => (int) ($totals->revenue_cents ?? 0),
            // Ratios are computed here rather than stored: they would go stale the
            // moment either side moved.
            'view_rate' => $impressions > 0 ? $views / $impressions : 0.0,
            'ctr' => $views > 0 ? $clicks / $views : 0.0,
            'cvr' => $clicks > 0 ? $sales / $clicks : 0.0,
        ];
    }

    /**
     * Best-performing shorts in the window.
     *
     * @return Collection<int, object>
     */
    public function getTopShortsProperty()
    {
        return DB::table('short_analytics_daily')
            ->join('shorts', 'shorts.id', '=', 'short_analytics_daily.short_id')
            ->where('shorts.tenant_id', auth()->user()?->tenant_id)
            ->where('short_analytics_daily.date', '>=', now()->subDays($this->days)->toDateString())
            ->groupBy('shorts.id', 'shorts.title')
            ->selectRaw('
                shorts.id,
                shorts.title,
                SUM(views) as views,
                SUM(cta_clicks) as cta_clicks,
                SUM(conversions) as conversions,
                SUM(revenue_cents) as revenue_cents,
                AVG(avg_watch_ratio) as avg_watch_ratio
            ')
            ->orderByDesc('views')
            ->limit(15)
            ->get();
    }

    /**
     * Drop-off curve across every short of this tenant — where viewers leave.
     *
     * @return array<int, int>
     */
    public function getRetentionProperty(): array
    {
        $rows = DB::table('short_retention')
            ->join('shorts', 'shorts.id', '=', 'short_retention.short_id')
            ->where('shorts.tenant_id', auth()->user()?->tenant_id)
            ->where('short_retention.date', '>=', now()->subDays($this->days)->toDateString())
            ->groupBy('short_retention.bucket')
            ->selectRaw('short_retention.bucket, SUM(short_retention.count) as total')
            ->pluck('total', 'bucket');

        // Always ten buckets, so a gap reads as "nobody got this far" rather
        // than shifting the chart.
        return collect(range(0, 9))
            ->mapWithKeys(fn (int $bucket) => [$bucket => (int) ($rows[$bucket] ?? 0)])
            ->all();
    }

    public function getHasDataProperty(): bool
    {
        return Short::query()->where('tenant_id', auth()->user()?->tenant_id)->exists();
    }
}
