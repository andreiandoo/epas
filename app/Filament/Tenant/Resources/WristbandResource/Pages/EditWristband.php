<?php

namespace App\Filament\Tenant\Resources\WristbandResource\Pages;

use App\Filament\Tenant\Resources\WristbandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWristband extends EditRecord
{
    protected static string $resource = WristbandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
