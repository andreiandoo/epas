<?php

namespace App\Filament\Marketplace\Resources\AffiliateEventResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateEventResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateAffiliateEvent extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = AffiliateEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();

        $data['marketplace_client_id'] = $marketplace?->id;
        $data['is_affiliate'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $needsSave = false;

        // Fix slug to include the actual event ID
        if ($record->slug) {
            $baseSlug = preg_replace('/-\d+$/', '', $record->slug);
            $correctSlug = $baseSlug . '-' . $record->id;
            if ($record->slug !== $correctSlug) {
                $record->slug = $correctSlug;
                $needsSave = true;
            }
        }

        // Fix event_series
        $correctSeries = 'AMB-' . $record->id;
        if ($record->event_series !== $correctSeries) {
            $record->event_series = $correctSeries;
            $needsSave = true;
        }

        if ($needsSave) {
            $record->saveQuietly();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
