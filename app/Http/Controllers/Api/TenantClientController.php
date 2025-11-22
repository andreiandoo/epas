<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantClientController extends Controller
{
    /**
     * Get tenant configuration
     */
    public function config(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant');
        $domainId = $request->query('domain');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $settings = $tenant->settings ?? [];

        return response()->json([
            'theme' => [
                'primaryColor' => $settings['theme']['primary_color'] ?? '#3B82F6',
                'secondaryColor' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                'logo' => $settings['branding']['logo_url'] ?? null,
                'favicon' => $settings['branding']['favicon_url'] ?? null,
                'fontFamily' => $settings['theme']['font_family'] ?? 'Inter',
            ],
            'modules' => $this->getEnabledModules($tenant),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->public_name ?? $tenant->name,
                'locale' => $tenant->locale ?? 'en',
            ],
        ]);
    }

    /**
     * List events for tenant
     */
    public function events(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant');
        $search = $request->query('search');
        $category = $request->query('category');
        $limit = $request->query('limit', 12);
        $offset = $request->query('offset', 0);

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $query = Event::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->where('end_date', '>=', now())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('end_date')
                         ->where('start_date', '>=', now());
                  });
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->whereHas('eventType', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        $total = $query->count();
        $events = $query->with(['venue', 'eventType', 'artists'])
            ->orderBy('start_date', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $events->map(fn ($event) => $this->formatEvent($event)),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * Get featured events
     */
    public function featuredEvents(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant');
        $limit = $request->query('limit', 6);

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $events = Event::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_featured', true)
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->where('end_date', '>=', now())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('end_date')
                         ->where('start_date', '>=', now());
                  });
            })
            ->with(['venue', 'eventType'])
            ->orderBy('start_date', 'asc')
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $events->map(fn ($event) => $this->formatEvent($event)),
        ]);
    }

    /**
     * Get single event by slug
     */
    public function event(Request $request, string $slug): JsonResponse
    {
        $tenantId = $request->query('tenant');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        $event = Event::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->with(['venue', 'eventType', 'eventGenres', 'artists', 'ticketTypes'])
            ->first();

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        return response()->json([
            'data' => $this->formatEventDetail($event),
        ]);
    }

    /**
     * Get event categories (types)
     */
    public function categories(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID required'], 400);
        }

        // Get event types that have events for this tenant
        $types = EventType::whereHas('events', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
              ->where('is_active', true);
        })->get();

        return response()->json([
            'data' => $types->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->getTranslation('name', app()->getLocale()),
                'slug' => $type->slug,
                'icon' => $type->icon ?? 'calendar',
            ]),
        ]);
    }

    /**
     * Format event for list view
     */
    private function formatEvent(Event $event): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $event->id,
            'title' => $event->getTranslation('title', $locale),
            'slug' => $event->slug,
            'description' => $event->getTranslation('short_description', $locale) ?? substr(strip_tags($event->getTranslation('description', $locale) ?? ''), 0, 150),
            'image' => $event->featured_image ? asset('storage/' . $event->featured_image) : null,
            'start_date' => $event->start_date?->toIso8601String(),
            'end_date' => $event->end_date?->toIso8601String(),
            'venue' => $event->venue ? [
                'name' => $event->venue->getTranslation('name', $locale),
                'city' => $event->venue->city,
            ] : null,
            'category' => $event->eventType ? [
                'name' => $event->eventType->getTranslation('name', $locale),
                'slug' => $event->eventType->slug,
            ] : null,
            'price_from' => $event->ticketTypes->min('price'),
            'is_sold_out' => $event->is_sold_out ?? false,
        ];
    }

    /**
     * Format event for detail view
     */
    private function formatEventDetail(Event $event): array
    {
        $locale = app()->getLocale();
        $basic = $this->formatEvent($event);

        return array_merge($basic, [
            'content' => $event->getTranslation('content', $locale),
            'description' => $event->getTranslation('description', $locale),
            'gallery' => $event->gallery ?? [],
            'artists' => $event->artists->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'image' => $artist->main_image ? asset('storage/' . $artist->main_image) : null,
            ]),
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->getTranslation('name', $locale),
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'latitude' => $event->venue->latitude,
                'longitude' => $event->venue->longitude,
            ] : null,
            'ticket_types' => $event->ticketTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->getTranslation('name', $locale),
                'description' => $type->getTranslation('description', $locale),
                'price' => $type->price,
                'currency' => $type->currency ?? 'EUR',
                'available' => $type->available_quantity ?? 0,
                'max_per_order' => $type->max_per_order ?? 10,
            ]),
            'genres' => $event->eventGenres->map(fn ($genre) => [
                'name' => $genre->getTranslation('name', $locale),
                'slug' => $genre->slug,
            ]),
        ]);
    }

    /**
     * Get enabled modules for tenant
     */
    private function getEnabledModules(Tenant $tenant): array
    {
        $modules = ['core', 'events', 'auth', 'cart', 'checkout'];

        $microservices = $tenant->microservices()
            ->wherePivot('is_active', true)
            ->get();

        $moduleMap = [
            'seating' => 'seating',
            'affiliates' => 'affiliates',
            'insurance' => 'insurance',
            'whatsapp' => 'whatsapp',
            'promo-codes' => 'promo_codes',
        ];

        foreach ($microservices as $ms) {
            if (isset($moduleMap[$ms->slug])) {
                $modules[] = $moduleMap[$ms->slug];
            }
        }

        return array_unique($modules);
    }
}
