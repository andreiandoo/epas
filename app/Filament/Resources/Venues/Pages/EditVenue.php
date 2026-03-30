<?php
namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analytics')
                ->label('Analytics')
                ->icon('heroicon-o-chart-bar-square')
                ->color('primary')
                ->url(fn () => static::getResource()::getUrl('analytics', ['record' => $this->record])),

            Actions\Action::make('stats')
                ->label('Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(fn () => static::getResource()::getUrl('stats', ['record' => $this->record])),

            Actions\Action::make('view')
                ->label('View')
                ->icon('heroicon-o-eye')
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->record])),

            Actions\DeleteAction::make(),
        ];
    }
}
