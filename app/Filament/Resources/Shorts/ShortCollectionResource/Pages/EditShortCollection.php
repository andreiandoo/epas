<?php

namespace App\Filament\Resources\Shorts\ShortCollectionResource\Pages;

use App\Filament\Resources\Shorts\ShortCollectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShortCollection extends EditRecord
{
    protected static string $resource = ShortCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
