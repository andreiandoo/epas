<?php

namespace App\Filament\Marketplace\Resources\InstallmentPlanResource\Pages;

use App\Filament\Marketplace\Resources\InstallmentPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentPlans extends ListRecords
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
