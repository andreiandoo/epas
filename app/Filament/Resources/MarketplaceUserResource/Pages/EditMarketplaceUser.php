<?php

namespace App\Filament\Resources\MarketplaceUserResource\Pages;

use App\Filament\Resources\MarketplaceUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceUser extends EditRecord
{
    protected static string $resource = MarketplaceUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
