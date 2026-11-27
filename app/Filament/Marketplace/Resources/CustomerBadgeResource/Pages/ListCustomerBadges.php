<?php

namespace App\Filament\Marketplace\Resources\CustomerBadgeResource\Pages;

use App\Filament\Marketplace\Resources\CustomerBadgeResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerBadges extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = CustomerBadgeResource::class;
}
