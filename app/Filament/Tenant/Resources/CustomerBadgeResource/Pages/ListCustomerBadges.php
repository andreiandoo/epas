<?php

namespace App\Filament\Tenant\Resources\CustomerBadgeResource\Pages;

use App\Filament\Tenant\Resources\CustomerBadgeResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerBadges extends ListRecords
{
    protected static string $resource = CustomerBadgeResource::class;
}
