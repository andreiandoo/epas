<?php

namespace App\Filament\Tenant\Resources\MerchandiseItemResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseItem extends EditRecord
{
    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
