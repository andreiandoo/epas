<?php

namespace App\Filament\Marketplace\Resources\GiftCardResource\Pages;

use App\Filament\Marketplace\Resources\GiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGiftCard extends ViewRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
