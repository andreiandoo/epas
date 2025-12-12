<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\AffiliateTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    protected AffiliateTrackingService $affiliateService;

    public function __construct(AffiliateTrackingService $affiliateService)
    {
        $this->affiliateService = $affiliateService;
    }
    /**
     * Initialize checkout
     */
    public function init(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get cart and validate items
        // Calculate totals

        // Check if tenant has WhatsApp notifications microservice enabled
        $hasWhatsApp = $tenant->microservices()
            ->where('slug', 'whatsapp-notifications')
            ->wherePivot('is_active', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'checkout_id' => 'chk_' . uniqid(),
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
        $orderId = 'ord_' . uniqid();
        $orderAmount = 0; // TODO: Calculate from cart

        // Track affiliate conversion if affiliate cookie is present
        if (!empty($validated['affiliate_cookie']) || !empty($validated['coupon_code'])) {
            $this->affiliateService->confirmOrder([
                'tenant_id' => $tenant->id,
                'order_ref' => $orderId,
                'order_amount' => $orderAmount,
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
        $paymentStatus = 'paid'; // TODO: Get from provider

        // Approve affiliate conversion if payment was successful
        if ($paymentStatus === 'paid' && $tenantId) {
            $this->affiliateService->approveConversion($orderId, $tenantId);
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
}
