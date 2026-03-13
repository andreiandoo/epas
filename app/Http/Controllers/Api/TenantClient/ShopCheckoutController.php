<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\Shop\ShopCart;
use App\Services\Shop\ShopCheckoutService;
use App\Services\Shop\ShopCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopCheckoutController extends Controller
{
    public function __construct(
        protected ShopCheckoutService $checkoutService,
        protected ShopCartService $cartService
    ) {}

    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();
            return $domain?->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function hasShopMicroservice(Tenant $tenant): bool
    {
        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function getCart(Request $request, Tenant $tenant): ?ShopCart
    {
        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        return $this->cartService->getCart($tenant->id, $customerId, $sessionId);
    }

    /**
     * Initialize checkout session
     */
    public function initialize(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $cart = $this->getCart($request, $tenant);

        if (!$cart || $cart->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Cart is empty'], 400);
        }

        $checkoutData = $this->checkoutService->prepareCheckoutSession($cart);

        return response()->json([
            'success' => true,
            'data' => $checkoutData,
        ]);
    }

    /**
     * Get available shipping methods for address
     */
    public function shippingMethods(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $cart = $this->getCart($request, $tenant);

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'country' => 'required|string|size:2',
            'region' => 'nullable|string',
            'city' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'has_bundle_physical' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $address = $validator->validated();
        $hasBundlePhysical = $request->boolean('has_bundle_physical', false);
        $methods = $this->checkoutService->getAvailableShippingMethods($cart, $address, $hasBundlePhysical);

        return response()->json([
            'success' => true,
            'data' => [
                'shipping_methods' => $methods,
            ],
        ]);
    }

    /**
     * Validate checkout data before placing order
     */
    public function validate(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $cart = $this->getCart($request, $tenant);

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        $validation = $this->checkoutService->validateCheckout($cart, $request->all());

        return response()->json([
            'success' => $validation['valid'],
            'errors' => $validation['errors'] ?? [],
            'warnings' => $validation['warnings'] ?? [],
        ]);
    }

    /**
     * Calculate totals with shipping method
     */
    public function calculateTotals(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $cart = $this->getCart($request, $tenant);

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        $shippingCents = 0;
        if ($request->input('shipping_method_id')) {
            $shippingCents = $this->checkoutService->calculateShippingCost(
                $cart,
                $request->input('shipping_method_id')
            ) ?? 0;
        }

        $giftCardCents = 0;
        if ($request->input('gift_card_code')) {
            $giftCardValidation = $this->cartService->validateGiftCard($cart, $request->input('gift_card_code'));
            if ($giftCardValidation['valid']) {
                $giftCardCents = $giftCardValidation['gift_card']['balance_cents'];
            }
        }

        $totals = $this->cartService->calculateTotals($cart, [
            'shipping_cents' => $shippingCents,
            'gift_card_cents' => $giftCardCents,
        ]);

        return response()->json([
            'success' => true,
            'data' => $totals,
        ]);
    }

    /**
     * Create order from cart
     */
    public function createOrder(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $cart = $this->getCart($request, $tenant);

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_email' => 'required|email',
            'customer_phone' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'shipping_address' => 'nullable|array',
            'shipping_address.name' => 'required_with:shipping_address|string',
            'shipping_address.line1' => 'required_with:shipping_address|string',
            'shipping_address.line2' => 'nullable|string',
            'shipping_address.city' => 'required_with:shipping_address|string',
            'shipping_address.region' => 'nullable|string',
            'shipping_address.postal_code' => 'nullable|string',
            'shipping_address.country' => 'required_with:shipping_address|string|size:2',
            'billing_address' => 'nullable|array',
            'same_as_shipping' => 'nullable|boolean',
            'shipping_method_id' => 'nullable|string',
            'gift_card_code' => 'nullable|string',
            'notes' => 'nullable|string',
            'event_id' => 'nullable|integer',
            'ticket_order_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->checkoutService->createOrder($cart, $validator->validated());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        $order = $result['order'];

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'requires_payment' => $order->total_cents > 0,
            ],
        ]);
    }

    /**
     * Mark order as paid (webhook callback or manual)
     */
    public function confirmPayment(Request $request, string $orderNumber): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $order = \App\Models\Shop\ShopOrder::where('tenant_id', $tenant->id)
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Order already paid',
                'data' => ['order_number' => $order->order_number],
            ]);
        }

        $paymentData = [
            'method' => $request->input('payment_method'),
            'transaction_id' => $request->input('transaction_id'),
        ];

        $order = $this->checkoutService->markOrderPaid($order, $paymentData);

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }
}
