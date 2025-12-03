<?php

namespace App\Filament\Resources\AudienceTargeting\AudienceSegmentResource\Pages;

use App\Filament\Resources\AudienceTargeting\AudienceSegmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAudienceSegments extends ListRecords
{
    protected static string $resource = AudienceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
