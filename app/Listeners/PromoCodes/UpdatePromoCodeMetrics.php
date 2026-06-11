<?php

namespace App\Listeners\PromoCodes;

use App\Events\PromoCodes\PromoCodeUsed;
use Illuminate\Support\Facades\DB;

/**
 * Update real-time metrics when promo codes are used
 */
class UpdatePromoCodeMetrics
{
    /**
     * Handle promo code used event
     */
    public function handle(PromoCodeUsed $event): void
    {
        // Update daily metrics
        $date = now()->toDateString();

        DB::table('promo_code_metrics')->updateOrInsert(
            [
                'promo_code_id' => $event->promoCode['id'],
                'date' => $date,
            ],
            [
                'uses' => DB::raw('uses + 1'),
                'total_discount' => DB::raw('total_discount + ' . ($event->usageData['discount_amount'] ?? 0)),
                'total_revenue' => DB::raw('total_revenue + ' . ($event->usageData['original_amount'] ?? 0)),
                'updated_at' => now(),
            ]
        );
    }
}
