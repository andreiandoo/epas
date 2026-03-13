<?php

namespace App\Filament\Marketplace\Resources\MarketplaceAdminResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceAdminResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceAdmins extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = MarketplaceAdminResource::class;
}
