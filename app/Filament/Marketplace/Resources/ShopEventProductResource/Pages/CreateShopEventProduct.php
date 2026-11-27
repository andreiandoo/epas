<?php

namespace App\Filament\Marketplace\Resources\ShopEventProductResource\Pages;

use App\Filament\Marketplace\Resources\ShopEventProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShopEventProduct extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ShopEventProductResource::class;
}
