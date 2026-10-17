<?php

namespace App\Filament\Marketplace\Resources\HeroBannerResource\Pages;

use App\Filament\Marketplace\Resources\HeroBannerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHeroBanners extends ListRecords
{
    protected static string $resource = HeroBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
