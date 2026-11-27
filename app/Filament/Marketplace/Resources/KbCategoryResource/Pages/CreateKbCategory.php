<?php

namespace App\Filament\Marketplace\Resources\KbCategoryResource\Pages;

use App\Filament\Marketplace\Resources\KbCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKbCategory extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = KbCategoryResource::class;
}
