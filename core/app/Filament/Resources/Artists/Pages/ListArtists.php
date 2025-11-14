<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

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
        ];
    }
}
