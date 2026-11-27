<?php

namespace App\Filament\Marketplace\Resources\TravelerTypeResource\Pages;

use App\Filament\Marketplace\Resources\TravelerTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTravelerType extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = TravelerTypeResource::class;
}
