<?php

namespace App\Filament\Marketplace\Resources\ShopProductResource\Pages;

use App\Filament\Marketplace\Resources\ShopProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateShopProduct extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ShopProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;

        // Generate slug if empty
        if (empty($data['slug']) && !empty($data['title'])) {
            $lang = static::getMarketplaceClient()?->language ?? 'en';
            $title = $data['title'][$lang] ?? reset($data['title']);
            $data['slug'] = Str::slug($title);
        }

        // Generate SKU if empty
        if (empty($data['sku'])) {
            $data['sku'] = strtoupper(Str::random(8));
        }

        return $data;
    }
}
