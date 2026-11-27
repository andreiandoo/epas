<?php

namespace App\Filament\Marketplace\Resources\CustomerExperienceResource\Pages;

use App\Filament\Marketplace\Resources\CustomerExperienceResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerExperiences extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = CustomerExperienceResource::class;
}
