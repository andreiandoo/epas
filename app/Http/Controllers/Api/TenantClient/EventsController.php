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

        $events = $query->with(['venue', 'eventTypes', 'eventGenres', 'artists', 'tags', 'ticketTypes'])
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

                        // Status Flags
                        'is_sold_out' => $event->is_sold_out ?? false,
                        'door_sales_only' => $event->door_sales_only ?? false,
                        'is_cancelled' => $event->is_cancelled ?? false,
                        'cancel_reason' => $event->cancel_reason,
                        'is_postponed' => $event->is_postponed ?? false,
                        'postponed_date' => $event->postponed_date?->toIso8601String(),
                        'postponed_start_time' => $event->postponed_start_time,
                        'postponed_door_time' => $event->postponed_door_time,
                        'postponed_end_time' => $event->postponed_end_time,
                        'postponed_reason' => $event->postponed_reason,
                        'is_promoted' => $event->is_promoted ?? false,
                        'promoted_until' => $event->promoted_until?->toIso8601String(),

                        // Schedule
                        'duration_mode' => $event->duration_mode,
                        'start_date' => $event->duration_mode === 'single_day' && $event->start_date && $event->start_time
                            ? \Carbon\Carbon::parse($event->start_date->format('Y-m-d') . ' ' . $event->start_time)->toIso8601String()
                            : $event->start_date?->toIso8601String(),
                        'end_date' => $event->duration_mode === 'single_day' && $event->event_date && $event->end_time
                            ? \Carbon\Carbon::parse($event->event_date->format('Y-m-d') . ' ' . $event->end_time)->toIso8601String()
                            : ($event->duration_mode === 'range' && $event->end_date && $event->range_end_time
                                ? \Carbon\Carbon::parse($event->end_date->format('Y-m-d') . ' ' . $event->range_end_time)->toIso8601String()
                                : $event->end_date?->toIso8601String()),
                        'start_time' => $event->start_time,
                        'door_time' => $event->door_time,
                        'end_time' => $event->end_time,

                        // Location & Links
                        'address' => $event->address,
                        'website_url' => $event->website_url,
                        'facebook_url' => $event->facebook_url,
                        'event_website_url' => $event->event_website_url,
                        'venue' => $event->venue ? [
                            'id' => $event->venue->id,
                            'name' => $event->venue->getTranslation('name', $locale),
                            'city' => $event->venue->city,
                        ] : null,

                        // Media
                        'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                        'hero_image_url' => $event->hero_image_url ? Storage::disk('public')->url($event->hero_image_url) : null,

                        // Content
                        'short_description' => $event->getTranslation('short_description', $locale),
                        'description' => $event->getTranslation('description', $locale),
                        'ticket_terms' => $event->getTranslation('ticket_terms', $locale),

                        // Taxonomies & Relations
                        'event_types' => $event->eventTypes->map(fn ($type) => [
                            'id' => $type->id,
                            'name' => $type->getTranslation('name', $locale),
                            'slug' => $type->slug,
                        ]),
                        'event_genres' => $event->eventGenres->map(fn ($genre) => [
                            'id' => $genre->id,
                            'name' => $genre->getTranslation('name', $locale),
                            'slug' => $genre->slug,
                        ]),
                        'artists' => $event->artists->map(fn ($artist) => [
                            'id' => $artist->id,
                            'name' => $artist->name,
                            'image' => $artist->main_image ? Storage::disk('public')->url($artist->main_image) : null,
                        ]),
                        'tags' => $event->tags->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ]),

                        // Tickets
                        'ticket_types' => $event->ticketTypes->map(fn ($type) => [
                            'id' => $type->id,
                            'name' => $type->name,
                            'description' => $type->description ?? '',
                            'sku' => $type->sku,
                            'price' => $type->price_max,
                            'sale_price' => $type->price ?? null,
                            'discount_percent' => $type->price && $type->price_max
                                ? round((1 - ($type->price / $type->price_max)) * 100, 2)
                                : null,
                            'currency' => $type->currency ?? 'EUR',
                            'available' => $type->available_quantity,
                            'capacity' => $type->quota_total,
                            'status' => $type->status,
                            'sales_start_at' => $type->sales_start_at?->toIso8601String(),
                            'sales_end_at' => $type->sales_end_at?->toIso8601String(),
                            'bulk_discounts' => $type->bulk_discounts ?? [],
                        ]),
                        'price_from' => $event->ticketTypes->min('price_max'),
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
                'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
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
                    'name' => $type->name,
                    'description' => $type->description ?? '',
                    'price' => $type->price_max,
                    'currency' => $type->currency ?? 'EUR',
                    'available' => $type->available_quantity,
                    'status' => $type->status,
                ]),
                'price_from' => $event->ticketTypes->min('price_max'),
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
