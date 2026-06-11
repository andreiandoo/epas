<?php

namespace App\Filament\Resources\Taxonomies\VenueTypeResource\Pages;

use App\Filament\Resources\Taxonomies\VenueTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVenueTypes extends ListRecords
{
    protected static string $resource = VenueTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add venue type')
                ->icon('heroicon-m-plus'),
        ];
    }
}
