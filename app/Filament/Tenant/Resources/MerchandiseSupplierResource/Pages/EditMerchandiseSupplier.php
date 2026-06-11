<?php

namespace App\Filament\Tenant\Resources\MerchandiseSupplierResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseSupplier extends EditRecord
{
    protected static string $resource = MerchandiseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
