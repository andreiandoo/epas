<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Services\EventSchedulingService;
use App\Services\PerformanceSyncService;
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
        // Fix slug and event_series to include the actual event ID
        // The form may have predicted a different ID if concurrent creates happened
        $record = $this->record;
        $needsSave = false;

        // Fix slug: remove any predicted ID suffix and add the correct one
        if ($record->slug) {
            // Remove trailing -NUMBER if present (predicted ID)
            $baseSlug = preg_replace('/-\d+$/', '', $record->slug);
            $correctSlug = $baseSlug . '-' . $record->id;
            if ($record->slug !== $correctSlug) {
                $record->slug = $correctSlug;
                $needsSave = true;
            }
        }

        // Fix event_series: ensure it has the correct ID
        $correctSeries = 'AMB-' . $record->id;
        if ($record->event_series !== $correctSeries) {
            $record->event_series = $correctSeries;
            $needsSave = true;
        }

        if ($needsSave) {
            $record->saveQuietly();
        }

        // Auto-fill short description from first 80 words of description if empty
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
        $shortDesc = $this->record->getTranslation('short_description', $lang);
        if (empty(trim(strip_tags($shortDesc ?? '')))) {
            $desc = $this->record->getTranslation('description', $lang);
            if ($desc) {
                $text = strip_tags($desc);
                $words = preg_split('/\s+/', trim($text), 81, PREG_SPLIT_NO_EMPTY);
                if (count($words) > 80) {
                    $words = array_slice($words, 0, 80);
                    $text = implode(' ', $words) . '...';
                } else {
                    $text = implode(' ', $words);
                }
                $this->record->setTranslation('short_description', $lang, $text);
                $this->record->saveQuietly();
            }
        }

        // Process multi-day and recurring event scheduling
        // Creates child events for each occurrence
        app(EventSchedulingService::class)->processEventScheduling($this->record);

        // Sync Performance records from multi_slots (for per-slot pricing)
        if ($this->record->duration_mode === 'multi_day') {
            app(PerformanceSyncService::class)->syncFromMultiSlots($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to edit page instead of view after creation
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
