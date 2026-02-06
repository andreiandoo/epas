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

    public function mount(): void
    {
        parent::mount();

        // Pre-fill organizer from query parameter if provided
        $organizerId = request()->query('organizer');
        if ($organizerId) {
            $this->form->fill([
                'marketplace_organizer_id' => (int) $organizerId,
            ]);
        }
    }

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
        $needsSave = false;

        // If slug doesn't already end with the ID, append it
        if ($slug && !str_ends_with($slug, '-' . $record->id)) {
            $record->slug = $slug . '-' . $record->id;
            $needsSave = true;
        }

        // Auto-generate event_series if not set or if it's the placeholder "AMB-"
        if (!$record->event_series || $record->event_series === 'AMB-') {
            $record->event_series = 'AMB-' . $record->id;
            $needsSave = true;
        }

        if ($needsSave) {
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
