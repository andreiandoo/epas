<?php

namespace App\Filament\Marketplace\Resources\ShopAttributeResource\Pages;

use App\Filament\Marketplace\Resources\ShopAttributeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopAttributes extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ShopAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
