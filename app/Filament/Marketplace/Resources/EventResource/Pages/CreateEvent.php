<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Services\EventSchedulingService;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateEvent extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();

        $data['marketplace_client_id'] = $marketplace?->id;

        // Auto-fill ticket_terms from tenant settings if empty
        // The form uses translatable format: ticket_terms.{language}
        // If ticket_terms array is empty but tenant has default terms, populate it
        if ($marketplace?->ticket_terms) {
            $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';
            if (empty($data['ticket_terms'][$marketplaceLanguage])) {
                $data['ticket_terms'][$marketplaceLanguage] = $marketplace->ticket_terms;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Fix slug to include the actual event ID (since we couldn't know it before save)
        $record = $this->record;
        $slug = $record->slug;

        // If slug doesn't already end with the ID, append it
        if ($slug && !str_ends_with($slug, '-' . $record->id)) {
            $record->slug = $slug . '-' . $record->id;
            $record->saveQuietly();
        }

        // Auto-generate event_series if not set
        if (!$record->event_series) {
            $record->event_series = 'AMB-' . $record->id;
            $record->saveQuietly();
        }

        // Process multi-day and recurring event scheduling
        // Creates child events for each occurrence
        app(EventSchedulingService::class)->processEventScheduling($this->record);
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to edit page instead of view after creation
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
