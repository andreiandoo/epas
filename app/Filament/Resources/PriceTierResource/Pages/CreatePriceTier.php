<?php

namespace App\Filament\Resources\PriceTierResource\Pages;

use App\Filament\Resources\PriceTierResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceTier extends CreateRecord
{
    protected static string $resource = PriceTierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set from current context
        $data['tenant_id'] = auth()->user()?->tenant_id
            ?? session('tenant_id')
            ?? request()->input('tenant_id')
            ?? 1; // Default to tenant_id = 1 for development

        return $data;
    }
}
