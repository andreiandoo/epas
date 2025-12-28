<?php

namespace App\Listeners\PromoCodes;

use App\Events\PromoCodes\PromoCodeExpired;
use App\Events\PromoCodes\PromoCodeDepleted;
use App\Services\Alerts\AlertService;

/**
 * Send alerts when promo codes expire or are depleted
 */
class SendPromoCodeAlerts
{
    public function __construct(
        protected AlertService $alertService
    ) {}

    /**
     * Handle promo code expired event
     */
    public function handleExpired(PromoCodeExpired $event): void
    {
        $this->alertService->send([
            'type' => 'promo_code_expired',
            'severity' => 'low',
            'title' => 'Promo Code Expired',
            'message' => "Promo code '{$event->promoCode['code']}' has expired.",
            'metadata' => [
                'promo_code_id' => $event->promoCode['id'],
                'code' => $event->promoCode['code'],
                'tenant_id' => $event->promoCode['tenant_id'],
                'usage_count' => $event->promoCode['usage_count'],
            ],
            'channels' => ['email'],
        ]);
    }

    /**
     * Handle promo code depleted event
     */
    public function handleDepleted(PromoCodeDepleted $event): void
    {
        $this->alertService->send([
            'type' => 'promo_code_depleted',
            'severity' => 'medium',
            'title' => 'Promo Code Depleted',
            'message' => "Promo code '{$event->promoCode['code']}' has reached its usage limit.",
            'metadata' => [
                'promo_code_id' => $event->promoCode['id'],
                'code' => $event->promoCode['code'],
                'tenant_id' => $event->promoCode['tenant_id'],
                'usage_count' => $event->promoCode['usage_count'],
                'usage_limit' => $event->promoCode['usage_limit'],
            ],
            'channels' => ['email'],
        ]);
    }
}
