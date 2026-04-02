<?php

namespace App\Filament\Tenant\Resources\Cashless\PricingRuleResource\Pages;

use App\Filament\Tenant\Resources\Cashless\PricingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPricingRules extends ListRecords
{
    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
