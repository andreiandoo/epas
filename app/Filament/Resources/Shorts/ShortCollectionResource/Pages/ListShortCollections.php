<?php

namespace App\Filament\Resources\Shorts\ShortCollectionResource\Pages;

use App\Filament\Resources\Shorts\ShortCollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShortCollections extends ListRecords
{
    protected static string $resource = ShortCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
