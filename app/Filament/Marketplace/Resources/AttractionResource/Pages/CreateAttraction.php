<?php

namespace App\Filament\Marketplace\Resources\AttractionResource\Pages;

use App\Filament\Marketplace\Resources\AttractionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttraction extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = AttractionResource::class;
}
