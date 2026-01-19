<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;

/**
 * Promo Code Validator
 *
 * Validates whether a promo code can be applied to a cart/order
 */
class PromoCodeValidator
{
    /**
     * Validate a promo code for a cart
     *
     * @param array $promoCode
     * @param array $cart Cart data with items, totals, etc.
     * @param string|null $customerId
     * @return array ['valid' => bool, 'reason' => string|null]
     */
    public function validate(array $promoCode, array $cart, ?string $customerId = null): array
    {
        // Check if code is active
        if ($promoCode['status'] !== 'active') {
            return [
                'valid' => false,
                'reason' => 'This promo code is no longer active',
            ];
        }

        // Check start date
        if ($promoCode['starts_at'] && now()->isBefore($promoCode['starts_at'])) {
            return [
                'valid' => false,
                'reason' => 'This promo code is not yet valid',
            ];
        }

        // Check expiration
        if ($promoCode['expires_at'] && now()->isAfter($promoCode['expires_at'])) {
            return [
                'valid' => false,
                'reason' => 'This promo code has expired',
            ];
        }

        // Check usage limit
        if ($promoCode['usage_limit'] && $promoCode['usage_count'] >= $promoCode['usage_limit']) {
            return [
                'valid' => false,
                'reason' => 'This promo code has reached its usage limit',
            ];
        }

        // Check per-customer usage limit
        if ($customerId && $promoCode['usage_limit_per_customer']) {
            $customerUsage = DB::table('promo_code_usage')
                ->where('promo_code_id', $promoCode['id'])
                ->where('customer_id', $customerId)
                ->count();

            if ($customerUsage >= $promoCode['usage_limit_per_customer']) {
                return [
                    'valid' => false,
                    'reason' => 'You have already used this promo code the maximum number of times',
                ];
            }
        }

        // Check minimum purchase amount
        if ($promoCode['min_purchase_amount']) {
            $cartTotal = $cart['subtotal'] ?? $cart['total'] ?? 0;
            if ($cartTotal < $promoCode['min_purchase_amount']) {
                return [
                    'valid' => false,
                    'reason' => sprintf(
                        'Minimum purchase of %s required to use this promo code',
                        number_format($promoCode['min_purchase_amount'], 2)
                    ),
                ];
            }
        }

        // Check minimum tickets
        if ($promoCode['min_tickets']) {
            $ticketCount = $cart['ticket_count'] ?? count($cart['items'] ?? []);
            if ($ticketCount < $promoCode['min_tickets']) {
                return [
                    'valid' => false,
                    'reason' => sprintf(
                        'Minimum of %d tickets required to use this promo code',
                        $promoCode['min_tickets']
                    ),
                ];
            }
        }

        // Check applicability (event/ticket type)
        if ($promoCode['applies_to'] === 'event' && $promoCode['event_id']) {
            if (!$this->cartContainsEvent($cart, $promoCode['event_id'])) {
                return [
                    'valid' => false,
                    'reason' => 'This promo code is only valid for a specific event',
                ];
            }
        }

        if ($promoCode['applies_to'] === 'ticket_type' && $promoCode['ticket_type_id']) {
            if (!$this->cartContainsTicketType($cart, $promoCode['ticket_type_id'])) {
                return [
                    'valid' => false,
                    'reason' => 'This promo code is only valid for specific ticket types',
                ];
            }
        }

        return [
            'valid' => true,
            'reason' => null,
        ];
    }

    /**
     * Check if cart contains items from a specific event
     *
     * @param array $cart
     * @param string $eventId
     * @return bool
     */
    protected function cartContainsEvent(array $cart, string $eventId): bool
    {
        $items = $cart['items'] ?? [];

        foreach ($items as $item) {
            if (isset($item['event_id']) && $item['event_id'] === $eventId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if cart contains a specific ticket type
     *
     * @param array $cart
     * @param string $ticketTypeId
     * @return bool
     */
    protected function cartContainsTicketType(array $cart, string $ticketTypeId): bool
    {
        $items = $cart['items'] ?? [];

        foreach ($items as $item) {
            if (isset($item['ticket_type_id']) && $item['ticket_type_id'] === $ticketTypeId) {
                return true;
            }
        }

        return false;
    }
}
