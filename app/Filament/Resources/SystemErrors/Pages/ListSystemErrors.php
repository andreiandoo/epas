<?php

namespace App\Filament\Resources\SystemErrors\Pages;

use App\Filament\Resources\SystemErrors\SystemErrorResource;
use App\Filament\Widgets\SystemErrorStatsWidget;
use App\Filament\Widgets\SystemErrorTrendsChartWidget;
use App\Models\SystemError;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSystemErrors extends ListRecords
{
    protected static string $resource = SystemErrorResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SystemErrorStatsWidget::class,
            SystemErrorTrendsChartWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $window = now()->subDay();
        $countBy = function (?string $category, ?int $minLevel = null) use ($window) {
            return fn () => SystemError::query()
                ->whereNull('acknowledged_at')
                ->where('created_at', '>=', $window)
                ->when($category, fn (Builder $q) => $q->where('category', $category))
                ->when($minLevel, fn (Builder $q) => $q->where('level', '>=', $minLevel))
                ->count() ?: null;
        };

        return [
            'all' => Tab::make('All')
                ->modifyQueryUsing(fn (Builder $q) => $q),
            'critical' => Tab::make('Critical')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('level', '>=', 500))
                ->badge($countBy(null, 500))
                ->badgeColor('danger'),
            'auth' => Tab::make('Auth')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'auth'))
                ->badge($countBy('auth')),
            'payment' => Tab::make('Payment')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'payment'))
                ->badge($countBy('payment'))
                ->badgeColor('warning'),
            'email' => Tab::make('Email')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'email'))
                ->badge($countBy('email')),
            'database' => Tab::make('Database')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'database'))
                ->badge($countBy('database'))
                ->badgeColor('warning'),
            'external_api' => Tab::make('External API')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'external_api'))
                ->badge($countBy('external_api')),
            'queue' => Tab::make('Queue')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'queue'))
                ->badge($countBy('queue')),
            'seating' => Tab::make('Seating')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'seating'))
                ->badge($countBy('seating')),
            'security' => Tab::make('Security')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('category', 'security'))
                ->badge($countBy('security')),
            'other' => Tab::make('Other')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('category', [
                    'pdf', 'storage', 'integration', 'cron', 'marketplace', 'app', 'unknown', 'validation',
                ])),
        ];
    }
}
