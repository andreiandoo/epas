<?php

namespace App\Filament\Marketplace\Resources\ShopGiftCardResource\Pages;

use App\Filament\Marketplace\Resources\ShopGiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopGiftCards extends ListRecords
{
    protected static string $resource = ShopGiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
