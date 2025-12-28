<?php

namespace App\Filament\Marketplace\Resources\ShopProductResource\Pages;

use App\Filament\Marketplace\Resources\ShopProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateShopProduct extends CreateRecord
{
    protected static string $resource = ShopProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;

        // Generate slug if empty
        if (empty($data['slug']) && !empty($data['title'])) {
            $lang = auth()->user()->tenant?->language ?? 'en';
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
