<?php

namespace App\Services\PromoCodes;

/**
 * Promo Code Calculator
 *
 * Calculates discount amounts for promo codes
 */
class PromoCodeCalculator
{
    /**
     * Calculate discount for a promo code applied to a cart
     *
     * @param array $promoCode
     * @param array $cart
     * @return array ['discount_amount' => float, 'applied_to' => array, 'final_amount' => float]
     */
    public function calculate(array $promoCode, array $cart): array
    {
        $subtotal = $cart['subtotal'] ?? $cart['total'] ?? 0;
        $items = $cart['items'] ?? [];

        $result = [
            'discount_amount' => 0,
            'applied_to' => [],
            'original_amount' => $subtotal,
            'final_amount' => $subtotal,
        ];

        switch ($promoCode['applies_to']) {
            case 'cart':
                $result = $this->calculateCartDiscount($promoCode, $subtotal);
                break;

            case 'event':
                $result = $this->calculateEventDiscount($promoCode, $items);
                break;

            case 'ticket_type':
                $result = $this->calculateTicketTypeDiscount($promoCode, $items);
                break;
        }

        return $result;
    }

    /**
     * Calculate discount for entire cart
     *
     * @param array $promoCode
     * @param float $cartTotal
     * @return array
     */
    protected function calculateCartDiscount(array $promoCode, float $cartTotal): array
    {
        $discount = $this->calculateDiscountAmount($promoCode, $cartTotal);

        return [
            'discount_amount' => $discount,
            'applied_to' => ['cart'],
            'original_amount' => $cartTotal,
            'final_amount' => max(0, $cartTotal - $discount),
        ];
    }

    /**
     * Calculate discount for specific event items
     *
     * @param array $promoCode
     * @param array $items
     * @return array
     */
    protected function calculateEventDiscount(array $promoCode, array $items): array
    {
        $eventId = $promoCode['event_id'];
        $applicableTotal = 0;
        $appliedTo = [];

        foreach ($items as $item) {
            if (isset($item['event_id']) && $item['event_id'] === $eventId) {
                $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                $applicableTotal += $itemTotal;
                $appliedTo[] = [
                    'item_id' => $item['id'] ?? null,
                    'event_id' => $item['event_id'],
                    'amount' => $itemTotal,
                ];
            }
        }

        $discount = $this->calculateDiscountAmount($promoCode, $applicableTotal);

        return [
            'discount_amount' => $discount,
            'applied_to' => $appliedTo,
            'original_amount' => $applicableTotal,
            'final_amount' => max(0, $applicableTotal - $discount),
        ];
    }

    /**
     * Calculate discount for specific ticket type items
     *
     * @param array $promoCode
     * @param array $items
     * @return array
     */
    protected function calculateTicketTypeDiscount(array $promoCode, array $items): array
    {
        $ticketTypeId = $promoCode['ticket_type_id'];
        $applicableTotal = 0;
        $appliedTo = [];

        foreach ($items as $item) {
            if (isset($item['ticket_type_id']) && $item['ticket_type_id'] === $ticketTypeId) {
                $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                $applicableTotal += $itemTotal;
                $appliedTo[] = [
                    'item_id' => $item['id'] ?? null,
                    'ticket_type_id' => $item['ticket_type_id'],
                    'amount' => $itemTotal,
                ];
            }
        }

        $discount = $this->calculateDiscountAmount($promoCode, $applicableTotal);

        return [
            'discount_amount' => $discount,
            'applied_to' => $appliedTo,
            'original_amount' => $applicableTotal,
            'final_amount' => max(0, $applicableTotal - $discount),
        ];
    }

    /**
     * Calculate the actual discount amount based on type and value
     *
     * @param array $promoCode
     * @param float $amount
     * @return float
     */
    protected function calculateDiscountAmount(array $promoCode, float $amount): float
    {
        $discount = 0;

        if ($promoCode['type'] === 'fixed') {
            // Fixed amount discount
            $discount = min($promoCode['value'], $amount);
        } elseif ($promoCode['type'] === 'percentage') {
            // Percentage discount
            $discount = ($amount * $promoCode['value']) / 100;

            // Apply maximum discount cap if set
            if ($promoCode['max_discount_amount']) {
                $discount = min($discount, $promoCode['max_discount_amount']);
            }
        }

        // Ensure discount doesn't exceed the amount
        return min($discount, $amount);
    }

    /**
     * Format discount for display
     *
     * @param array $promoCode
     * @return string
     */
    public function formatDiscount(array $promoCode): string
    {
        if ($promoCode['type'] === 'fixed') {
            return number_format($promoCode['value'], 2) . ' off';
        }

        $text = $promoCode['value'] . '% off';

        if ($promoCode['max_discount_amount']) {
            $text .= ' (up to ' . number_format($promoCode['max_discount_amount'], 2) . ')';
        }

        return $text;
    }
}
