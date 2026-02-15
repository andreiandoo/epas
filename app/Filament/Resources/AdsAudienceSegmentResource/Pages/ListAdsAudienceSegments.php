<?php

namespace App\Filament\Resources\AdsAudienceSegmentResource\Pages;

use App\Filament\Resources\AdsAudienceSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdsAudienceSegments extends ListRecords
{
    protected static string $resource = AdsAudienceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
