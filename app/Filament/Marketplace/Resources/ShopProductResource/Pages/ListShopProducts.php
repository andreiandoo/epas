<?php

namespace App\Filament\Marketplace\Resources\ShopProductResource\Pages;

use App\Filament\Marketplace\Resources\ShopProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopProducts extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ShopProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
