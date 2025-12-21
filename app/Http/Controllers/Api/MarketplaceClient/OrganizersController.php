<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizersController extends Controller
{
    /**
     * List organizers for the marketplace.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $query = MarketplaceOrganizer::where('tenant_id', $tenant->id)
            ->active()
            ->withCount(['events' => function ($q) {
                $q->upcoming();
            }]);

        // Search by name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by verified status
        if ($request->filled('verified')) {
            $query->where('is_verified', $request->boolean('verified'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'name');
        $query->when($sortBy === 'name', fn ($q) => $q->orderBy('name'));
        $query->when($sortBy === 'events', fn ($q) => $q->orderByDesc('total_events'));
        $query->when($sortBy === 'newest', fn ($q) => $q->orderByDesc('created_at'));

        // Pagination
        $perPage = min($request->get('per_page', 12), 50);
        $organizers = $query->paginate($perPage);

        return response()->json([
            'organizers' => $organizers->map(fn ($org) => $this->formatOrganizer($org)),
            'pagination' => [
                'current_page' => $organizers->currentPage(),
                'last_page' => $organizers->lastPage(),
                'per_page' => $organizers->perPage(),
                'total' => $organizers->total(),
            ],
        ]);
    }

    /**
     * Get featured organizers for the marketplace homepage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function featured(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        // Get organizers with the most upcoming events
        $organizers = MarketplaceOrganizer::where('tenant_id', $tenant->id)
            ->active()
            ->verified()
            ->withCount(['events' => function ($q) {
                $q->upcoming();
            }])
            ->orderByDesc('events_count')
            ->limit(6)
            ->get();

        return response()->json([
            'organizers' => $organizers->map(fn ($org) => $this->formatOrganizer($org)),
        ]);
    }

    /**
     * Get a single organizer by slug.
     *
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $organizer = MarketplaceOrganizer::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->active()
            ->first();

        if (!$organizer) {
            return response()->json(['error' => 'Organizer not found'], 404);
        }

        return response()->json([
            'organizer' => $this->formatOrganizerDetailed($organizer),
        ]);
    }

    /**
     * Get events for a specific organizer.
     *
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function events(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $organizer = MarketplaceOrganizer::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->active()
            ->first();

        if (!$organizer) {
            return response()->json(['error' => 'Organizer not found'], 404);
        }

        $query = $organizer->events()
            ->with(['venue', 'eventTypes'])
            ->upcoming();

        // Filter by time
        $filter = $request->get('filter', 'upcoming');
        if ($filter === 'past') {
            $query = $organizer->events()
                ->with(['venue', 'eventTypes'])
                ->past()
                ->orderByDesc('event_date');
        }

        // Pagination
        $perPage = min($request->get('per_page', 12), 50);
        $events = $query->paginate($perPage);

        return response()->json([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
            ],
            'events' => $events->map(fn ($event) => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title' => $event->getTranslation('title', 'ro'),
                'poster_url' => $event->poster_url,
                'date' => $event->start_date?->format('Y-m-d'),
                'date_formatted' => $event->start_date?->format('M j, Y'),
                'venue' => $event->venue ? [
                    'name' => $event->venue->name,
                    'city' => $event->venue->city,
                ] : null,
                'is_sold_out' => $event->is_sold_out,
            ]),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Format organizer for list view.
     */
    protected function formatOrganizer(MarketplaceOrganizer $organizer): array
    {
        return [
            'id' => $organizer->id,
            'slug' => $organizer->slug,
            'name' => $organizer->name,
            'description' => \Illuminate\Support\Str::limit($organizer->description, 150),
            'logo' => $organizer->logo,
            'cover_image' => $organizer->cover_image,
            'is_verified' => $organizer->is_verified,
            'upcoming_events_count' => $organizer->events_count ?? 0,
            'total_events' => $organizer->total_events,
        ];
    }

    /**
     * Format organizer for detail view.
     */
    protected function formatOrganizerDetailed(MarketplaceOrganizer $organizer): array
    {
        return [
            'id' => $organizer->id,
            'slug' => $organizer->slug,
            'name' => $organizer->name,
            'description' => $organizer->description,
            'logo' => $organizer->logo,
            'cover_image' => $organizer->cover_image,
            'is_verified' => $organizer->is_verified,
            'website_url' => $organizer->website_url,
            'social' => [
                'facebook' => $organizer->facebook_url,
                'instagram' => $organizer->instagram_url,
            ],
            'location' => [
                'city' => $organizer->city,
                'country' => $organizer->country,
            ],
            'stats' => [
                'total_events' => $organizer->total_events,
                'upcoming_events' => $organizer->events()->upcoming()->count(),
            ],
            'created_at' => $organizer->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Resolve the marketplace tenant from the request.
     */
    protected function resolveMarketplace(Request $request): ?Tenant
    {
        $marketplaceId = $request->header('X-Marketplace-Id');
        if ($marketplaceId) {
            return Tenant::find($marketplaceId);
        }

        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        return Tenant::where('slug', $subdomain)
            ->orWhere('custom_domain', $host)
            ->first();
    }
}
