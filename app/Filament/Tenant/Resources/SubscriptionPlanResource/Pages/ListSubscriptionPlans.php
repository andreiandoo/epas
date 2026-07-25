<?php

namespace App\Filament\Tenant\Resources\SubscriptionPlanResource\Pages;

use App\Filament\Tenant\Resources\SubscriptionPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
