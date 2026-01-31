<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\MarketplaceNotificationService;
use Illuminate\Support\Facades\Log;

class MarketplaceEventObserver
{
    public function __construct(
        protected MarketplaceNotificationService $notificationService
    ) {}

    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        // Only for marketplace events
        if (!$event->marketplace_client_id || !$event->marketplace_organizer_id) {
            return;
        }

        try {
            $organizer = $event->marketplaceOrganizer;
            $organizerName = $organizer?->company_name ?? $organizer?->name ?? 'Organizator necunoscut';

            $eventTitle = $event->getTranslation('title', 'ro')
                ?: $event->getTranslation('title', 'en')
                ?: $event->getTranslation('title')
                ?: 'Eveniment nou';

            $this->notificationService->notifyEventCreated(
                $event->marketplace_client_id,
                $eventTitle,
                $organizerName,
                $event,
                route('filament.marketplace.resources.events.edit', ['record' => $event->id])
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create event notification', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        // Only for marketplace events
        if (!$event->marketplace_client_id || !$event->marketplace_organizer_id) {
            return;
        }

        // Only notify for significant changes (skip minor updates)
        $significantFields = [
            'title', 'event_date', 'start_time', 'venue_id',
            'is_cancelled', 'is_postponed', 'is_published',
        ];

        $hasSignificantChange = false;
        foreach ($significantFields as $field) {
            if ($event->isDirty($field)) {
                $hasSignificantChange = true;
                break;
            }
        }

        if (!$hasSignificantChange) {
            return;
        }

        try {
            $organizer = $event->marketplaceOrganizer;
            $organizerName = $organizer?->company_name ?? $organizer?->name ?? 'Organizator necunoscut';

            $eventTitle = $event->getTranslation('title', 'ro')
                ?: $event->getTranslation('title', 'en')
                ?: $event->getTranslation('title')
                ?: 'Eveniment';

            $this->notificationService->notifyEventUpdated(
                $event->marketplace_client_id,
                $eventTitle,
                $organizerName,
                $event,
                route('filament.marketplace.resources.events.edit', ['record' => $event->id])
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create event update notification', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
