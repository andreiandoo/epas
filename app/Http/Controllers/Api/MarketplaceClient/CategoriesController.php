<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceEventCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoriesController extends BaseController
{
    /**
     * Get all event categories for the marketplace client
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = MarketplaceEventCategory::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        // Optional: only featured
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $categories = $query->get();

        $language = $client->language ?? 'ro';

        $data = $categories->map(function ($category) use ($language) {
            return [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
                'description' => $category->getTranslation('description', $language),
                'icon' => $category->icon,
                'icon_emoji' => $category->icon_emoji,
                'image' => $category->image_url,
                'color' => $category->color,
                'event_count' => $category->event_count ?? 0,
                'is_featured' => $category->is_featured,
                'children' => $category->children()
                    ->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function ($child) use ($language) {
                        return [
                            'id' => $child->id,
                            'name' => $child->getTranslation('name', $language),
                            'slug' => $child->slug,
                            'description' => $child->getTranslation('description', $language),
                            'icon' => $child->icon,
                            'icon_emoji' => $child->icon_emoji,
                            'event_count' => $child->event_count ?? 0,
                        ];
                    }),
            ];
        });

        return $this->success([
            'categories' => $data,
        ]);
    }

    /**
     * Get single category by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $category = MarketplaceEventCategory::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return $this->error('Category not found', 404);
        }

        $language = $client->language ?? 'ro';

        return $this->success([
            'category' => [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
                'description' => $category->getTranslation('description', $language),
                'icon' => $category->icon,
                'icon_emoji' => $category->icon_emoji,
                'image' => $category->image_url,
                'color' => $category->color,
                'meta_title' => $category->getTranslation('meta_title', $language),
                'meta_description' => $category->getTranslation('meta_description', $language),
                'event_count' => $category->event_count ?? 0,
                'is_featured' => $category->is_featured,
                'parent' => $category->parent ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->getTranslation('name', $language),
                    'slug' => $category->parent->slug,
                ] : null,
                'children' => $category->children()
                    ->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function ($child) use ($language) {
                        return [
                            'id' => $child->id,
                            'name' => $child->getTranslation('name', $language),
                            'slug' => $child->slug,
                            'event_count' => $child->event_count ?? 0,
                        ];
                    }),
            ],
        ]);
    }
}
