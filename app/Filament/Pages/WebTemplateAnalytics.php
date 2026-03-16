<?php

namespace App\Filament\Pages;

use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class WebTemplateAnalytics extends Page
{
    protected static UnitEnum|string|null $navigationGroup = 'Web Templates';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Analiză';
    protected static ?string $title = 'Analiză Web Templates';
    protected string $view = 'filament.pages.web-template-analytics';

    public function getViewData(): array
    {
        $days = 30;
        $startDate = now()->subDays($days);

        // Views per day (last 30 days)
        $viewsPerDay = WebTemplateCustomization::where('last_viewed_at', '>=', $startDate)
            ->selectRaw('DATE(last_viewed_at) as date, SUM(viewed_count) as total_views')
            ->groupByRaw('DATE(last_viewed_at)')
            ->orderBy('date')
            ->pluck('total_views', 'date')
            ->toArray();

        // Fill in missing days
        $chartData = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartData[] = [
                'date' => Carbon::parse($date)->format('d M'),
                'views' => $viewsPerDay[$date] ?? 0,
            ];
        }

        // Top customizations by views
        $topCustomizations = WebTemplateCustomization::with('template')
            ->where('viewed_count', '>', 0)
            ->orderByDesc('viewed_count')
            ->limit(10)
            ->get();

        // Template performance
        $templateStats = WebTemplate::withCount('customizations')
            ->with(['customizations' => function ($q) {
                $q->select('web_template_id')
                    ->selectRaw('SUM(viewed_count) as total_views')
                    ->groupBy('web_template_id');
            }])
            ->where('is_active', true)
            ->get()
            ->map(function ($t) {
                return [
                    'name' => $t->name,
                    'category' => $t->category->label(),
                    'customizations' => $t->customizations_count,
                    'total_views' => $t->customizations->sum('total_views'),
                ];
            })
            ->sortByDesc('total_views')
            ->values();

        // Engagement funnel
        $totalCustomizations = WebTemplateCustomization::count();
        $withViews = WebTemplateCustomization::where('viewed_count', '>', 0)->count();
        $with10Views = WebTemplateCustomization::where('viewed_count', '>=', 10)->count();
        $with50Views = WebTemplateCustomization::where('viewed_count', '>=', 50)->count();

        // Recent activity
        $recentActivity = WebTemplateCustomization::with('template')
            ->whereNotNull('last_viewed_at')
            ->orderByDesc('last_viewed_at')
            ->limit(15)
            ->get();

        // UTM source breakdown
        $utmSources = [];
        WebTemplateCustomization::whereNotNull('utm_data')
            ->where('utm_data', '!=', '[]')
            ->each(function ($c) use (&$utmSources) {
                foreach (($c->utm_data ?? []) as $entry) {
                    $source = $entry['utm_source'] ?? 'direct';
                    $utmSources[$source] = ($utmSources[$source] ?? 0) + 1;
                }
            });
        arsort($utmSources);
        $utmSources = array_slice($utmSources, 0, 10, true);

        return [
            'chartData' => $chartData,
            'topCustomizations' => $topCustomizations,
            'templateStats' => $templateStats,
            'funnel' => [
                ['label' => 'Total Personalizări', 'count' => $totalCustomizations, 'color' => 'bg-blue-500'],
                ['label' => 'Cu vizualizări (1+)', 'count' => $withViews, 'color' => 'bg-green-500'],
                ['label' => 'Cu 10+ vizualizări', 'count' => $with10Views, 'color' => 'bg-amber-500'],
                ['label' => 'Cu 50+ vizualizări', 'count' => $with50Views, 'color' => 'bg-red-500'],
            ],
            'recentActivity' => $recentActivity,
            'utmSources' => $utmSources,
        ];
    }
}
