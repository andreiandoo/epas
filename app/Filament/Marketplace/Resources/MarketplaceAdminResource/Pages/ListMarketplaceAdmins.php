<?php

namespace App\Filament\Marketplace\Resources\MarketplaceAdminResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceAdmins extends ListRecords
{
    protected static string $resource = MarketplaceAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
