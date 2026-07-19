<?php

namespace App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource\Pages;

use App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventFlexiblePaymentConfigs extends ListRecords
{
    protected static string $resource = EventFlexiblePaymentConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
