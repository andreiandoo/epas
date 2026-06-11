<?php

namespace App\Filament\Tenant\Resources\ShopEventProductResource\Pages;

use App\Filament\Tenant\Resources\ShopEventProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopEventProducts extends ListRecords
{
    protected static string $resource = ShopEventProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
