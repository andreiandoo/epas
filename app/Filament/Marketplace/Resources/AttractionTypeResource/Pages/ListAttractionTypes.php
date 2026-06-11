<?php

namespace App\Filament\Marketplace\Resources\AttractionTypeResource\Pages;

use App\Filament\Marketplace\Resources\AttractionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttractionTypes extends ListRecords
{
    protected static string $resource = AttractionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
