<?php

namespace App\Filament\Resources\AudienceTargeting\AudienceSegmentResource\Pages;

use App\Filament\Resources\AudienceTargeting\AudienceSegmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAudienceSegment extends CreateRecord
{
    protected static string $resource = AudienceSegmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Refresh segment membership if dynamic
        if ($this->record->segment_type === 'dynamic' && $this->record->criteria) {
            app(\App\Services\AudienceTargeting\SegmentationService::class)
                ->refreshSegment($this->record);
        }
    }
}
