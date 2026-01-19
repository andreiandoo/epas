<?php

namespace App\Filament\Marketplace\Resources\ShopGiftCardResource\Pages;

use App\Filament\Marketplace\Resources\ShopGiftCardResource;
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
