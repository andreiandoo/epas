<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorProduct;
use App\Models\VendorProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    // ── Categories ──

    public function categories(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $categories = $vendor->productCategories()
            ->where('festival_edition_id', $editionId)
            ->orderBy('sort_order')
            ->withCount('products')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request, int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $category = VendorProductCategory::create([
            'vendor_id'           => $vendor->id,
            'festival_edition_id' => $editionId,
            'name'                => $data['name'],
            'slug'                => Str::slug($data['name']),
            'sort_order'          => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, int $editionId, int $categoryId): JsonResponse
    {
        $vendor   = Auth::guard('vendor')->user();
        $category = VendorProductCategory::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->findOrFail($categoryId);

        $data = $request->validate([
            'name'       => 'string|max:100',
            'sort_order' => 'integer|min:0',
            'is_active'  => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json(['category' => $category]);
    }

    public function destroyCategory(int $editionId, int $categoryId): JsonResponse
    {
        $vendor   = Auth::guard('vendor')->user();
        $category = VendorProductCategory::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->findOrFail($categoryId);

        $category->delete();

        return response()->json(null, 204);
    }

    // ── Products ──

    public function products(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $products = $vendor->products()
            ->where('festival_edition_id', $editionId)
            ->with('category:id,name')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['products' => $products]);
    }

    public function storeProduct(Request $request, int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $data = $request->validate([
            'name'                       => 'required|string|max:200',
            'vendor_product_category_id' => 'nullable|integer|exists:vendor_product_categories,id',
            'description'                => 'nullable|string|max:1000',
            'price_cents'                => 'required|integer|min:0',
            'currency'                   => 'string|size:3',
            'image_url'                  => 'nullable|url|max:500',
            'variants'                   => 'nullable|array',
            'variants.*.name'            => 'required|string|max:100',
            'variants.*.price_cents'     => 'required|integer|min:0',
            'allergens'                  => 'nullable|array',
            'allergens.*'                => 'string|max:50',
            'tags'                       => 'nullable|array',
            'tags.*'                     => 'string|max:50',
            'sort_order'                 => 'integer|min:0',
        ]);

        $product = VendorProduct::create([
            'vendor_id'                  => $vendor->id,
            'festival_edition_id'        => $editionId,
            'vendor_product_category_id' => $data['vendor_product_category_id'] ?? null,
            'name'                       => $data['name'],
            'slug'                       => Str::slug($data['name']),
            'description'                => $data['description'] ?? null,
            'price_cents'                => $data['price_cents'],
            'currency'                   => $data['currency'] ?? 'RON',
            'image_url'                  => $data['image_url'] ?? null,
            'variants'                   => $data['variants'] ?? null,
            'allergens'                  => $data['allergens'] ?? null,
            'tags'                       => $data['tags'] ?? null,
            'sort_order'                 => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['product' => $product->load('category:id,name')], 201);
    }

    public function updateProduct(Request $request, int $editionId, int $productId): JsonResponse
    {
        $vendor  = Auth::guard('vendor')->user();
        $product = VendorProduct::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->findOrFail($productId);

        $data = $request->validate([
            'name'                       => 'string|max:200',
            'vendor_product_category_id' => 'nullable|integer|exists:vendor_product_categories,id',
            'description'                => 'nullable|string|max:1000',
            'price_cents'                => 'integer|min:0',
            'image_url'                  => 'nullable|url|max:500',
            'is_available'               => 'boolean',
            'variants'                   => 'nullable|array',
            'allergens'                  => 'nullable|array',
            'tags'                       => 'nullable|array',
            'sort_order'                 => 'integer|min:0',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $product->update($data);

        return response()->json(['product' => $product->load('category:id,name')]);
    }

    public function destroyProduct(int $editionId, int $productId): JsonResponse
    {
        $vendor  = Auth::guard('vendor')->user();
        $product = VendorProduct::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->findOrFail($productId);

        $product->delete();

        return response()->json(null, 204);
    }

    public function toggleAvailability(int $editionId, int $productId): JsonResponse
    {
        $vendor  = Auth::guard('vendor')->user();
        $product = VendorProduct::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->findOrFail($productId);

        $product->update(['is_available' => !$product->is_available]);

        return response()->json(['is_available' => $product->is_available]);
    }
}
