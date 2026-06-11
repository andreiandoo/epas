<?php

namespace App\Filament\Marketplace\Resources\OrganizerPromoCodeResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerPromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganizerPromoCode extends ViewRecord
{
    protected static string $resource = OrganizerPromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
