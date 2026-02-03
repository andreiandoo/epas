<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\AffiliateTrackingService;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    protected AffiliateTrackingService $affiliateService;
    protected GamificationService $gamificationService;

    public function __construct(
        AffiliateTrackingService $affiliateService,
        GamificationService $gamificationService
    ) {
        $this->affiliateService = $affiliateService;
        $this->gamificationService = $gamificationService;
    }
    /**
     * Initialize checkout
     */
    public function init(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get cart and validate items
        // Calculate totals
        $subtotal = 0; // TODO: Calculate from cart

        // Check if tenant has WhatsApp notifications microservice enabled
        $hasWhatsApp = $tenant->microservices()
            ->where('slug', 'whatsapp-notifications')
            ->wherePivot('is_active', true)
            ->exists();

        // Check gamification microservice and get redemption info
        $gamificationData = $this->getGamificationCheckoutData($request, $tenant, $subtotal);

        return response()->json([
            'success' => true,
            'data' => [
                // SECURITY FIX: Use cryptographically secure random ID
                'checkout_id' => 'chk_' . bin2hex(random_bytes(12)),
                'items' => [],
                'subtotal' => 0,
                'fees' => 0,
                'discount' => 0,
                'total' => 0,
                'currency' => $tenant->settings['currency'] ?? 'RON',
                'payment_methods' => $this->getPaymentMethods($tenant),
                'has_whatsapp' => $hasWhatsApp,
                'requires_billing' => true,
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
                'gamification' => $gamificationData,
            ],
        ]);
    }

    /**
     * Submit checkout (create order and initiate payment)
     */
    public function submit(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'checkout_id' => 'nullable|string',
            'cart_id' => 'nullable|string',
            'payment_method' => 'required|string|in:stripe,netopia,euplatesc,payu',
            // Support both nested billing object and flat fields
            'billing' => 'nullable|array',
            'billing.first_name' => 'nullable|string|max:255',
            'billing.last_name' => 'nullable|string|max:255',
            'billing.name' => 'nullable|string|max:255',
            'billing.email' => 'nullable|email|max:255',
            'billing.phone' => 'nullable|string|max:50',
            'billing.address' => 'nullable|string|max:500',
            'billing.city' => 'nullable|string|max:100',
            'billing.country' => 'nullable|string|max:100',
            // Flat fields (from frontend)
            'billing_first_name' => 'nullable|string|max:255',
            'billing_last_name' => 'nullable|string|max:255',
            'billing_email' => 'nullable|email|max:255',
            'billing_phone' => 'nullable|string|max:50',
            'add_insurance' => 'nullable|boolean',
            'affiliate_cookie' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            // Gamification points redemption
            'redeem_points' => 'nullable|integer|min:0',
            // Ticket beneficiaries
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.name' => 'required_with:beneficiaries|string|max:255',
            'beneficiaries.*.email' => 'nullable|email|max:255',
            'beneficiaries.*.phone' => 'nullable|string|max:50',
            'beneficiaries.*.ticket_index' => 'nullable|integer',
        ]);

        // Normalize billing data - support both nested and flat fields
        $firstName = $validated['billing_first_name'] ?? $validated['billing']['first_name'] ?? null;
        $lastName = $validated['billing_last_name'] ?? $validated['billing']['last_name'] ?? null;
        $fullName = $validated['billing']['name'] ?? null;

        // If first_name/last_name not provided but full name is, try to split it
        if (!$firstName && !$lastName && $fullName) {
            $nameParts = explode(' ', trim($fullName), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
        }

        $billingData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => trim(($firstName ?? '') . ' ' . ($lastName ?? '')),
            'email' => $validated['billing_email'] ?? $validated['billing']['email'] ?? null,
            'phone' => $validated['billing_phone'] ?? $validated['billing']['phone'] ?? null,
            'address' => $validated['billing']['address'] ?? null,
            'city' => $validated['billing']['city'] ?? null,
            'country' => $validated['billing']['country'] ?? null,
        ];

        // Create order
        // TODO: Implement actual order creation with $billingData
        // SECURITY FIX: Use cryptographically secure random ID
        $orderId = 'ord_' . bin2hex(random_bytes(12));
        $orderAmountCents = 0; // TODO: Calculate from cart

        // Get customer ID for gamification
        $customerId = $this->getCustomerId($request);
        $pointsRedemptionData = null;

        // Handle points redemption if requested
        $redeemPoints = $validated['redeem_points'] ?? 0;
        if ($redeemPoints > 0 && $customerId) {
            $hasGamification = $tenant->microservices()
                ->where('slug', 'gamification')
                ->wherePivot('is_active', true)
                ->exists();

            if ($hasGamification) {
                $redemptionResult = $this->gamificationService->redeemPoints(
                    $tenant->id,
                    $customerId,
                    $redeemPoints,
                    $orderAmountCents,
                    'App\\Models\\Order',
                    0 // Will be updated after order creation
                );

                if (!$redemptionResult['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $redemptionResult['error'] ?? 'Failed to redeem points',
                    ], 400);
                }

                $pointsRedemptionData = $redemptionResult;
                // Reduce order amount by discount
                $orderAmountCents = max(0, $orderAmountCents - $redemptionResult['discount_cents']);
            }
        }

        // Track affiliate conversion if affiliate cookie is present
        if (!empty($validated['affiliate_cookie']) || !empty($validated['coupon_code'])) {
            $this->affiliateService->confirmOrder([
                'tenant_id' => $tenant->id,
                'order_ref' => $orderId,
                'order_amount' => $orderAmountCents / 100,
                'buyer_email' => $billingData['email'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'cookie_value' => $validated['affiliate_cookie'] ?? null,
            ]);
        }

        // Initialize payment with selected processor

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'payment' => [
                    'method' => $validated['payment_method'],
                    'redirect_url' => 'https://payment-gateway.com/pay/xxx',
                    // Or for Stripe:
                    // 'client_secret' => 'pi_xxx_secret_xxx',
                ],
                'points_redeemed' => $pointsRedemptionData,
            ],
        ]);
    }

    /**
     * Handle payment callback/webhook
     */
    public function paymentCallback(Request $request, string $provider): JsonResponse
    {
        // Verify payment status from provider
        // Update order status
        // Generate tickets if successful

        $orderId = $request->input('order_id');
        $tenantId = $request->input('tenant_id');
        $customerId = $request->input('customer_id');
        $orderAmountCents = $request->input('order_amount_cents', 0);
        $paymentStatus = 'paid'; // TODO: Get from provider

        // Approve affiliate conversion if payment was successful
        if ($paymentStatus === 'paid' && $tenantId) {
            $this->affiliateService->approveConversion($orderId, $tenantId);

            // Award gamification points for the order
            $this->awardOrderPoints($tenantId, $customerId, $orderAmountCents, $orderId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'status' => $paymentStatus,
            ],
        ]);
    }

    /**
     * Get order status
     */
    public function orderStatus(Request $request, string $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get order
        // Order::where('tenant_id', $tenant->id)->where('order_number', $orderId)->firstOrFail()

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'status' => 'paid',
                'items' => [],
                'total' => 0,
                'tickets' => [],
            ],
        ]);
    }

    /**
     * Get insurance quote
     */
    public function insuranceQuote(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'order_total' => 'required|numeric|min:0',
        ]);

        // Calculate insurance premium

        return response()->json([
            'success' => true,
            'data' => [
                'premium' => round($validated['order_total'] * 0.05, 2),
                'coverage' => 'Full refund if event is cancelled',
            ],
        ]);
    }

    protected function getPaymentMethods($tenant): array
    {
        $settings = $tenant->settings ?? [];
        $methods = [];

        if (!empty($settings['payments']['stripe']['enabled'])) {
            $methods[] = [
                'id' => 'stripe',
                'name' => 'Card Payment',
                'icon' => 'credit-card',
            ];
        }
        if (!empty($settings['payments']['netopia']['enabled'])) {
            $methods[] = [
                'id' => 'netopia',
                'name' => 'Netopia',
                'icon' => 'netopia',
            ];
        }

        return $methods;
    }

    /**
     * Get gamification checkout data for the current customer
     */
    protected function getGamificationCheckoutData(Request $request, $tenant, int $orderTotalCents): ?array
    {
        // Check if gamification microservice is enabled
        $hasGamification = $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasGamification) {
            return null;
        }

        $customerId = $this->getCustomerId($request);

        if (!$customerId) {
            // Return config only for non-authenticated users
            $config = $this->gamificationService->getConfig($tenant->id);
            if (!$config || !$config->is_active) {
                return null;
            }

            return [
                'enabled' => true,
                'can_redeem' => false,
                'earn_percentage' => $config->earn_percentage,
                'points_to_earn' => $config->calculateEarnedPoints($orderTotalCents),
                'points_name' => $config->points_name,
            ];
        }

        // Get redemption eligibility for authenticated users
        $redemptionData = $this->gamificationService->canRedeemPoints(
            $tenant->id,
            $customerId,
            $orderTotalCents
        );

        $config = $this->gamificationService->getConfig($tenant->id);
        if (!$config || !$config->is_active) {
            return null;
        }

        return [
            'enabled' => true,
            'can_redeem' => $redemptionData['can_redeem'],
            'current_balance' => $redemptionData['current_balance'],
            'max_redeemable' => $redemptionData['max_redeemable'],
            'max_discount_cents' => $redemptionData['max_discount_cents'],
            'min_redeem_points' => $redemptionData['min_redeem_points'],
            'point_value_cents' => $redemptionData['point_value_cents'],
            'earn_percentage' => $config->earn_percentage,
            'points_to_earn' => $config->calculateEarnedPoints($orderTotalCents),
            'points_name' => $config->points_name,
            'currency' => $config->currency,
        ];
    }

    /**
     * Get customer ID from request (bearer token or session)
     */
    protected function getCustomerId(Request $request): ?int
    {
        // Try to get from bearer token
        $token = $request->bearerToken();
        if ($token) {
            $customer = \App\Models\Customer::where('api_token', $token)->first();
            if ($customer) {
                return $customer->id;
            }
        }

        // Try from request attribute (set by auth middleware)
        $customer = $request->attributes->get('customer');
        if ($customer) {
            return $customer->id;
        }

        return null;
    }

    /**
     * Award gamification points for a completed order
     */
    protected function awardOrderPoints(int $tenantId, ?int $customerId, int $orderAmountCents, string $orderId): void
    {
        if (!$customerId || $orderAmountCents <= 0) {
            return;
        }

        // Check if gamification is enabled
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }

        $hasGamification = $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasGamification) {
            return;
        }

        // Award points for the order
        $this->gamificationService->awardOrderPoints(
            $tenantId,
            $customerId,
            $orderAmountCents,
            'App\\Models\\Order',
            (int) str_replace('ord_', '', $orderId),
            ['order_number' => $orderId]
        );
    }
}
