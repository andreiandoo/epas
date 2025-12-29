<?php

namespace App\Filament\Marketplace\Resources\ShopAttributeResource\Pages;

use App\Filament\Marketplace\Resources\ShopAttributeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateShopAttribute extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ShopAttributeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;

        if (empty($data['slug']) && !empty($data['name'])) {
            $lang = static::getMarketplaceClient()?->language ?? 'en';
            $name = $data['name'][$lang] ?? reset($data['name']);
            $data['slug'] = Str::slug($name);
        }

        return $data;
    }
}
