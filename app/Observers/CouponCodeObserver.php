<?php

namespace App\Observers;

use App\Models\Coupon\CouponCode;
use App\Models\MarketplaceOrganizerPromoCode;
use Illuminate\Support\Facades\Log;

/**
 * Keeps coupon_codes mirrors in sync with their mkt_promo_codes parent.
 *
 * coupon_codes rows whose source = 'organizer' were created as mirrors of
 * MarketplaceOrganizerPromoCode rows so admin can see them at
 * /marketplace/coupon-codes-list. When admin edits or deletes one of those
 * mirrors, the underlying mkt_promo_codes row must move along with it —
 * otherwise the next ListCouponCodes mount() re-syncs the orphaned parent
 * back into a new coupon_codes row.
 */
class CouponCodeObserver
{
    public function updating(CouponCode $coupon): void
    {
        if ($coupon->source !== 'organizer') {
            return;
        }

        if (!$coupon->isDirty('code')) {
            return;
        }

        $oldCode = $coupon->getOriginal('code');
        $newCode = $coupon->code;

        if (!$oldCode || $oldCode === $newCode) {
            return;
        }

        try {
            MarketplaceOrganizerPromoCode::where('marketplace_client_id', $coupon->marketplace_client_id)
                ->where('code', $oldCode)
                ->update(['code' => $newCode]);
        } catch (\Throwable $e) {
            Log::warning('CouponCodeObserver: failed to propagate code rename to mkt_promo_codes', [
                'coupon_id' => $coupon->id,
                'old_code' => $oldCode,
                'new_code' => $newCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function saved(CouponCode $coupon): void
    {
        // Phase B series allocations — keep event_ticket_type_promo_series
        // rows in sync when a coupon's discount info / applicability /
        // usage limits change. Scoped to events in applicable_events to
        // keep the sync bounded; "all events" coupons (empty array) rely
        // on first-access auto-sync inside the tax-template generator.
        $this->syncTouchedEvents($coupon);
    }

    public function deleted(CouponCode $coupon): void
    {
        if ($coupon->source === 'organizer') {
            try {
                MarketplaceOrganizerPromoCode::where('marketplace_client_id', $coupon->marketplace_client_id)
                    ->where('code', $coupon->code)
                    ->delete();
            } catch (\Throwable $e) {
                Log::warning('CouponCodeObserver: failed to propagate delete to mkt_promo_codes', [
                    'coupon_id' => $coupon->id,
                    'code' => $coupon->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncTouchedEvents($coupon);
    }

    private function syncTouchedEvents(CouponCode $coupon): void
    {
        try {
            $eventIds = is_array($coupon->applicable_events)
                ? array_filter(array_map('intval', $coupon->applicable_events))
                : [];
            if (empty($eventIds)) {
                // "All events" scope — too expensive to fan out to every
                // event of the marketplace_client. Fresh events auto-sync
                // on first tax-template access; bulk backfill is done via
                // `php artisan series:sync`.
                return;
            }
            $allocator = app(\App\Services\Marketplace\SeriesAllocator::class);
            foreach (\App\Models\Event::whereIn('id', $eventIds)->with('ticketTypes')->get() as $event) {
                $allocator->syncForEvent($event);
            }
        } catch (\Throwable $e) {
            Log::warning('[SeriesAllocator] coupon sync failed for coupon ' . $coupon->id . ': ' . $e->getMessage());
        }
    }
}
