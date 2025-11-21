<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use App\Filament\Resources\Venues\Widgets\VenueHeaderWidget;
use App\Filament\Resources\Venues\Widgets\VenueStatsOverview;
use App\Filament\Resources\Venues\Widgets\VenueEventsTable;

class ViewVenue extends ViewRecord
{
    protected static string $resource = VenueResource::class;

    protected function hasInfolist(): bool
    {
        return true;
    }

    public function getHeaderWidgetsColumns(): array|int
    {
        // un singur “stack” vertical: hero -> stats -> tabel
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stats')
                ->label('View Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(fn () => static::getResource()::getUrl('stats', ['record' => $this->record])),

            Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->record])),

            Action::make('openPublic')
                ->label('Open public page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(function () {
                    $locale = request()->route('locale') ?? app()->getLocale();
                    // explicit pe slug pentru public
                    return route('public.venues.show', [
                        'locale' => $locale,
                        'venue'  => $this->record->slug,
                    ]);
                })
                ->openUrlInNewTab(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // VenueHeaderWidget::class,
            // VenueStatsOverview::class,
            // VenueEventsTable::class,
        ];
    }
}
