<?php

namespace App\Filament\Resources\Gamification\CustomerBadgeResource\Pages;

use App\Filament\Resources\Gamification\CustomerBadgeResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerBadges extends ListRecords
{
    protected static string $resource = CustomerBadgeResource::class;
}
