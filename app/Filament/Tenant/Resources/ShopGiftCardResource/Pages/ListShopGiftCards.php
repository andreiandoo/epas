<?php

namespace App\Filament\Tenant\Resources\ShopGiftCardResource\Pages;

use App\Filament\Tenant\Resources\ShopGiftCardResource;
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
