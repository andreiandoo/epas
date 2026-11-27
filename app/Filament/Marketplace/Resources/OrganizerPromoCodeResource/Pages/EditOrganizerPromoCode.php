<?php

namespace App\Filament\Marketplace\Resources\OrganizerPromoCodeResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerPromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizerPromoCode extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = OrganizerPromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
