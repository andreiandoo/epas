<?php

namespace App\Filament\Marketplace\Resources\SystemUpdateResource\Pages;

use App\Filament\Marketplace\Resources\SystemUpdateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSystemUpdate extends EditRecord
{
    protected static string $resource = SystemUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
