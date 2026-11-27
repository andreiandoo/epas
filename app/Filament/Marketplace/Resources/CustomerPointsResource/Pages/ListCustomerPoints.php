<?php

namespace App\Filament\Marketplace\Resources\CustomerPointsResource\Pages;

use App\Filament\Marketplace\Resources\CustomerPointsResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPoints extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = CustomerPointsResource::class;
}
