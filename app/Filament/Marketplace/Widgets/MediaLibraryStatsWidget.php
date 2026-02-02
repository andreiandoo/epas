<?php

namespace App\Filament\Marketplace\Widgets;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MediaLibrary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MediaLibraryStatsWidget extends StatsOverviewWidget
{
    use HasMarketplaceContext;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $marketplace = static::getMarketplaceClient();
        $query = MediaLibrary::query()->where('marketplace_client_id', $marketplace?->id);

        $totalFiles = (clone $query)->count();
        $totalSize = (clone $query)->sum('size');
        $imagesCount = (clone $query)->images()->count();
        $thisMonth = (clone $query)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            Stat::make('Total Fișiere', number_format($totalFiles))
                ->icon('heroicon-o-document-duplicate')
                ->color('primary'),

            Stat::make('Mărime Totală', $this->formatBytes($totalSize))
                ->icon('heroicon-o-server-stack')
                ->color('info'),

            Stat::make('Imagini', number_format($imagesCount))
                ->icon('heroicon-o-photo')
                ->color('success'),

            Stat::make('Luna Aceasta', number_format($thisMonth))
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
