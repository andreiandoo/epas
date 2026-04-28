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

    public function deleted(CouponCode $coupon): void
    {
        if ($coupon->source !== 'organizer') {
            return;
        }

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
}
