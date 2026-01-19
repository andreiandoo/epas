<?php

namespace App\Filament\Marketplace\Resources\OrganizerPromoCodeResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerPromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganizerPromoCodes extends ListRecords
{
    protected static string $resource = OrganizerPromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
