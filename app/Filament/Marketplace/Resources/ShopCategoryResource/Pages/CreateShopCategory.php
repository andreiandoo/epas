<?php

namespace App\Filament\Marketplace\Resources\ShopCategoryResource\Pages;

use App\Filament\Marketplace\Resources\ShopCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateShopCategory extends CreateRecord
{
    protected static string $resource = ShopCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;

        if (empty($data['slug']) && !empty($data['name'])) {
            $lang = auth()->user()->tenant?->language ?? 'en';
            $name = $data['name'][$lang] ?? reset($data['name']);
            $data['slug'] = Str::slug($name);
        }

        return $data;
    }
}
