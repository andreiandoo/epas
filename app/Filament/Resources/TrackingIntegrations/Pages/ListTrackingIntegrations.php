<?php

namespace App\Filament\Resources\TrackingIntegrations\Pages;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrackingIntegrations extends ListRecords
{
    protected static string $resource = TrackingIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
