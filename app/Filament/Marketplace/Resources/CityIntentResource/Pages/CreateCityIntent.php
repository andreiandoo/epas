<?php

namespace App\Filament\Marketplace\Resources\CityIntentResource\Pages;

use App\Filament\Marketplace\Resources\CityIntentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCityIntent extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = CityIntentResource::class;
}
