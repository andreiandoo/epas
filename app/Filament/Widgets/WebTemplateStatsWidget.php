<?php

namespace App\Filament\Widgets;

use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WebTemplateStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 15;
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $activeTemplates = WebTemplate::where('is_active', true)->count();
        $totalCustomizations = WebTemplateCustomization::count();
        $activeCustomizations = WebTemplateCustomization::where('status', 'active')->count();

        $monthViews = WebTemplateCustomization::where('last_viewed_at', '>=', now()->startOfMonth())
            ->sum('viewed_count');

        $totalViews = WebTemplateCustomization::sum('viewed_count');

        $topTemplate = WebTemplate::withCount('customizations')
            ->orderByDesc('customizations_count')
            ->first();

        $recentlyViewed = WebTemplateCustomization::where('last_viewed_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Template-uri Active', $activeTemplates)
                ->description('din ' . WebTemplate::count() . ' total')
                ->icon('heroicon-o-paint-brush')
                ->color('primary'),

            Stat::make('Personalizări Active', $activeCustomizations)
                ->description($totalCustomizations . ' total create')
                ->icon('heroicon-o-sparkles')
                ->color('success'),

            Stat::make('Vizualizări Luna Aceasta', number_format($monthViews))
                ->description(number_format($totalViews) . ' total')
                ->icon('heroicon-o-eye')
                ->color('info'),

            Stat::make('Vizitate Recent (7 zile)', $recentlyViewed)
                ->description($topTemplate ? 'Top: ' . $topTemplate->name : '')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        return WebTemplate::exists();
    }
}
