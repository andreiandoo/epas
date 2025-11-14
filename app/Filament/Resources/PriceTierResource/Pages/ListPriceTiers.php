<?php

namespace App\Filament\Resources\PriceTierResource\Pages;

use App\Filament\Resources\PriceTierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPriceTiers extends ListRecords
{
    protected static string $resource = PriceTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
