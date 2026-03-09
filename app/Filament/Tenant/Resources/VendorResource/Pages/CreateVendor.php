<?php

namespace App\Filament\Tenant\Resources\VendorResource\Pages;

use App\Filament\Tenant\Resources\VendorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateVendor extends CreateRecord
{
    protected static string $resource = VendorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
