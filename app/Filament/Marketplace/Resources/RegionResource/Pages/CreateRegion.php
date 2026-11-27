<?php

namespace App\Filament\Marketplace\Resources\RegionResource\Pages;

use App\Filament\Marketplace\Resources\RegionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegion extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = RegionResource::class;
}
