<?php

namespace App\Filament\Marketplace\Resources\AttractionResource\Pages;

use App\Filament\Marketplace\Resources\AttractionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttractions extends ListRecords
{
    protected static string $resource = AttractionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
