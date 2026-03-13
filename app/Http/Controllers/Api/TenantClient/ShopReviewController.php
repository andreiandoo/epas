<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopReview;
use App\Models\Shop\ShopOrderItem;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopReviewController extends Controller
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

    private function getShopConfig(Tenant $tenant): array
    {
        $config = $tenant->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration;

        if (is_string($config)) {
            $config = json_decode($config, true) ?? [];
        }

        return $config ?? [];
    }

    /**
     * Get reviews for a product
     */
    public function index(Request $request, string $productSlug): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $product = ShopProduct::where('tenant_id', $tenant->id)
            ->where('slug', $productSlug)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $page = $request->query('page', 1);
        $perPage = min($request->query('per_page', 10), 50);
        $sort = $request->query('sort', 'newest');

        $query = ShopReview::where('product_id', $product->id)
            ->where('status', 'approved');

        $query = match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'highest' => $query->orderBy('rating', 'desc'),
            'lowest' => $query->orderBy('rating', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $total = $query->count();
        $reviews = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Calculate rating distribution
        $ratingDistribution = ShopReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $formattedReviews = $reviews->map(fn($r) => $this->formatReview($r));

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $formattedReviews,
                'summary' => [
                    'average_rating' => $product->average_rating,
                    'review_count' => $product->review_count,
                    'rating_distribution' => [
                        5 => $ratingDistribution[5] ?? 0,
                        4 => $ratingDistribution[4] ?? 0,
                        3 => $ratingDistribution[3] ?? 0,
                        2 => $ratingDistribution[2] ?? 0,
                        1 => $ratingDistribution[1] ?? 0,
                    ],
                ],
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => (int) $page,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * Submit a review
     */
    public function store(Request $request, string $productSlug): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $config = $this->getShopConfig($tenant);

        if (!($config['reviews_enabled'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews are disabled',
            ], 403);
        }

        $product = ShopProduct::where('tenant_id', $tenant->id)
            ->where('slug', $productSlug)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        if (!$product->reviews_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews are disabled for this product',
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:200',
            'content' => 'required|string|max:5000',
            'reviewer_name' => 'required|string|max:100',
            'reviewer_email' => 'required|email|max:255',
        ]);

        $customerId = $request->user()?->customer_id;

        // Check if require purchase
        $verifiedPurchase = false;
        if ($config['reviews_require_purchase'] ?? true) {
            $hasPurchased = ShopOrderItem::whereHas('order', function ($q) use ($tenant, $customerId, $validated) {
                $q->where('tenant_id', $tenant->id)
                    ->where('payment_status', 'paid')
                    ->where(function ($q2) use ($customerId, $validated) {
                        $q2->where('customer_id', $customerId)
                            ->orWhere('customer_email', $validated['reviewer_email']);
                    });
            })->where('product_id', $product->id)->exists();

            if (!$hasPurchased) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must purchase this product before reviewing',
                ], 403);
            }

            $verifiedPurchase = true;
        }

        // Check for existing review
        $existingReview = ShopReview::where('product_id', $product->id)
            ->where('reviewer_email', $validated['reviewer_email'])
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product',
            ], 400);
        }

        // Determine initial status
        $requiresModeration = $config['reviews_moderation'] ?? true;

        $review = ShopReview::create([
            'id' => Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'customer_id' => $customerId,
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'reviewer_name' => $validated['reviewer_name'],
            'reviewer_email' => $validated['reviewer_email'],
            'verified_purchase' => $verifiedPurchase,
            'status' => $requiresModeration ? 'pending' : 'approved',
        ]);

        // Update product stats if auto-approved
        if (!$requiresModeration) {
            $product->updateReviewStats();
        }

        return response()->json([
            'success' => true,
            'message' => $requiresModeration
                ? 'Thank you! Your review will be published after moderation.'
                : 'Thank you for your review!',
            'data' => [
                'review' => $this->formatReview($review),
            ],
        ], 201);
    }

    private function formatReview(ShopReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'content' => $review->content,
            'reviewer_name' => $review->reviewer_name,
            'verified_purchase' => $review->verified_purchase,
            'admin_response' => $review->admin_response,
            'responded_at' => $review->responded_at?->toISOString(),
            'created_at' => $review->created_at->toISOString(),
        ];
    }
}
