<?php

namespace App\Filament\Tenant\Resources\CustomerPointsResource\Pages;

use App\Filament\Tenant\Resources\CustomerPointsResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPoints extends ListRecords
{
    protected static string $resource = CustomerPointsResource::class;
}
