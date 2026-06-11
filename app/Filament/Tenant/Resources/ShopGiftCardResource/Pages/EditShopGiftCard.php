<?php

namespace App\Filament\Tenant\Resources\ShopGiftCardResource\Pages;

use App\Filament\Tenant\Resources\ShopGiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopGiftCard extends EditRecord
{
    protected static string $resource = ShopGiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
