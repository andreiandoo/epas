<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\Shop\ShopStockAlert;
use App\Models\Shop\ShopProduct;
use App\Services\Shop\ShopInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopStockAlertController extends Controller
{
    public function __construct(
        protected ShopInventoryService $inventoryService
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

    /**
     * Subscribe to stock alert for a product
     */
    public function subscribe(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'variant_id' => 'nullable|string',
            'email' => 'required|email',
            'customer_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Verify product exists and is out of stock
        $product = ShopProduct::where('tenant_id', $tenant->id)
            ->where('id', $data['product_id'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // Check variant if specified
        $variant = null;
        if (!empty($data['variant_id'])) {
            $variant = $product->variants()->find($data['variant_id']);
            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variant not found',
                ], 404);
            }
        }

        // Check if product is actually out of stock
        $stockQuantity = $variant?->stock_quantity ?? $product->stock_quantity;
        if ($stockQuantity === null || $stockQuantity > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Product is currently in stock',
            ], 400);
        }

        $result = $this->inventoryService->createStockAlert(
            $tenant->id,
            $data['product_id'],
            $data['email'],
            $data['variant_id'] ?? null,
            $data['customer_id'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'You will be notified when this product is back in stock',
            'data' => [
                'alert_id' => $result['alert']->id,
            ],
        ]);
    }

    /**
     * Unsubscribe from stock alert
     */
    public function unsubscribe(Request $request, string $alertId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $alert = ShopStockAlert::where('tenant_id', $tenant->id)
            ->where('id', $alertId)
            ->first();

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found',
            ], 404);
        }

        // Verify email matches if provided
        $email = $request->input('email');
        if ($email && $alert->email !== $email) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->inventoryService->cancelStockAlert($alert);

        return response()->json([
            'success' => true,
            'message' => 'Stock alert cancelled',
        ]);
    }

    /**
     * Unsubscribe by email and product
     */
    public function unsubscribeByProduct(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'variant_id' => 'nullable|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $alert = ShopStockAlert::where('tenant_id', $tenant->id)
            ->where('product_id', $data['product_id'])
            ->where('variant_id', $data['variant_id'] ?? null)
            ->where('email', $data['email'])
            ->where('status', 'pending')
            ->first();

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found',
            ], 404);
        }

        $this->inventoryService->cancelStockAlert($alert);

        return response()->json([
            'success' => true,
            'message' => 'Stock alert cancelled',
        ]);
    }

    /**
     * Get customer's stock alerts
     */
    public function myAlerts(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $email = $request->input('email');

        if (!$customerId && !$email) {
            return response()->json([
                'success' => false,
                'message' => 'customer_id or email is required',
            ], 422);
        }

        $query = ShopStockAlert::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->with('product');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $query->where('email', $email);
        }

        $alerts = $query->get();
        $language = $request->query('lang', $tenant->language ?? 'en');

        $alertsData = $alerts->map(function ($alert) use ($language) {
            $product = $alert->product;

            return [
                'id' => $alert->id,
                'product_id' => $alert->product_id,
                'variant_id' => $alert->variant_id,
                'product' => $product ? [
                    'title' => $product->getTranslation('title', $language),
                    'slug' => $product->slug,
                    'image_url' => $product->image_url,
                ] : null,
                'email' => $alert->email,
                'created_at' => $alert->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'alerts' => $alertsData,
            ],
        ]);
    }

    /**
     * Check if user has an alert for a product
     */
    public function check(Request $request, string $productId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $email = $request->input('email');
        $customerId = $request->input('customer_id');
        $variantId = $request->input('variant_id');

        if (!$email && !$customerId) {
            return response()->json([
                'success' => true,
                'data' => ['has_alert' => false],
            ]);
        }

        $query = ShopStockAlert::where('tenant_id', $tenant->id)
            ->where('product_id', $productId)
            ->where('status', 'pending');

        if ($variantId) {
            $query->where('variant_id', $variantId);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $query->where('email', $email);
        }

        $exists = $query->exists();

        return response()->json([
            'success' => true,
            'data' => ['has_alert' => $exists],
        ]);
    }
}
