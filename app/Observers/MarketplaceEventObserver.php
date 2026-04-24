<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\MarketplaceNotificationService;
use Illuminate\Support\Facades\DB;
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
        if ($event->wasChanged('marketplace_organizer_id')) {
            $this->propagateOrganizerChange($event);
        }

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

    /**
     * Propagate a change of marketplace_organizer_id from the event to all
     * denormalized copies on related tables (currently only `orders`).
     *
     * Without this, the organizer dashboard, payouts, refunds etc. keep
     * pointing to the previous organizer because they filter by
     * `orders.marketplace_organizer_id`, not by `events.marketplace_organizer_id`.
     */
    protected function propagateOrganizerChange(Event $event): void
    {
        $newOrganizerId = $event->marketplace_organizer_id;
        $oldOrganizerId = $event->getOriginal('marketplace_organizer_id');

        try {
            $ordersUpdated = DB::transaction(function () use ($event, $newOrganizerId) {
                return DB::table('orders')
                    ->where('event_id', $event->id)
                    ->where(function ($q) use ($newOrganizerId) {
                        $q->where('marketplace_organizer_id', '!=', $newOrganizerId)
                            ->orWhereNull('marketplace_organizer_id');
                    })
                    ->update(['marketplace_organizer_id' => $newOrganizerId]);
            });

            Log::info('Event organizer changed — propagated to related records', [
                'event_id' => $event->id,
                'old_organizer_id' => $oldOrganizerId,
                'new_organizer_id' => $newOrganizerId,
                'orders_updated' => $ordersUpdated,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to propagate event organizer change', [
                'event_id' => $event->id,
                'old_organizer_id' => $oldOrganizerId,
                'new_organizer_id' => $newOrganizerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
