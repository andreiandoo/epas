<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;

class ListArtists extends ListRecords
{
    protected static string $resource = ArtistResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Artists';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add artist')
                ->icon('heroicon-m-plus')
                ->outlined()
                ->modalHeading('Add artist')
                ->slideOver(), // opțional; dacă preferi redirect la /create, șterge ->slideOver()

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => ArtistResource::getUrl('import')),
        ];
    }
}
