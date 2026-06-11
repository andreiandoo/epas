<?php

namespace App\Filament\Tenant\Resources\ShopProductResource\Pages;

use App\Filament\Tenant\Resources\ShopProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopProducts extends ListRecords
{
    protected static string $resource = ShopProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
