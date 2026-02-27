<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Coupon\CouponCode;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizerPromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends BaseController
{
    /**
     * Validate a promo code for checkout
     * Searches both mkt_promo_codes and coupon_codes tables
     */
    public function validate(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'code' => 'required|string',
            'event_id' => 'required|integer',
            'cart_total' => 'required|numeric|min:0',
            'ticket_count' => 'nullable|integer|min:1',
            'items' => 'nullable|array',
            'customer_email' => 'nullable|email',
        ]);

        $code = strtoupper($validated['code']);

        // 1. Try organizer promo codes (mkt_promo_codes table)
        $promoCode = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $client->id)
            ->where('code', $code)
            ->first();

        if ($promoCode) {
            return $this->validateOrganizerPromoCode($promoCode, $validated);
        }

        // 2. Try coupon codes (coupon_codes table)
        $couponCode = CouponCode::where('marketplace_client_id', $client->id)
            ->where('code', $code)
            ->first();

        if ($couponCode) {
            return $this->validateCouponCode($couponCode, $validated);
        }

        return $this->error('Invalid promo code', 404);
    }

    /**
     * Validate an organizer promo code (mkt_promo_codes)
     */
    protected function validateOrganizerPromoCode(MarketplaceOrganizerPromoCode $promoCode, array $validated): JsonResponse
    {
        $cart = [
            'event_id' => $validated['event_id'],
            'total' => $validated['cart_total'],
            'ticket_count' => $validated['ticket_count'] ?? 1,
            'items' => $validated['items'] ?? [],
        ];

        $validation = $promoCode->validateForCart(
            $cart,
            $validated['customer_email'] ?? null
        );

        if (!$validation['valid']) {
            return $this->error($validation['reason'], 400);
        }

        $calculation = $promoCode->calculateDiscount($cart);

        return $this->success([
            'valid' => true,
            'promo_code' => [
                'id' => $promoCode->id,
                'code' => $promoCode->code,
                'type' => $promoCode->type,
                'value' => (float) $promoCode->value,
                'formatted_discount' => $promoCode->getFormattedDiscount(),
                'description' => $promoCode->description,
            ],
            'discount' => [
                'amount' => $calculation['discount_amount'],
                'original_amount' => $calculation['original_amount'],
                'final_amount' => $calculation['final_amount'],
            ],
        ]);
    }

    /**
     * Validate a coupon code (coupon_codes table)
     */
    protected function validateCouponCode(CouponCode $couponCode, array $validated): JsonResponse
    {
        $eventId = (int) $validated['event_id'];
        $cartTotal = (float) $validated['cart_total'];

        // Check basic validity (status, dates, usage limits)
        if (!$couponCode->isValid()) {
            return $this->error('Promo code is not active or has expired', 400);
        }

        // Check time restrictions (day of week, hours)
        if (!$couponCode->isValidAtTime()) {
            return $this->error('Promo code is not valid at this time', 400);
        }

        // Check minimum purchase amount
        if (!$couponCode->isValidForAmount($cartTotal)) {
            return $this->error("Minimum purchase of {$couponCode->min_purchase_amount} required", 400);
        }

        // Check per-user usage limit
        if (!empty($validated['customer_email'])) {
            $userId = MarketplaceCustomer::where('marketplace_client_id', $couponCode->marketplace_client_id)
                ->where('email', $validated['customer_email'])
                ->value('id');
            if ($userId && !$couponCode->isValidForUser($userId)) {
                return $this->error('You have already used this promo code the maximum number of times', 400);
            }
        }

        // Check event targeting (applicable_events)
        $applicableEvents = $couponCode->applicable_events;
        if (!empty($applicableEvents) && !in_array($eventId, array_map('intval', $applicableEvents))) {
            return $this->error('Promo code is not valid for this event', 400);
        }

        // Check ticket type targeting (applicable_ticket_types)
        $applicableTicketTypes = $couponCode->applicable_ticket_types;
        if (!empty($applicableTicketTypes) && !empty($validated['items'])) {
            $hasApplicableTicket = false;
            foreach ($validated['items'] as $item) {
                $ticketTypeId = (int) ($item['ticket_type_id'] ?? 0);
                if (in_array($ticketTypeId, array_map('intval', $applicableTicketTypes))) {
                    $hasApplicableTicket = true;
                    break;
                }
            }
            if (!$hasApplicableTicket) {
                return $this->error('Promo code is not valid for selected ticket types', 400);
            }
        }

        // Calculate discount
        $discount = $couponCode->calculateDiscount($cartTotal);

        // Build formatted discount string
        $formattedDiscount = $couponCode->discount_type === 'percentage'
            ? "{$couponCode->discount_value}%"
            : number_format($couponCode->discount_value, 2) . ' RON';

        return $this->success([
            'valid' => true,
            'promo_code' => [
                'id' => $couponCode->id,
                'code' => $couponCode->code,
                'type' => $couponCode->discount_type === 'percentage' ? 'percentage' : 'fixed',
                'value' => (float) $couponCode->discount_value,
                'formatted_discount' => $formattedDiscount,
                'description' => null,
            ],
            'discount' => [
                'amount' => $discount,
                'original_amount' => $cartTotal,
                'final_amount' => max(0, $cartTotal - $discount),
            ],
        ]);
    }

    /**
     * Get public promo codes for an event
     * Only returns is_public=true promo codes
     */
    public function publicCodes(Request $request, int $eventId): JsonResponse
    {
        $client = $this->requireClient($request);

        $promoCodes = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $client->id)
            ->forEvent($eventId)
            ->public()
            ->valid()
            ->get();

        return $this->success([
            'promo_codes' => $promoCodes->map(function ($promoCode) {
                return [
                    'code' => $promoCode->code,
                    'type' => $promoCode->type,
                    'value' => (float) $promoCode->value,
                    'formatted_discount' => $promoCode->getFormattedDiscount(),
                    'description' => $promoCode->description,
                    'min_purchase_amount' => $promoCode->min_purchase_amount ? (float) $promoCode->min_purchase_amount : null,
                    'min_tickets' => $promoCode->min_tickets,
                    'expires_at' => $promoCode->expires_at?->toIso8601String(),
                ];
            }),
        ]);
    }
}
