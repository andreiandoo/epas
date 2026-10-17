<?php

namespace App\Filament\Marketplace\Resources\HeroBannerResource\Pages;

use App\Filament\Marketplace\Resources\HeroBannerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHeroBanner extends EditRecord
{
    protected static string $resource = HeroBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
