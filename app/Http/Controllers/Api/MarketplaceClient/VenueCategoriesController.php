<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceVenueCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VenueCategoriesController extends BaseController
{
    /**
     * Get all venue categories for the marketplace client
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = MarketplaceVenueCategory::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
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
                'color' => $category->color,
                'image' => $category->image_url,
                'venues_count' => $category->venues_count,
                'is_featured' => $category->is_featured,
                'sort_order' => $category->sort_order ?? 0,
            ];
        });

        return $this->success([
            'categories' => $data,
        ]);
    }

    /**
     * Get single venue category by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $category = MarketplaceVenueCategory::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return $this->error('Venue category not found', 404);
        }

        $language = $client->language ?? 'ro';

        // Get venues in this category
        $venues = $category->venues()
            ->orderBy('pivot_sort_order')
            ->get()
            ->map(function ($venue) use ($language) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', $language),
                    'slug' => $venue->slug,
                    'city' => $venue->city,
                    'address' => $venue->address,
                    'image' => $venue->image_url,
                    'capacity' => $venue->capacity,
                    'events_count' => $venue->events()->where('event_date', '>=', now()->toDateString())->count(),
                ];
            });

        return $this->success([
            'category' => [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
                'description' => $category->getTranslation('description', $language),
                'icon' => $category->icon,
                'color' => $category->color,
                'image' => $category->image_url,
                'venues_count' => $category->venues_count,
                'is_featured' => $category->is_featured,
            ],
            'venues' => $venues,
        ]);
    }
}
