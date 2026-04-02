<?php

namespace App\Filament\Tenant\Resources\Cashless\CustomerProfileResource\Pages;

use App\Filament\Tenant\Resources\Cashless\CustomerProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerProfiles extends ListRecords
{
    protected static string $resource = CustomerProfileResource::class;
}
