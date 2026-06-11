<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\Shop\ShopWishlist;
use App\Models\Shop\ShopWishlistItem;
use App\Models\Shop\ShopProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopWishlistController extends Controller
{
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

    private function getLanguage(Request $request, Tenant $tenant): string
    {
        return $request->query('lang', $tenant->language ?? 'en');
    }

    private function getOrCreateWishlist(Tenant $tenant, ?int $customerId, ?string $sessionId): ShopWishlist
    {
        if ($customerId) {
            $wishlist = ShopWishlist::where('tenant_id', $tenant->id)
                ->where('customer_id', $customerId)
                ->first();

            if ($wishlist) {
                return $wishlist;
            }
        }

        if ($sessionId) {
            $wishlist = ShopWishlist::where('tenant_id', $tenant->id)
                ->where('session_id', $sessionId)
                ->first();

            if ($wishlist) {
                if ($customerId && !$wishlist->customer_id) {
                    $wishlist->update(['customer_id' => $customerId]);
                }
                return $wishlist;
            }
        }

        return ShopWishlist::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Get wishlist items
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');
        $language = $this->getLanguage($request, $tenant);

        if (!$customerId && !$sessionId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'items' => [],
                    'item_count' => 0,
                ],
            ]);
        }

        $wishlist = $this->getOrCreateWishlist($tenant, $customerId, $sessionId);
        $wishlist->load(['items.product']);

        $items = $wishlist->items->map(function ($item) use ($language) {
            $product = $item->product;

            if (!$product || !$product->is_visible || $product->status !== 'active') {
                return null;
            }

            return [
                'id' => $item->id,
                'product_id' => $product->id,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->getTranslation('title', $language),
                    'slug' => $product->slug,
                    'image_url' => $product->image_url,
                    'price_cents' => $product->display_price_cents,
                    'original_price_cents' => $product->price_cents,
                    'is_on_sale' => $product->isOnSale(),
                    'in_stock' => $product->isInStock(),
                    'type' => $product->type,
                ],
                'added_at' => $item->created_at->toISOString(),
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'item_count' => $items->count(),
            ],
        ]);
    }

    /**
     * Add item to wishlist
     */
    public function addItem(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $productId = $request->input('product_id');
        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        if (!$productId) {
            return response()->json([
                'success' => false,
                'message' => 'Product ID is required',
            ], 422);
        }

        if (!$customerId && !$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Customer ID or session ID is required',
            ], 422);
        }

        $product = ShopProduct::where('tenant_id', $tenant->id)
            ->where('id', $productId)
            ->where('is_visible', true)
            ->where('status', 'active')
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $wishlist = $this->getOrCreateWishlist($tenant, $customerId, $sessionId);

        // Check if already in wishlist
        $existing = $wishlist->items()->where('product_id', $productId)->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Product already in wishlist',
                'data' => ['item_id' => $existing->id],
            ]);
        }

        $item = $wishlist->items()->create([
            'product_id' => $productId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist',
            'data' => ['item_id' => $item->id],
        ]);
    }

    /**
     * Remove item from wishlist
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        $query = ShopWishlistItem::whereHas('wishlist', function ($q) use ($tenant, $customerId, $sessionId) {
            $q->where('tenant_id', $tenant->id);
            if ($customerId) {
                $q->where('customer_id', $customerId);
            } elseif ($sessionId) {
                $q->where('session_id', $sessionId);
            }
        })->where('id', $itemId);

        $deleted = $query->delete();

        return response()->json([
            'success' => $deleted > 0,
            'message' => $deleted > 0 ? 'Item removed from wishlist' : 'Item not found',
        ]);
    }

    /**
     * Remove item by product ID
     */
    public function removeByProduct(Request $request, string $productId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        $query = ShopWishlistItem::whereHas('wishlist', function ($q) use ($tenant, $customerId, $sessionId) {
            $q->where('tenant_id', $tenant->id);
            if ($customerId) {
                $q->where('customer_id', $customerId);
            } elseif ($sessionId) {
                $q->where('session_id', $sessionId);
            }
        })->where('product_id', $productId);

        $deleted = $query->delete();

        return response()->json([
            'success' => $deleted > 0,
            'message' => $deleted > 0 ? 'Item removed from wishlist' : 'Item not found',
        ]);
    }

    /**
     * Check if product is in wishlist
     */
    public function check(Request $request, string $productId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        if (!$customerId && !$sessionId) {
            return response()->json([
                'success' => true,
                'data' => ['in_wishlist' => false],
            ]);
        }

        $exists = ShopWishlistItem::whereHas('wishlist', function ($q) use ($tenant, $customerId, $sessionId) {
            $q->where('tenant_id', $tenant->id);
            if ($customerId) {
                $q->where('customer_id', $customerId);
            } elseif ($sessionId) {
                $q->where('session_id', $sessionId);
            }
        })->where('product_id', $productId)->exists();

        return response()->json([
            'success' => true,
            'data' => ['in_wishlist' => $exists],
        ]);
    }

    /**
     * Clear entire wishlist
     */
    public function clear(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        $wishlist = ShopWishlist::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->first();

        if ($wishlist) {
            $wishlist->items()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Wishlist cleared',
        ]);
    }

    /**
     * Merge guest wishlist to customer
     */
    public function merge(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $customerId = $request->input('customer_id');
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-Id');

        if (!$customerId || !$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Both customer_id and session_id are required for merge',
            ], 422);
        }

        $guestWishlist = ShopWishlist::where('tenant_id', $tenant->id)
            ->where('session_id', $sessionId)
            ->whereNull('customer_id')
            ->first();

        if (!$guestWishlist) {
            return response()->json([
                'success' => true,
                'message' => 'No guest wishlist to merge',
            ]);
        }

        $customerWishlist = $this->getOrCreateWishlist($tenant, $customerId, null);

        // Merge items
        foreach ($guestWishlist->items as $item) {
            $exists = $customerWishlist->items()
                ->where('product_id', $item->product_id)
                ->exists();

            if (!$exists) {
                $customerWishlist->items()->create([
                    'product_id' => $item->product_id,
                ]);
            }
        }

        // Delete guest wishlist
        $guestWishlist->items()->delete();
        $guestWishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist merged successfully',
        ]);
    }
}
