<?php

namespace App\Filament\Resources\DynamicPricingRuleResource\Pages;

use App\Filament\Resources\DynamicPricingRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDynamicPricingRule extends CreateRecord
{
    protected static string $resource = DynamicPricingRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set from current context
        $data['tenant_id'] = auth()->user()?->tenant_id
            ?? session('tenant_id')
            ?? request()->input('tenant_id');

        return $data;
    }
}
