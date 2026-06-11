<?php

namespace App\Filament\Tenant\Resources\LeisureCapacityResource\Pages;

use App\Filament\Tenant\Resources\LeisureCapacityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeisureCapacity extends EditRecord
{
    protected static string $resource = LeisureCapacityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
