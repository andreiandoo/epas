<?php

namespace App\Filament\Tenant\Resources\PhysicalResourceTypeResource\Pages;

use App\Filament\Tenant\Resources\PhysicalResourceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhysicalResourceType extends EditRecord
{
    protected static string $resource = PhysicalResourceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
