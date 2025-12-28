<?php

namespace App\Filament\Tenant\Resources\ShopAttributeResource\Pages;

use App\Filament\Tenant\Resources\ShopAttributeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateShopAttribute extends CreateRecord
{
    protected static string $resource = ShopAttributeResource::class;

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
