<?php

namespace App\Filament\Marketplace\Resources\AttractionTypeResource\Pages;

use App\Filament\Marketplace\Resources\AttractionTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttractionType extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = AttractionTypeResource::class;
}
