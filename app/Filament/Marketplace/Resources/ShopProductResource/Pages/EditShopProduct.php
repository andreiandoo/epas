<?php

namespace App\Filament\Marketplace\Resources\ShopProductResource\Pages;

use App\Filament\Marketplace\Resources\ShopProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopProduct extends EditRecord
{
    protected static string $resource = ShopProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
