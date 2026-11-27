<?php

namespace App\Filament\Marketplace\Resources\MarketplaceAdminResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceAdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceAdmin extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = MarketplaceAdminResource::class;
}
