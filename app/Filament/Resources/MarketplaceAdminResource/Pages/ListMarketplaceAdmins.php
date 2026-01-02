<?php

namespace App\Filament\Resources\MarketplaceAdminResource\Pages;

use App\Filament\Resources\MarketplaceAdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceAdmins extends ListRecords
{
    protected static string $resource = MarketplaceAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
