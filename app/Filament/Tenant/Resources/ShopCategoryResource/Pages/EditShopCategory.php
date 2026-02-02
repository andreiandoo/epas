<?php

namespace App\Filament\Tenant\Resources\ShopCategoryResource\Pages;

use App\Filament\Tenant\Resources\ShopCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopCategory extends EditRecord
{
    protected static string $resource = ShopCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
