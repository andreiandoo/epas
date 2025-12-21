<?php

namespace App\Filament\Organizer\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = -2;

    public function getColumns(): int | string | array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Organizer\Widgets\OrganizerStatsOverview::class,
            \App\Filament\Organizer\Widgets\RevenueChart::class,
            \App\Filament\Organizer\Widgets\RecentOrdersTable::class,
            \App\Filament\Organizer\Widgets\UpcomingEventsTable::class,
        ];
    }

    public function getTitle(): string
    {
        $organizer = auth('organizer')->user()?->organizer;
        return 'Welcome, ' . ($organizer?->name ?? 'Organizer');
    }
}
