<?php

namespace App\Filament\Resources\MarketplaceAdminResource\Pages;

use App\Filament\Resources\MarketplaceAdminResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceAdmin extends EditRecord
{
    protected static string $resource = MarketplaceAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
