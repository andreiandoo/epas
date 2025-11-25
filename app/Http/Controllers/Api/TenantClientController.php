<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Tenant;
use App\Models\TenantPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantClientController extends Controller
{
    /**
     * Resolve tenant from request (hostname preferred, ID fallback)
     */
    private function resolveTenant(Request $request): ?array
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

            return [
                'tenant' => $domain->tenant,
                'domain_id' => $domain->id,
            ];
        }

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return null;
            }

            return [
                'tenant' => $tenant,
                'domain_id' => $request->query('domain'),
            ];
        }

        return null;
    }

    /**
     * Get tenant configuration
     * Supports both domain-based lookup (secure) and ID-based lookup (legacy)
     */
    public function config(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $domainId = $resolved['domain_id'];

        $settings = $tenant->settings ?? [];

        $logo = $settings['branding']['logo'] ?? null;
        $favicon = $settings['branding']['favicon'] ?? null;

        return response()->json([
            'theme' => [
                'primaryColor' => $settings['theme']['primary_color'] ?? '#3B82F6',
                'secondaryColor' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                'logo' => $logo ? Storage::disk('public')->url($logo) : null,
                'favicon' => $favicon ? Storage::disk('public')->url($favicon) : null,
                'fontFamily' => $settings['theme']['font_family'] ?? 'Inter',
            ],
            'site' => [
                'title' => $settings['site_title'] ?? $tenant->public_name ?? $tenant->name,
                'description' => $settings['site_description'] ?? '',
                'tagline' => $settings['site_tagline'] ?? '',
                'language' => $settings['site_language'] ?? 'en',
                'template' => $settings['site_template'] ?? 'default',
            ],
            'social' => [
                'facebook' => $settings['social']['facebook'] ?? null,
                'instagram' => $settings['social']['instagram'] ?? null,
                'twitter' => $settings['social']['twitter'] ?? null,
                'youtube' => $settings['social']['youtube'] ?? null,
                'tiktok' => $settings['social']['tiktok'] ?? null,
                'linkedin' => $settings['social']['linkedin'] ?? null,
            ],
            'pages' => [
                'terms_title' => $settings['legal']['terms_title'] ?? 'Terms & Conditions',
                'privacy_title' => $settings['legal']['privacy_title'] ?? 'Privacy Policy',
            ],
            'menus' => [
                'header' => $this->getMenuPages($tenant, 'header'),
                'footer' => $this->getMenuPages($tenant, 'footer'),
            ],
            'modules' => $this->getEnabledModules($tenant),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->public_name ?? $tenant->name,
                'locale' => $settings['site_language'] ?? 'en',
            ],
        ]);
    }

    /**
     * List events for tenant
     */
    public function events(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;
        $search = $request->query('search');
        $category = $request->query('category');
        $limit = $request->query('limit', 12);
        $offset = $request->query('offset', 0);
        $showAll = $request->query('show_all'); // Debug parameter

        $query = Event::where('tenant_id', $tenantId);

        // Check date columns exist before filtering
        // Use today's date (not time) for comparison to include events starting today
        if (!$showAll) {
            $today = now()->startOfDay();

            if (\Schema::hasColumn('events', 'end_date') && \Schema::hasColumn('events', 'start_date')) {
                $query->where(function ($q) use ($today) {
                    $q->where('end_date', '>=', $today)
                      ->orWhere(function ($q2) use ($today) {
                          $q2->whereNull('end_date')
                             ->where('start_date', '>=', $today);
                      });
                });
            } elseif (\Schema::hasColumn('events', 'start_date')) {
                $query->where('start_date', '>=', $today);
            } elseif (\Schema::hasColumn('events', 'event_date')) {
                $query->where('event_date', '>=', $today);
            }
        }

        // Check if cancelled - allow NULL or false
        if (\Schema::hasColumn('events', 'is_cancelled')) {
            $query->where(function ($q) {
                $q->where('is_cancelled', 0)
                  ->orWhereNull('is_cancelled');
            });
        }

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

        // Determine order column
        $orderColumn = 'created_at';
        if (\Schema::hasColumn('events', 'start_date')) {
            $orderColumn = 'start_date';
        } elseif (\Schema::hasColumn('events', 'event_date')) {
            $orderColumn = 'event_date';
        }

        $events = $query->with(['venue', 'eventType', 'artists'])
            ->orderBy($orderColumn, 'asc')
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
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;
        $limit = $request->query('limit', 6);
        $showAll = $request->query('show_all'); // Debug parameter

        $query = Event::where('tenant_id', $tenantId);

        // Check date columns exist before filtering
        // Use today's date (not time) for comparison to include events starting today
        if (!$showAll) {
            $today = now()->startOfDay();

            if (\Schema::hasColumn('events', 'end_date') && \Schema::hasColumn('events', 'start_date')) {
                $query->where(function ($q) use ($today) {
                    $q->where('end_date', '>=', $today)
                      ->orWhere(function ($q2) use ($today) {
                          $q2->whereNull('end_date')
                             ->where('start_date', '>=', $today);
                      });
                });
            } elseif (\Schema::hasColumn('events', 'start_date')) {
                $query->where('start_date', '>=', $today);
            } elseif (\Schema::hasColumn('events', 'event_date')) {
                $query->where('event_date', '>=', $today);
            }
        }

        // Check if cancelled - allow NULL or false
        if (\Schema::hasColumn('events', 'is_cancelled')) {
            $query->where(function ($q) {
                $q->where('is_cancelled', 0)
                  ->orWhereNull('is_cancelled');
            });
        }

        // Check if is_featured column exists, otherwise just return latest events
        if (\Schema::hasColumn('events', 'is_featured')) {
            $query->where('is_featured', true);
        }

        // Determine order column
        $orderColumn = 'created_at';
        if (\Schema::hasColumn('events', 'start_date')) {
            $orderColumn = 'start_date';
        } elseif (\Schema::hasColumn('events', 'event_date')) {
            $orderColumn = 'event_date';
        }

        $events = $query->with(['venue', 'eventType'])
            ->orderBy($orderColumn, 'asc')
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
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;

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
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;

        // Get event types that have events for this tenant
        $types = EventType::whereHas('events', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
            if (\Schema::hasColumn('events', 'is_active')) {
                $q->where('is_active', true);
            }
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
            'image' => $event->featured_image ? Storage::disk('public')->url($event->featured_image) : null,
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
                'image' => $artist->main_image ? Storage::disk('public')->url($artist->main_image) : null,
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
     * Get terms page content
     */
    public function terms(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $settings = $tenant->settings ?? [];

        $content = $settings['legal']['terms'] ?? '';

        if (empty($content)) {
            return response()->json(['error' => 'Terms page not found'], 404);
        }

        return response()->json([
            'data' => [
                'title' => $settings['legal']['terms_title'] ?? 'Terms & Conditions',
                'content' => $content,
            ],
        ]);
    }

    /**
     * Get privacy page content
     */
    public function privacy(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $settings = $tenant->settings ?? [];

        $content = $settings['legal']['privacy'] ?? '';

        if (empty($content)) {
            return response()->json(['error' => 'Privacy page not found'], 404);
        }

        return response()->json([
            'data' => [
                'title' => $settings['legal']['privacy_title'] ?? 'Privacy Policy',
                'content' => $content,
            ],
        ]);
    }

    /**
     * Get single page by slug
     */
    public function page(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $locale = $request->query('locale', 'en');

        $page = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        return response()->json([
            'data' => [
                'title' => $page->getTranslation('title', $locale) ?? $page->getTranslation('title', 'en'),
                'content' => $page->getTranslation('content', $locale) ?? $page->getTranslation('content', 'en'),
                'slug' => $page->slug,
            ],
        ]);
    }

    /**
     * Get menu pages for a location
     */
    private function getMenuPages(Tenant $tenant, string $location): array
    {
        $locale = app()->getLocale();

        return TenantPage::where('tenant_id', $tenant->id)
            ->where('menu_location', $location)
            ->where('is_published', true)
            ->orderBy('menu_order')
            ->get()
            ->map(fn ($page) => [
                'title' => $page->getTranslation('title', $locale) ?? $page->getTranslation('title', 'en'),
                'slug' => $page->slug,
                'url' => '/page/' . $page->slug,
            ])
            ->toArray();
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
