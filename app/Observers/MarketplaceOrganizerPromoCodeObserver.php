<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\TicketType;
use App\Services\Marketplace\SeriesAllocator;
use Illuminate\Support\Facades\Log;

/**
 * Keep event_ticket_type_promo_series in sync whenever a promo code is
 * created/updated/deleted. Re-runs SeriesAllocator::syncForEvent on every
 * event the promo touches.
 *
 * Touched events resolved from:
 *   - promo.marketplace_event_id (event-scoped promo)
 *   - promo.ticket_type_id → ticket_type.event_id (legacy single-type)
 *   - promo.applicable_ticket_type_ids → events of those types (array)
 */
class MarketplaceOrganizerPromoCodeObserver
{
    public function saved(MarketplaceOrganizerPromoCode $promo): void
    {
        $this->syncTouchedEvents($promo);
    }

    public function deleted(MarketplaceOrganizerPromoCode $promo): void
    {
        $this->syncTouchedEvents($promo);
    }

    private function syncTouchedEvents(MarketplaceOrganizerPromoCode $promo): void
    {
        try {
            $eventIds = $this->resolveTouchedEventIds($promo);
            if (empty($eventIds)) {
                return;
            }
            $allocator = app(SeriesAllocator::class);
            foreach (Event::whereIn('id', $eventIds)->with('ticketTypes')->get() as $event) {
                $allocator->syncForEvent($event);
            }
        } catch (\Throwable $e) {
            // Don't let a sync failure cascade into the promo save flow.
            Log::warning('[SeriesAllocator] sync failed for promo ' . $promo->id . ': ' . $e->getMessage());
        }
    }

    private function resolveTouchedEventIds(MarketplaceOrganizerPromoCode $promo): array
    {
        $eventIds = [];

        if ($promo->marketplace_event_id) {
            $eventIds[] = (int) $promo->marketplace_event_id;
        }

        $ttIds = $promo->getApplicableTicketTypeIdsList();
        if (!empty($ttIds)) {
            $eventIds = array_merge(
                $eventIds,
                TicketType::whereIn('id', $ttIds)->pluck('event_id')->filter()->all()
            );
        }

        return array_values(array_unique(array_map('intval', $eventIds)));
    }
}
