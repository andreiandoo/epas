<?php

namespace App\Filament\Marketplace\Resources\ShopOrderResource\Pages;

use App\Filament\Marketplace\Resources\ShopOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopOrders extends ListRecords
{
    protected static string $resource = ShopOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Orders are created through checkout, not manually
        ];
    }
}
