<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceOrganizerPromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends BaseController
{
    /**
     * Validate a promo code for checkout
     * This is a public endpoint for customers
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

        // Find the promo code
        $promoCode = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $client->id)
            ->where('code', $code)
            ->first();

        if (!$promoCode) {
            return $this->error('Invalid promo code', 404);
        }

        // Build cart array for validation
        $cart = [
            'event_id' => $validated['event_id'],
            'total' => $validated['cart_total'],
            'ticket_count' => $validated['ticket_count'] ?? 1,
            'items' => $validated['items'] ?? [],
        ];

        // Validate the promo code for this cart
        $validation = $promoCode->validateForCart(
            $cart,
            $validated['customer_email'] ?? null
        );

        if (!$validation['valid']) {
            return $this->error($validation['reason'], 400);
        }

        // Calculate discount
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
