<?php

namespace App\Filament\Tenant\Resources\PhysicalResourceResource\Pages;

use App\Filament\Tenant\Resources\PhysicalResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhysicalResource extends EditRecord
{
    protected static string $resource = PhysicalResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
