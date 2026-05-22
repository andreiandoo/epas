<?php

namespace App\Filament\Marketplace\Resources\CityIntentResource\Pages;

use App\Filament\Marketplace\Resources\CityIntentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCityIntent extends EditRecord
{
    protected static string $resource = CityIntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
