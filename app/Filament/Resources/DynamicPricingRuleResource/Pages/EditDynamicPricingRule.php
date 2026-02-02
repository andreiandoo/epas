<?php

namespace App\Filament\Resources\DynamicPricingRuleResource\Pages;

use App\Filament\Resources\DynamicPricingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDynamicPricingRule extends EditRecord
{
    protected static string $resource = DynamicPricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
