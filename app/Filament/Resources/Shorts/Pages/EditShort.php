<?php

namespace App\Filament\Resources\Shorts\Pages;

use App\Filament\Resources\Shorts\ShortResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShort extends EditRecord
{
    protected static string $resource = ShortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
