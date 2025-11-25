<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventsController extends Controller
{
    /**
     * Resolve tenant from request (hostname preferred, ID fallback)
     */
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

    /**
     * List public events for the tenant
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

        $search = $request->query('search');
        $category = $request->query('category');
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 12);
        $showAll = $request->query('show_all'); // Debug: show past events

        $query = Event::where('tenant_id', $tenant->id);

        // Date filtering (skip if show_all=1)
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
            }
        }

        // Cancelled check
        if (\Schema::hasColumn('events', 'is_cancelled')) {
            $query->where(function ($q) {
                $q->where('is_cancelled', 0)
                  ->orWhereNull('is_cancelled');
            });
        }

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title->en', 'like', "%{$search}%")
                  ->orWhere('title->ro', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($category) {
            $query->whereHas('eventTypes', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        $total = $query->count();

        // Order by start_date
        $orderColumn = 'created_at';
        if (\Schema::hasColumn('events', 'start_date')) {
            $orderColumn = 'start_date';
        }

        $events = $query->with(['venue', 'eventTypes', 'artists', 'ticketTypes'])
            ->orderBy($orderColumn, 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $locale = $request->query('locale', 'en');

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events->map(function ($event) use ($locale) {
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
                        'category' => $event->eventTypes->first() ? [
                            'name' => $event->eventTypes->first()->getTranslation('name', $locale),
                            'slug' => $event->eventTypes->first()->slug,
                        ] : null,
                        'price_from' => $event->ticketTypes->min('price'),
                        'is_sold_out' => $event->is_sold_out ?? false,
                    ];
                }),
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            ],
        ]);
    }

    /**
     * Get single event details
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

        $event = Event::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->with(['venue', 'eventTypes', 'eventGenres', 'artists', 'ticketTypes'])
            ->first();

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $locale = $request->query('locale', 'en');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $event->id,
                'title' => $event->getTranslation('title', $locale),
                'slug' => $event->slug,
                'description' => $event->getTranslation('description', $locale),
                'short_description' => $event->getTranslation('short_description', $locale),
                'content' => $event->getTranslation('content', $locale),
                'image' => $event->featured_image ? Storage::disk('public')->url($event->featured_image) : null,
                'gallery' => $event->gallery ?? [],
                'start_date' => $event->start_date?->toIso8601String(),
                'end_date' => $event->end_date?->toIso8601String(),
                'venue' => $event->venue ? [
                    'id' => $event->venue->id,
                    'name' => $event->venue->getTranslation('name', $locale),
                    'address' => $event->venue->address,
                    'city' => $event->venue->city,
                    'latitude' => $event->venue->latitude,
                    'longitude' => $event->venue->longitude,
                ] : null,
                'category' => $event->eventTypes->first() ? [
                    'name' => $event->eventTypes->first()->getTranslation('name', $locale),
                    'slug' => $event->eventTypes->first()->slug,
                ] : null,
                'genres' => $event->eventGenres->map(fn ($genre) => [
                    'name' => $genre->getTranslation('name', $locale),
                    'slug' => $genre->slug,
                ]),
                'artists' => $event->artists->map(fn ($artist) => [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'image' => $artist->main_image ? Storage::disk('public')->url($artist->main_image) : null,
                ]),
                'ticket_types' => $event->ticketTypes->map(fn ($type) => [
                    'id' => $type->id,
                    'name' => $type->getTranslation('name', $locale),
                    'description' => $type->getTranslation('description', $locale),
                    'price' => $type->price,
                    'currency' => $type->currency ?? 'EUR',
                    'available' => $type->available_quantity ?? 0,
                    'max_per_order' => $type->max_per_order ?? 10,
                ]),
                'price_from' => $event->ticketTypes->min('price'),
                'is_sold_out' => $event->is_sold_out ?? false,
            ],
        ]);
    }

    /**
     * Get event ticket types
     */
    public function tickets(Request $request, string $slug): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get ticket types for the event
        $tickets = [];

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Get event seating layout (if applicable)
     */
    public function seating(Request $request, string $slug): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get seating layout
        $seating = null;

        return response()->json([
            'success' => true,
            'data' => $seating,
        ]);
    }
}
