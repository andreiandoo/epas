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

        return $this->error('Cod promoțional invalid', 404);
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

        // Build applied_to label with event name and ticket type names
        $appliedToLabel = $this->buildAppliedToLabel($promoCode->applies_to, $promoCode->event, $promoCode->ticketType);

        return $this->success([
            'valid' => true,
            'promo_code' => [
                'id' => $promoCode->id,
                'code' => $promoCode->code,
                'type' => $promoCode->type,
                'value' => (float) $promoCode->value,
                'formatted_discount' => $promoCode->getFormattedDiscount(),
                'description' => $promoCode->description,
                'applied_to_label' => $appliedToLabel,
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

        // Check status
        if ($couponCode->status !== 'active') {
            return $this->error('Codul promoțional nu este activ', 400);
        }

        // Check start date — dates are stored as local time (Europe/Bucharest) but cast as UTC
        $now = now('Europe/Bucharest');
        if ($couponCode->starts_at && $couponCode->starts_at->shiftTimezone('Europe/Bucharest')->isAfter($now)) {
            return $this->error('Codul promoțional nu este încă valid', 400);
        }

        // Check expiry date
        if ($couponCode->expires_at && $couponCode->expires_at->shiftTimezone('Europe/Bucharest')->isBefore($now)) {
            return $this->error('Codul promoțional a expirat', 400);
        }

        // Check usage limits
        if ($couponCode->max_uses_total && $couponCode->current_uses >= $couponCode->max_uses_total) {
            return $this->error('Codul promoțional a atins limita de utilizări', 400);
        }

        // Check time restrictions (day of week, hours)
        if (!$couponCode->isValidAtTime()) {
            return $this->error('Codul promoțional nu este valid în acest moment', 400);
        }

        // Check minimum purchase amount
        if (!$couponCode->isValidForAmount($cartTotal)) {
            return $this->error("Suma minimă de achiziție este {$couponCode->min_purchase_amount}", 400);
        }

        // Check per-user usage limit (direct query, avoid isValidForUser which re-runs isValid with wrong timezone)
        if ($couponCode->max_uses_per_user && !empty($validated['customer_email'])) {
            $userId = MarketplaceCustomer::where('marketplace_client_id', $couponCode->marketplace_client_id)
                ->where('email', $validated['customer_email'])
                ->value('id');
            if ($userId) {
                $userUsages = $couponCode->redemptions()
                    ->where('user_id', $userId)
                    ->where('status', '!=', 'cancelled')
                    ->count();
                if ($userUsages >= $couponCode->max_uses_per_user) {
                    return $this->error('Ai folosit deja acest cod promoțional de numărul maxim de ori', 400);
                }
            }
        }

        // Check organizer targeting (marketplace_organizer_id)
        // When a coupon is scoped to an organizer but has no per-event list,
        // it must still only apply to events of that organizer.
        if ($couponCode->marketplace_organizer_id) {
            $eventOrganizerId = \App\Models\Event::where('id', $eventId)
                ->value('marketplace_organizer_id');
            if (!$eventOrganizerId || (int) $eventOrganizerId !== (int) $couponCode->marketplace_organizer_id) {
                return $this->error('Codul promoțional nu este valid pentru acest eveniment', 400);
            }
        }

        // Check event targeting (applicable_events)
        $applicableEvents = $couponCode->applicable_events;
        if (!empty($applicableEvents) && !in_array($eventId, array_map('intval', $applicableEvents))) {
            return $this->error('Codul promoțional nu este valid pentru acest eveniment', 400);
        }

        // Check ticket type targeting (applicable_ticket_types)
        $applicableTicketTypes = $couponCode->applicable_ticket_types;
        $items = $validated['items'] ?? [];

        if (!empty($applicableTicketTypes)) {
            if (empty($items)) {
                // Items not provided but code requires specific ticket types - reject
                return $this->error('Codul promoțional nu este valid pentru tipurile de bilete selectate', 400);
            }

            $hasApplicableTicket = false;
            foreach ($items as $item) {
                $ticketTypeId = (int) ($item['ticket_type_id'] ?? 0);
                if (in_array($ticketTypeId, array_map('intval', $applicableTicketTypes))) {
                    $hasApplicableTicket = true;
                    break;
                }
            }
            if (!$hasApplicableTicket) {
                return $this->error('Codul promoțional nu este valid pentru tipurile de bilete selectate', 400);
            }
        }

        // Calculate discount - filter by applicable ticket types if set
        $discountBase = $cartTotal;
        if (!empty($applicableTicketTypes) && !empty($items)) {
            $discountBase = 0;
            foreach ($items as $item) {
                $ticketTypeId = (int) ($item['ticket_type_id'] ?? 0);
                if (in_array($ticketTypeId, array_map('intval', $applicableTicketTypes))) {
                    $discountBase += (float) ($item['total'] ?? 0);
                }
            }
        }

        $discount = $couponCode->calculateDiscount($discountBase);

        // Build formatted discount string
        $formattedDiscount = $couponCode->discount_type === 'percentage'
            ? "{$couponCode->discount_value}%"
            : number_format($couponCode->discount_value, 2) . ' RON';

        // Build applied_to label from coupon targeting, narrowed to what's
        // actually in the cart (so the message shows only cart events/ticket
        // types the code applies to, not the coupon's full catalog list).
        $cartEventIds = [];
        $cartTicketTypeIds = [];
        foreach ($items as $item) {
            if (!empty($item['event_id'])) {
                $cartEventIds[] = (int) $item['event_id'];
            }
            if (!empty($item['ticket_type_id'])) {
                $cartTicketTypeIds[] = (int) $item['ticket_type_id'];
            }
        }
        $cartEventIds = array_values(array_unique($cartEventIds));
        $cartTicketTypeIds = array_values(array_unique($cartTicketTypeIds));

        $appliedToLabel = $this->buildCouponAppliedToLabel($couponCode, $cartEventIds, $cartTicketTypeIds);

        return $this->success([
            'valid' => true,
            // Flat fields for cart.js compatibility
            'discount_type' => $couponCode->discount_type === 'percentage' ? 'percentage' : 'fixed',
            'discount_value' => (float) $couponCode->discount_value,
            'discount_amount' => $discount,
            // Nested for detailed consumers
            'promo_code' => [
                'id' => $couponCode->id,
                'code' => $couponCode->code,
                'type' => $couponCode->discount_type === 'percentage' ? 'percentage' : 'fixed',
                'value' => (float) $couponCode->discount_value,
                'formatted_discount' => $formattedDiscount,
                'description' => null,
                'applied_to_label' => $appliedToLabel,
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

    /**
     * Build a human-readable label for what the organizer promo code applies to
     */
    protected function buildAppliedToLabel(string $appliesTo, $event, $ticketType): ?string
    {
        if ($appliesTo === 'ticket_type' && $ticketType) {
            $eventName = $event?->name ?? '';
            $ttName = $ticketType->name ?? '';
            $parts = array_filter([$ttName, $eventName]);
            return implode(' — ', $parts) ?: null;
        }

        if ($appliesTo === 'specific_event' && $event) {
            return $event->name ?? null;
        }

        return null; // all_events — no specific label needed
    }

    /**
     * Build a human-readable label for what the coupon code applies to
     */
    protected function buildCouponAppliedToLabel(CouponCode $coupon, ?array $cartEventIds = null, ?array $cartTicketTypeIds = null): ?string
    {
        $parts = [];

        // Ticket type names (CouponCode stores TicketType IDs from ticket_types table).
        // When cart context is supplied, narrow the list to ticket types actually in the cart.
        if (!empty($coupon->applicable_ticket_types)) {
            $ttIds = array_map('intval', $coupon->applicable_ticket_types);
            if ($cartTicketTypeIds !== null) {
                $ttIds = array_values(array_intersect($ttIds, $cartTicketTypeIds));
            }
            if (!empty($ttIds)) {
                $ttNames = \App\Models\TicketType::whereIn('id', $ttIds)->pluck('name')->toArray();
                if ($ttNames) {
                    $parts[] = implode(', ', $ttNames);
                }
            }
        }

        // Event names (CouponCode stores Event IDs from events table).
        // When cart context is supplied, narrow to events in the cart the code
        // applies to; when the coupon has no restriction list, show the cart's
        // events so the label still reflects what's in the order.
        $eventIds = null;
        if (!empty($coupon->applicable_events)) {
            $eventIds = array_map('intval', $coupon->applicable_events);
            if ($cartEventIds !== null) {
                $eventIds = array_values(array_intersect($eventIds, $cartEventIds));
            }
        } elseif ($cartEventIds !== null && !empty($cartEventIds)) {
            $eventIds = $cartEventIds;
        }

        if (!empty($eventIds)) {
            $events = \App\Models\Event::whereIn('id', $eventIds)->get();
            $eventNames = $events->map(function ($event) {
                $title = $event->title;
                return is_array($title) ? ($title['ro'] ?? $title['en'] ?? reset($title) ?: '') : ($title ?? '');
            })->filter()->toArray();
            if ($eventNames) {
                $parts[] = implode(', ', $eventNames);
            }
        }

        return !empty($parts) ? implode(' — ', $parts) : null;
    }
}
