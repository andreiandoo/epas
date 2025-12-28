<?php

namespace App\Filament\Resources\DynamicPricingRuleResource\Pages;

use App\Filament\Resources\DynamicPricingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDynamicPricingRules extends ListRecords
{
    protected static string $resource = DynamicPricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
