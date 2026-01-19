<?php

namespace App\Filament\Marketplace\Resources\OrganizerPromoCodeResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerPromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizerPromoCode extends EditRecord
{
    protected static string $resource = OrganizerPromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
