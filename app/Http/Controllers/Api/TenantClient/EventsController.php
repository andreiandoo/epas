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

        // Use upcoming scope for filtering (excludes past events and cancelled)
        if (!$showAll) {
            $query->upcoming();
        } else {
            // Even with show_all, exclude cancelled
            $query->where(function ($q) {
                $q->where('is_cancelled', false)
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
                        'postponed_date' => $event->postponed_date && $event->postponed_start_time
                            ? \Carbon\Carbon::parse($event->postponed_date->format('Y-m-d') . ' ' . $event->postponed_start_time)->toIso8601String()
                            : $event->postponed_date?->toIso8601String(),
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

                        // Tickets - exclude invitations (meta->is_invitation = true)
                        'ticket_types' => $event->ticketTypes
                            ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                            ->map(fn ($type) => [
                                'id' => $type->id,
                                'name' => $type->name,
                                'description' => $type->description ?? '',
                                'sku' => $type->sku,
                                'price' => $type->price_max,
                                'sale_price' => $type->price ?? null,
                                'discount_percent' => $type->price && $type->price_max
                                    ? round((1 - ($type->price / $type->price_max)) * 100, 2)
                                    : null,
                                'currency' => $type->currency ?? 'RON',
                                'available' => $type->available_quantity,
                                'capacity' => $type->quota_total,
                                'status' => $type->status,
                                'sales_start_at' => $type->sales_start_at?->toIso8601String(),
                                'sales_end_at' => $type->sales_end_at?->toIso8601String(),
                                'bulk_discounts' => $type->bulk_discounts ?? [],
                            ])->values(),
                        'price_from' => $event->ticketTypes
                            ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                            ->min('price_max'),
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
            ->with(['venue', 'eventTypes', 'eventGenres', 'artists', 'ticketTypes', 'tags'])
            ->first();

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $locale = $request->query('locale', 'en');

        // Commission info
        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $event->id,
                'title' => $event->getTranslation('title', $locale),
                'slug' => $event->slug,
                'is_sold_out' => $event->is_sold_out ?? false,
                'door_sales_only' => $event->door_sales_only ?? false,
                'is_cancelled' => $event->is_cancelled ?? false,
                'cancel_reason' => $event->cancel_reason,
                'is_postponed' => $event->is_postponed ?? false,
                'postponed_date' => $event->postponed_date && $event->postponed_start_time
                    ? \Carbon\Carbon::parse($event->postponed_date->format('Y-m-d') . ' ' . $event->postponed_start_time)->toIso8601String()
                    : $event->postponed_date?->toIso8601String(),
                'postponed_start_time' => $event->postponed_start_time,
                'postponed_door_time' => $event->postponed_door_time,
                'postponed_end_time' => $event->postponed_end_time,
                'postponed_reason' => $event->postponed_reason,
                'is_promoted' => $event->is_promoted ?? false,
                'promoted_until' => $event->promoted_until?->toIso8601String(),
                'duration_mode' => $event->duration_mode,
                'start_date' => $event->duration_mode === 'single_day' && $event->start_date && $event->start_time
                    ? \Carbon\Carbon::parse($event->start_date->format('Y-m-d') . ' ' . $event->start_time)->toIso8601String()
                    : $event->start_date?->toIso8601String(),
                'end_date' => $event->duration_mode === 'single_day' && $event->start_date && $event->end_time
                    ? \Carbon\Carbon::parse($event->start_date->format('Y-m-d') . ' ' . $event->end_time)->toIso8601String()
                    : ($event->duration_mode === 'range' && $event->end_date && $event->range_end_time
                        ? \Carbon\Carbon::parse($event->end_date->format('Y-m-d') . ' ' . $event->range_end_time)->toIso8601String()
                        : $event->end_date?->toIso8601String()),
                'start_time' => $event->start_time,
                'door_time' => $event->door_time,
                'end_time' => $event->end_time,
                'address' => $event->address,
                'website_url' => $event->website_url,
                'facebook_url' => $event->facebook_url,
                'event_website_url' => $event->event_website_url,

                // Enhanced venue data
                'venue' => $event->venue ? [
                    'id' => $event->venue->id,
                    'name' => $event->venue->getTranslation('name', $locale),
                    'slug' => $event->venue->slug ?? null,
                    'address' => $event->venue->address,
                    'city' => $event->venue->city,
                    'state' => $event->venue->state,
                    'country' => $event->venue->country,
                    'latitude' => $event->venue->lat,
                    'longitude' => $event->venue->lng,
                    'google_maps_url' => $event->venue->google_maps_url,
                    'phone' => $event->venue->phone,
                    'phone2' => $event->venue->phone2,
                    'email' => $event->venue->email,
                    'email2' => $event->venue->email2,
                    'website_url' => $event->venue->website_url,
                    'image_url' => $event->venue->image_url ? Storage::disk('public')->url($event->venue->image_url) : null,
                ] : null,

                'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'hero_image_url' => $event->hero_image_url ? Storage::disk('public')->url($event->hero_image_url) : null,
                'short_description' => $event->getTranslation('short_description', $locale),
                'description' => $event->getTranslation('description', $locale),
                'ticket_terms' => $event->getTranslation('ticket_terms', $locale),
                'gallery' => $event->gallery ?? [],

                // Commission info
                'commission' => [
                    'mode' => $commissionMode, // 'included' or 'added_on_top'
                    'rate' => $commissionRate, // percentage (e.g., 5.00 means 5%)
                    'is_added_on_top' => $commissionMode === 'added_on_top',
                ],

                // SEO data
                'seo' => $event->seo ?? [],

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
                'artists' => $event->artists->load(['artistTypes', 'artistGenres'])->map(fn ($artist) => [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'slug' => $artist->slug ?? null,
                    'bio' => $artist->getTranslation('bio_html', $locale),
                    'image' => $artist->main_image ? Storage::disk('public')->url($artist->main_image) : null,
                    'portrait' => $artist->portrait_url ? Storage::disk('public')->url($artist->portrait_url) : null,
                    'city' => $artist->city,
                    'country' => $artist->country,
                    // Social Links
                    'website' => $artist->website,
                    'facebook_url' => $artist->facebook_url,
                    'instagram_url' => $artist->instagram_url,
                    'youtube_url' => $artist->youtube_url,
                    'spotify_url' => $artist->spotify_url,
                    'tiktok_url' => $artist->tiktok_url,
                    // Social Stats
                    'youtube_subscribers' => $artist->youtube_followers ?? $artist->followers_youtube,
                    'youtube_total_views' => $artist->youtube_total_views,
                    'spotify_followers' => $artist->spotify_followers,
                    'spotify_popularity' => $artist->spotify_popularity,
                    'spotify_monthly_listeners' => $artist->spotify_monthly_listeners,
                    'facebook_followers' => $artist->facebook_followers ?? $artist->followers_facebook,
                    'instagram_followers' => $artist->instagram_followers ?? $artist->followers_instagram,
                    'tiktok_followers' => $artist->tiktok_followers ?? $artist->followers_tiktok,
                    // YouTube Videos - parse URLs to extract video IDs
                    'youtube_videos' => collect($artist->youtube_videos ?? [])->map(function ($video) {
                        $url = is_array($video) ? ($video['url'] ?? null) : $video;
                        if (!$url) return null;

                        // Extract video ID from YouTube URL
                        $videoId = null;
                        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
                            $videoId = $matches[1];
                        }

                        return $videoId ? [
                            'video_id' => $videoId,
                            'url' => $url,
                            'title' => is_array($video) ? ($video['title'] ?? null) : null,
                        ] : null;
                    })->filter()->values()->toArray(),
                    // Types and Genres
                    'artist_types' => $artist->artistTypes->map(fn ($type) => [
                        'id' => $type->id,
                        'name' => $type->getTranslation('name', $locale),
                        'slug' => $type->slug,
                    ]),
                    'artist_genres' => $artist->artistGenres->map(fn ($genre) => [
                        'id' => $genre->id,
                        'name' => $genre->getTranslation('name', $locale),
                        'slug' => $genre->slug,
                    ]),
                ]),
                'tags' => $event->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->getTranslation('name', $locale),
                    'slug' => $tag->slug,
                ]) ?? [],

                // Ticket types with commission calculation - exclude invitations
                // Commission is ALWAYS calculated from BASE price (price_max), not discounted price
                'ticket_types' => $event->ticketTypes
                    ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                    ->map(function ($type) use ($commissionMode, $commissionRate) {
                        $basePrice = $type->price_max; // Always use base price for commission
                        $effectivePrice = $type->price ?? $type->price_max; // Sale price or base price
                        $commissionAmount = $commissionMode === 'added_on_top' && $basePrice
                            ? round($basePrice * ($commissionRate / 100), 2)
                            : 0;
                        $finalPrice = $commissionMode === 'added_on_top' && $effectivePrice
                            ? round($effectivePrice + $commissionAmount, 2)
                            : $effectivePrice;

                        return [
                            'id' => $type->id,
                            'name' => $type->name,
                            'description' => $type->description ?? '',
                            'sku' => $type->sku,
                            'price' => $type->price_max,
                            'sale_price' => $type->price ?? null,
                            'discount_percent' => $type->price && $type->price_max
                                ? round((1 - ($type->price / $type->price_max)) * 100, 2)
                                : null,
                            'currency' => $type->currency ?? 'RON',
                            'available' => $type->available_quantity,
                            'capacity' => $type->capacity,
                            'status' => $type->status,
                            'sales_start_at' => $type->sales_start_at?->toIso8601String(),
                            'sales_end_at' => $type->sales_end_at?->toIso8601String(),
                            'bulk_discounts' => $type->bulk_discounts ?? [],
                            // Commission details per ticket
                            'commission_amount' => $commissionAmount,
                            'final_price' => $finalPrice, // Price customer pays (with commission if added_on_top)
                        ];
                    })->values(),
                'price_from' => $event->ticketTypes
                    ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                    ->min('price_max'),
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

    /**
     * List past events for the tenant
     */
    public function pastEvents(Request $request): JsonResponse
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

        $query = Event::where('tenant_id', $tenant->id)
            ->past(); // Use past scope

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

        // Order by start_date descending (most recent first)
        $events = $query->with(['venue', 'eventTypes', 'eventGenres', 'artists', 'tags'])
            ->orderBy('event_date', 'desc')
            ->orderBy('range_start_date', 'desc')
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
                        'is_cancelled' => $event->is_cancelled ?? false,
                        'is_postponed' => $event->is_postponed ?? false,

                        // Schedule
                        'duration_mode' => $event->duration_mode,
                        'start_date' => $event->start_date?->toIso8601String(),
                        'end_date' => $event->end_date?->toIso8601String(),
                        'start_time' => $event->start_time,

                        // Location
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

                        // Taxonomies
                        'event_types' => $event->eventTypes->map(fn ($type) => [
                            'id' => $type->id,
                            'name' => $type->getTranslation('name', $locale),
                            'slug' => $type->slug,
                        ]),
                        'artists' => $event->artists->map(fn ($artist) => [
                            'id' => $artist->id,
                            'name' => $artist->name,
                            'image' => $artist->main_image ? Storage::disk('public')->url($artist->main_image) : null,
                        ]),

                        // Note: No ticket_types for past events - they can't be purchased
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
}
