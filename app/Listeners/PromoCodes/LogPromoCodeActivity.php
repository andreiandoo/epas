<?php

namespace App\Listeners\PromoCodes;

use App\Events\PromoCodes\PromoCodeCreated;
use App\Events\PromoCodes\PromoCodeUsed;
use App\Events\PromoCodes\PromoCodeExpired;
use App\Events\PromoCodes\PromoCodeDepleted;
use App\Events\PromoCodes\PromoCodeUpdated;
use App\Events\PromoCodes\PromoCodeDeactivated;
use Illuminate\Support\Facades\Log;

/**
 * Log promo code activity for analytics and debugging
 */
class LogPromoCodeActivity
{
    /**
     * Handle promo code created event
     */
    public function handleCreated(PromoCodeCreated $event): void
    {
        Log::info('Promo code created', [
            'promo_code_id' => $event->promoCode['id'],
            'code' => $event->promoCode['code'],
            'type' => $event->promoCode['type'],
            'value' => $event->promoCode['value'],
            'created_by' => $event->userId,
        ]);
    }

    /**
     * Handle promo code used event
     */
    public function handleUsed(PromoCodeUsed $event): void
    {
        Log::info('Promo code used', [
            'promo_code_id' => $event->promoCode['id'],
            'code' => $event->promoCode['code'],
            'order_id' => $event->orderId,
            'discount_amount' => $event->usageData['discount_amount'] ?? null,
            'customer_id' => $event->usageData['customer_id'] ?? null,
        ]);
    }

    /**
     * Handle promo code expired event
     */
    public function handleExpired(PromoCodeExpired $event): void
    {
        Log::info('Promo code expired', [
            'promo_code_id' => $event->promoCode['id'],
            'code' => $event->promoCode['code'],
            'usage_count' => $event->promoCode['usage_count'],
        ]);
    }

    /**
     * Handle promo code depleted event
     */
    public function handleDepleted(PromoCodeDepleted $event): void
    {
        Log::info('Promo code depleted', [
            'promo_code_id' => $event->promoCode['id'],
            'code' => $event->promoCode['code'],
            'usage_count' => $event->promoCode['usage_count'],
            'usage_limit' => $event->promoCode['usage_limit'],
        ]);
    }

    /**
     * Handle promo code updated event
     */
    public function handleUpdated(PromoCodeUpdated $event): void
    {
        Log::info('Promo code updated', [
            'promo_code_id' => $event->promoCode['id'],
            'code' => $event->promoCode['code'],
            'changes' => $event->changes,
            'updated_by' => $event->userId,
        ]);
    }

    /**
     * Handle promo code deactivated event
     */
    public function handleDeactivated(PromoCodeDeactivated $event): void
    {
        Log::info('Promo code deactivated', [
            'promo_code_id' => $event->promoCode['id'],
            'code' => $event->promoCode['code'],
            'deactivated_by' => $event->userId,
        ]);
    }
}
