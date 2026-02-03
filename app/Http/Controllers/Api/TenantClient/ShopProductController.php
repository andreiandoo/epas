<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopCategory;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopProductController extends Controller
{
    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();

            if (!$domain) {
                return null;
            }

            return $domain->tenant;
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
     * List products
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Shop is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
        $page = $request->query('page', 1);
        $perPage = min($request->query('per_page', 12), 50);
        $category = $request->query('category');
        $featured = $request->boolean('featured');
        $type = $request->query('type'); // physical, digital
        $sort = $request->query('sort', 'newest');
        $search = $request->query('search');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');

        $query = ShopProduct::where('tenant_id', $tenant->id)
            ->active()
            ->visible()
            ->with(['category', 'variants']);

        if ($category) {
            $query->whereHas('category', fn($q) => $q->where('slug', $category));
        }

        if ($featured) {
            $query->featured();
        }

        if ($type && in_array($type, ['physical', 'digital'])) {
            $query->where('type', $type);
        }

        // SECURITY FIX: Validate language to prevent SQL injection via JSON path
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $tenantLanguage)) {
            $tenantLanguage = 'en';
        }

        if ($search) {
            $query->where(function ($q) use ($search, $tenantLanguage) {
                $q->whereRaw("JSON_EXTRACT(title, ?) LIKE ?", ['$."' . $tenantLanguage . '"', "%{$search}%"])
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($minPrice) {
            $query->where('price_cents', '>=', (int)$minPrice * 100);
        }

        if ($maxPrice) {
            $query->where('price_cents', '<=', (int)$maxPrice * 100);
        }

        $query = match ($sort) {
            'price_asc' => $query->orderBy('price_cents', 'asc'),
            'price_desc' => $query->orderBy('price_cents', 'desc'),
            'name_asc' => $query->orderByRaw("JSON_EXTRACT(title, ?) ASC", ['$."' . $tenantLanguage . '"']),
            'name_desc' => $query->orderByRaw("JSON_EXTRACT(title, ?) DESC", ['$."' . $tenantLanguage . '"']),
            'rating' => $query->orderBy('average_rating', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $total = $query->count();
        $products = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $formattedProducts = $products->map(fn($p) => $this->formatProduct($p, $tenantLanguage));

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $formattedProducts,
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
     * Get single product by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Shop is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        $product = ShopProduct::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->active()
            ->visible()
            ->with(['category', 'variants.attributeValues.attribute', 'attributes.values', 'approvedReviews'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $relatedProducts = $product->getRelatedProducts()
            ->map(fn($p) => $this->formatProduct($p, $tenantLanguage));

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $this->formatProduct($product, $tenantLanguage, true),
                'related' => $relatedProducts,
            ],
        ]);
    }

    /**
     * List categories
     */
    public function categories(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Shop is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        $categories = ShopCategory::where('tenant_id', $tenant->id)
            ->where('is_visible', true)
            ->withCount(['products' => fn($q) => $q->active()->visible()])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) use ($tenantLanguage) {
                $name = is_array($category->name)
                    ? ($category->name[$tenantLanguage] ?? $category->name['en'] ?? array_values($category->name)[0] ?? '')
                    : $category->name;

                $description = is_array($category->description)
                    ? ($category->description[$tenantLanguage] ?? '')
                    : ($category->description ?? '');

                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $name,
                    'description' => $description,
                    'image_url' => $category->image_url,
                    'parent_id' => $category->parent_id,
                    'products_count' => $category->products_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Get featured products
     */
    public function featured(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Shop is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
        $limit = min($request->query('limit', 8), 20);

        $products = ShopProduct::where('tenant_id', $tenant->id)
            ->active()
            ->visible()
            ->featured()
            ->with(['category'])
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(fn($p) => $this->formatProduct($p, $tenantLanguage));

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products,
            ],
        ]);
    }

    private function formatProduct(ShopProduct $product, string $language, bool $detailed = false): array
    {
        $getTranslation = function ($field) use ($product, $language) {
            $value = $product->{$field};
            if (is_array($value)) {
                return $value[$language] ?? $value['en'] ?? array_values($value)[0] ?? '';
            }
            return $value ?? '';
        };

        $categoryName = null;
        if ($product->category) {
            $catName = $product->category->name;
            $categoryName = is_array($catName)
                ? ($catName[$language] ?? $catName['en'] ?? '')
                : $catName;
        }

        $data = [
            'id' => $product->id,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'title' => $getTranslation('title'),
            'short_description' => $getTranslation('short_description'),
            'type' => $product->type,
            'price' => $product->price,
            'price_cents' => $product->price_cents,
            'sale_price' => $product->sale_price,
            'sale_price_cents' => $product->sale_price_cents,
            'display_price' => $product->display_price,
            'currency' => $product->currency,
            'is_on_sale' => $product->isOnSale(),
            'discount_percentage' => $product->getDiscountPercentage(),
            'image_url' => $product->image_url,
            'category' => $categoryName,
            'category_slug' => $product->category?->slug,
            'is_in_stock' => $product->isInStock(),
            'stock_quantity' => $product->track_inventory ? $product->getTotalStock() : null,
            'is_featured' => $product->is_featured,
            'average_rating' => $product->average_rating,
            'review_count' => $product->review_count,
        ];

        if ($detailed) {
            $data['description'] = $getTranslation('description');
            $data['gallery'] = $product->gallery ?? [];
            $data['weight_grams'] = $product->weight_grams;
            $data['dimensions'] = $product->dimensions;
            $data['reviews_enabled'] = $product->reviews_enabled;

            // Tax info
            $data['tax_rate'] = $product->getEffectiveTaxRate();
            $data['tax_mode'] = $product->getEffectiveTaxMode();
            $data['price_with_tax'] = $product->getPriceWithTax() / 100;

            // Variants
            if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
                $data['variants'] = $product->variants->map(function ($variant) use ($language, $product) {
                    $attributes = $variant->attributeValues->map(function ($av) use ($language) {
                        $attrName = is_array($av->attribute->name)
                            ? ($av->attribute->name[$language] ?? $av->attribute->slug)
                            : $av->attribute->slug;
                        $valueName = is_array($av->value)
                            ? ($av->value[$language] ?? $av->slug)
                            : $av->slug;
                        return [
                            'attribute' => $attrName,
                            'attribute_slug' => $av->attribute->slug,
                            'value' => $valueName,
                            'value_slug' => $av->slug,
                            'color_code' => $av->color_code,
                        ];
                    });

                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'name' => $variant->name,
                        'price_cents' => $variant->price_cents ?? $product->price_cents,
                        'sale_price_cents' => $variant->sale_price_cents ?? $product->sale_price_cents,
                        'stock_quantity' => $variant->stock_quantity,
                        'is_in_stock' => !$product->track_inventory || ($variant->stock_quantity === null || $variant->stock_quantity > 0),
                        'image_url' => $variant->image_url,
                        'attributes' => $attributes,
                    ];
                });

                // Attributes for variant selection
                $data['attributes'] = $product->attributes->map(function ($attr) use ($language) {
                    $name = is_array($attr->name)
                        ? ($attr->name[$language] ?? $attr->slug)
                        : $attr->slug;
                    return [
                        'id' => $attr->id,
                        'slug' => $attr->slug,
                        'name' => $name,
                        'type' => $attr->type,
                        'values' => $attr->values->map(function ($v) use ($language) {
                            $val = is_array($v->value)
                                ? ($v->value[$language] ?? $v->slug)
                                : $v->slug;
                            return [
                                'id' => $v->id,
                                'slug' => $v->slug,
                                'value' => $val,
                                'color_code' => $v->color_code,
                            ];
                        }),
                    ];
                });
            }

            // Reviews summary
            if ($product->relationLoaded('approvedReviews') && $product->approvedReviews->isNotEmpty()) {
                $reviews = $product->approvedReviews->take(5)->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'title' => $review->title,
                        'content' => $review->content,
                        'reviewer_name' => $review->reviewer_name,
                        'verified_purchase' => $review->verified_purchase,
                        'created_at' => $review->created_at->toISOString(),
                        'admin_response' => $review->admin_response,
                    ];
                });
                $data['reviews'] = $reviews;
            }

            // SEO
            $data['seo'] = $product->seo ?? [];
        }

        return $data;
    }
}
