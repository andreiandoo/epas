<?php

namespace App\Filament\Marketplace\Resources\CountyResource\Pages;

use App\Filament\Marketplace\Resources\CountyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCounty extends EditRecord
{
    protected static string $resource = CountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
