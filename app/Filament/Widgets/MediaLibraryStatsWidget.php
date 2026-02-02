<?php

namespace App\Filament\Widgets;

use App\Models\MediaLibrary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MediaLibraryStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $totalFiles = MediaLibrary::count();
        $totalSize = MediaLibrary::sum('size');
        $imagesCount = MediaLibrary::images()->count();
        $thisMonth = MediaLibrary::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            Stat::make('Total Files', number_format($totalFiles))
                ->icon('heroicon-o-document-duplicate')
                ->color('primary'),

            Stat::make('Total Size', $this->formatBytes($totalSize))
                ->icon('heroicon-o-server-stack')
                ->color('info'),

            Stat::make('Images', number_format($imagesCount))
                ->icon('heroicon-o-photo')
                ->color('success'),

            Stat::make('This Month', number_format($thisMonth))
                ->icon('heroicon-o-calendar')
                ->color('warning'),
        ];
    }

    protected function formatBytes($bytes): string
    {
        if ($bytes === 0 || $bytes === null) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}
