<?php

namespace App\Filament\Marketplace\Resources\CityIntentResource\Pages;

use App\Filament\Marketplace\Resources\CityIntentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCityIntents extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = CityIntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
