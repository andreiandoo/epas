<?php

namespace App\Filament\Marketplace\Resources\AttractionTypeResource\Pages;

use App\Filament\Marketplace\Resources\AttractionTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttractionType extends EditRecord
{
    protected static string $resource = AttractionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
