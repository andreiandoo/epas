<?php

namespace App\Filament\Tenant\Resources\MerchandiseAllocationResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseAllocation extends EditRecord
{
    protected static string $resource = MerchandiseAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
