<?php

namespace App\Filament\Marketplace\Resources\HeroBannerResource\Pages;

use App\Filament\Marketplace\Resources\HeroBannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHeroBanner extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = HeroBannerResource::class;
}
