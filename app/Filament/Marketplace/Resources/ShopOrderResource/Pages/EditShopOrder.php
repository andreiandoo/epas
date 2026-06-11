<?php

namespace App\Filament\Marketplace\Resources\ShopOrderResource\Pages;

use App\Filament\Marketplace\Resources\ShopOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopOrder extends EditRecord
{
    protected static string $resource = ShopOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
