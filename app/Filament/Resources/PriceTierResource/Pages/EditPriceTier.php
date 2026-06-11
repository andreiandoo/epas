<?php

namespace App\Filament\Resources\PriceTierResource\Pages;

use App\Filament\Resources\PriceTierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPriceTier extends EditRecord
{
    protected static string $resource = PriceTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
