<?php

namespace App\Filament\Resources\AdsServiceRequestResource\Pages;

use App\Filament\Resources\AdsServiceRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdsServiceRequests extends ListRecords
{
    protected static string $resource = AdsServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
