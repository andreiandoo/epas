<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Coupon\CouponCode;
use App\Services\CartService;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Get session ID from request
     * SECURITY FIX: Generate cryptographically secure session IDs
     * Previously used md5(IP + UserAgent) which was predictable
     */
    protected function getSessionId(Request $request): string
    {
        // First try to get existing session ID from secure sources
        $sessionId = $request->session()->getId();
        if ($sessionId && strlen($sessionId) >= 32) {
            return $sessionId;
        }

        // Check cookie (must be validated)
        $cookieSession = $request->cookie('session_id');
        if ($cookieSession && preg_match('/^[a-zA-Z0-9]{32,64}$/', $cookieSession)) {
            return $cookieSession;
        }

        // SECURITY FIX: Generate cryptographically secure random ID
        // Previously was: md5($request->ip() . $request->userAgent()) - PREDICTABLE!
        return bin2hex(random_bytes(32));
    }

    /**
     * Get cart contents
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $sessionId = $this->getSessionId($request);
        $cart = $this->cartService->getCart($sessionId, $tenant->id);

        return response()->json([
            'success' => true,
            'data' => $this->cartService->formatCart($cart, $tenant->id),
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'ticket_type_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10',
            'seat_ids' => 'nullable|array',
            'seat_ids.*' => 'integer',
        ]);

        $sessionId = $this->getSessionId($request);

        $result = $this->cartService->addItem(
            $sessionId,
            $tenant->id,
            $validated['event_id'],
            $validated['ticket_type_id'],
            $validated['quantity'],
            $validated['seat_ids'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'available' => $result['available'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
            'data' => $result['cart'],
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, string $itemId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        $sessionId = $this->getSessionId($request);

        $result = $this->cartService->updateItem(
            $sessionId,
            $tenant->id,
            $itemId,
            $validated['quantity']
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart updated',
            'data' => $result['cart'],
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $sessionId = $this->getSessionId($request);

        $result = $this->cartService->removeItem($sessionId, $tenant->id, $itemId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'data' => $result['cart'],
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $sessionId = $this->getSessionId($request);

        $result = $this->cartService->clearCart($sessionId, $tenant->id);

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared',
            'data' => $result['cart'],
        ]);
    }

    /**
     * Apply promo code
     */
    public function applyPromoCode(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'cart' => 'nullable|array',
        ]);

        // Find coupon code for this tenant
        $coupon = CouponCode::where('tenant_id', $tenant->id)
            ->byCode($validated['code'])
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Codul promoțional nu a fost găsit.',
            ], 404);
        }

        // Check if coupon is valid (active, not expired, not exhausted)
        if (!$coupon->isValid()) {
            $message = 'Codul promoțional nu este valid.';

            if ($coupon->status !== 'active') {
                $message = 'Codul promoțional nu mai este activ.';
            } elseif ($coupon->expires_at && $coupon->expires_at->isPast()) {
                $message = 'Codul promoțional a expirat.';
            } elseif ($coupon->starts_at && $coupon->starts_at->isFuture()) {
                $message = 'Codul promoțional nu este încă valid.';
            } elseif ($coupon->max_uses_total && $coupon->current_uses >= $coupon->max_uses_total) {
                $message = 'Codul promoțional a atins limita maximă de utilizări.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }

        // Check time-based restrictions
        if (!$coupon->isValidAtTime()) {
            return response()->json([
                'success' => false,
                'message' => 'Codul promoțional nu este valid în acest moment.',
            ], 400);
        }

        // Get cart total from stored cart
        $sessionId = $this->getSessionId($request);
        $cart = $this->cartService->getCart($sessionId, $tenant->id);
        $formattedCart = $this->cartService->formatCart($cart, $tenant->id);
        $cartTotal = $formattedCart['subtotal_cents'] / 100;

        // Check minimum purchase amount
        if ($cartTotal > 0 && !$coupon->isValidForAmount($cartTotal)) {
            return response()->json([
                'success' => false,
                'message' => 'Comanda minimă pentru acest cod promoțional este ' .
                    number_format($coupon->min_purchase_amount, 2) . ' ' . ($tenant->settings['currency'] ?? 'RON') . '.',
            ], 400);
        }

        // Calculate discount
        $discountAmount = $coupon->calculateDiscount($cartTotal);

        // Get campaign name for display
        $campaignName = $coupon->campaign ?
            ($coupon->campaign->getTranslation('name', app()->getLocale()) ?? $coupon->campaign->name ?? $validated['code']) :
            $validated['code'];

        // Save promo code to cart
        $result = $this->cartService->applyPromoCode($sessionId, $tenant->id, $coupon->code, [
            'discount_type' => $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed',
            'discount_value' => (float) $coupon->discount_value,
            'discount_amount' => $discountAmount,
            'max_discount_amount' => $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null,
            'name' => $campaignName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Codul promoțional a fost aplicat cu succes!',
            'data' => $result['cart'],
        ]);
    }

    /**
     * Remove promo code
     */
    public function removePromoCode(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $sessionId = $this->getSessionId($request);
        $result = $this->cartService->removePromoCode($sessionId, $tenant->id);

        return response()->json([
            'success' => true,
            'message' => 'Promo code removed',
            'data' => $result['cart'],
        ]);
    }

    /**
     * Create a Stripe Payment Intent for inline checkout
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:3',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string|max:255',
            'order_description' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        // Get tenant's active payment config
        $paymentConfig = $tenant->activePaymentConfig();

        if (!$paymentConfig || $paymentConfig->processor !== 'stripe') {
            return response()->json([
                'success' => false,
                'message' => 'Stripe payment is not configured for this tenant.',
            ], 400);
        }

        try {
            $processor = PaymentProcessorFactory::makeFromConfig($paymentConfig);

            if (!$processor->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe payment is not properly configured.',
                ], 400);
            }

            $result = $processor->createPaymentIntent([
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? $tenant->settings['currency'] ?? 'ron',
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'description' => $validated['order_description'] ?? 'Ticket purchase',
                'metadata' => array_merge(
                    ['tenant_id' => $tenant->id],
                    $validated['metadata'] ?? []
                ),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $result['client_secret'],
                    'publishable_key' => $result['publishable_key'],
                    'payment_intent_id' => $result['payment_intent_id'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Stripe publishable key for the tenant
     */
    public function getPaymentConfig(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get tenant's active payment config
        $paymentConfig = $tenant->activePaymentConfig();

        if (!$paymentConfig) {
            return response()->json([
                'success' => true,
                'data' => [
                    'processor' => null,
                    'configured' => false,
                ],
            ]);
        }

        $publishableKey = null;
        if ($paymentConfig->processor === 'stripe') {
            try {
                $processor = PaymentProcessorFactory::makeFromConfig($paymentConfig);
                $publishableKey = $processor->getPublishableKey();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'processor' => $paymentConfig->processor,
                'configured' => $paymentConfig->processor === 'stripe' && !empty($publishableKey),
                'publishable_key' => $publishableKey,
            ],
        ]);
    }
}
