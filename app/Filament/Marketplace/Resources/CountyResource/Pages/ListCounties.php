<?php

namespace App\Filament\Marketplace\Resources\CountyResource\Pages;

use App\Filament\Marketplace\Resources\CountyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCounties extends ListRecords
{
    protected static string $resource = CountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
