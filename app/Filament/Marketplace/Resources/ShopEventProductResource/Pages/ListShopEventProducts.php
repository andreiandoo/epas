<?php

namespace App\Filament\Marketplace\Resources\ShopEventProductResource\Pages;

use App\Filament\Marketplace\Resources\ShopEventProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopEventProducts extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ShopEventProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
