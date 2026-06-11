<?php

namespace App\Filament\Tenant\Resources\Cashless\FinanceFeeRuleResource\Pages;

use App\Filament\Tenant\Resources\Cashless\FinanceFeeRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinanceFeeRules extends ListRecords
{
    protected static string $resource = FinanceFeeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
