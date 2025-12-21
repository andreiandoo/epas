<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    /**
     * Bootstrap the marketplace website with initial data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json([
                'error' => 'Marketplace not found',
            ], 404);
        }

        if (!$tenant->isMarketplace()) {
            return response()->json([
                'error' => 'This tenant is not a marketplace',
            ], 403);
        }

        return response()->json([
            'marketplace' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo_url,
                'favicon' => $tenant->favicon_url,
                'currency' => $tenant->currency ?? 'RON',
                'locale' => $tenant->locale ?? 'ro',
                'timezone' => $tenant->timezone ?? 'Europe/Bucharest',
                'settings' => [
                    'primary_color' => $tenant->settings['primary_color'] ?? '#10b981',
                    'secondary_color' => $tenant->settings['secondary_color'] ?? '#3b82f6',
                    'hero_title' => $tenant->settings['hero_title'] ?? null,
                    'hero_subtitle' => $tenant->settings['hero_subtitle'] ?? null,
                    'hero_image' => $tenant->settings['hero_image'] ?? null,
                ],
                'features' => [
                    'organizer_registration' => $tenant->marketplace_settings['allow_registration'] ?? true,
                    'show_organizers_page' => $tenant->marketplace_settings['show_organizers_page'] ?? true,
                    'show_categories' => $tenant->marketplace_settings['show_categories'] ?? true,
                ],
                'social' => [
                    'facebook' => $tenant->facebook_url ?? null,
                    'instagram' => $tenant->instagram_url ?? null,
                    'twitter' => $tenant->twitter_url ?? null,
                ],
            ],
            'stats' => [
                'organizers_count' => $tenant->organizers()->active()->count(),
                'events_count' => $tenant->events()->upcoming()->count(),
            ],
        ]);
    }

    /**
     * Get marketplace categories/event types.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function categories(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $categories = $tenant->eventTypes()
            ->withCount(['events' => function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id)
                    ->upcoming();
            }])
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'icon' => $type->icon,
                'events_count' => $type->events_count,
            ]);

        return response()->json(['categories' => $categories]);
    }

    /**
     * Resolve the marketplace tenant from the request.
     */
    protected function resolveMarketplace(Request $request): ?Tenant
    {
        // Try to resolve from header
        $marketplaceId = $request->header('X-Marketplace-Id');
        if ($marketplaceId) {
            return Tenant::find($marketplaceId);
        }

        // Try to resolve from subdomain
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        return Tenant::where('slug', $subdomain)
            ->orWhere('custom_domain', $host)
            ->first();
    }
}
