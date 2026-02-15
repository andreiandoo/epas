<?php

namespace App\Filament\Resources\AdsAudienceSegmentResource\Pages;

use App\Filament\Resources\AdsAudienceSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdsAudienceSegment extends EditRecord
{
    protected static string $resource = AdsAudienceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
