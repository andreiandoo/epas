<?php

namespace App\Filament\Tenant\Resources\ShopAttributeResource\Pages;

use App\Filament\Tenant\Resources\ShopAttributeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopAttributes extends ListRecords
{
    protected static string $resource = ShopAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
