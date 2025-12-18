<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Venue;
use App\Models\VenueCategory;
use App\Models\VenueType;
use App\Services\SpotifyService;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDataController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'events' => Event::count(),
            'venues' => Venue::count(),
            'artists' => Artist::count(),
            'tenants' => Tenant::where('status', 'active')->count(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'tickets_sold' => Ticket::count(),
            'customers' => Customer::count(),
            'tenants' => Tenant::where('status', 'active')->count(),
            'venues' => Venue::count(),
            'events' => Event::count(),
            'artists' => Artist::count(),
        ]);
    }

    public function venues(Request $request): JsonResponse
    {
        $query = Venue::query()->with(['venueType.category']);

        if ($request->has('city')) {
            $query->where('city', $request->get('city'));
        }

        if ($request->has('country')) {
            $query->where('country', $request->get('country'));
        }

        if ($request->has('venue_type')) {
            $query->whereHas('venueType', fn($q) => $q->where('slug', $request->get('venue_type')));
        }

        if ($request->has('venue_category')) {
            $query->whereHas('venueType.category', fn($q) => $q->where('slug', $request->get('venue_category')));
        }

        if ($request->has('venue_tag')) {
            $query->where('venue_tag', $request->get('venue_tag'));
        }

        $perPage = min((int) $request->get('per_page', 50), 500);
        $paginator = $query->paginate($perPage);

        $formattedVenues = collect($paginator->items())->map(function ($venue) {
            return [
                'id' => $venue->id,
                'name' => $venue->getTranslation('name', 'en'),
                'name_translations' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->getTranslation('description', 'en'),
                'description_translations' => $venue->description,
                'venue_type' => $venue->venueType ? [
                    'id' => $venue->venueType->id,
                    'name' => $venue->venueType->getTranslation('name', 'en'),
                    'name_translations' => $venue->venueType->name,
                    'slug' => $venue->venueType->slug,
                    'icon' => $venue->venueType->icon,
                    'category' => $venue->venueType->category ? [
                        'id' => $venue->venueType->category->id,
                        'name' => $venue->venueType->category->getTranslation('name', 'en'),
                        'name_translations' => $venue->venueType->category->name,
                        'slug' => $venue->venueType->category->slug,
                        'icon' => $venue->venueType->category->icon,
                    ] : null,
                ] : null,
                'venue_tag' => $venue->venue_tag ? [
                    'key' => $venue->venue_tag,
                    'label' => Venue::TAG_OPTIONS[$venue->venue_tag]['label'] ?? null,
                    'icon' => Venue::TAG_OPTIONS[$venue->venue_tag]['icon'] ?? null,
                ] : null,
                'facilities' => $venue->getFacilitiesWithLabels(),
                'address' => $venue->address,
                'city' => $venue->city,
                'state' => $venue->state,
                'country' => $venue->country,
                'capacity' => [
                    'total' => $venue->capacity ?? $venue->capacity_total,
                    'standing' => $venue->capacity_standing,
                    'seated' => $venue->capacity_seated,
                ],
                'location' => [
                    'latitude' => $venue->lat,
                    'longitude' => $venue->lng,
                    'google_maps_url' => $venue->google_maps_url,
                ],
                'contact' => [
                    'website' => $venue->website_url,
                    'phone' => $venue->phone,
                    'phone2' => $venue->phone2,
                    'email' => $venue->email,
                    'email2' => $venue->email2,
                ],
                'social' => [
                    'facebook_url' => $venue->facebook_url,
                    'instagram_url' => $venue->instagram_url,
                    'tiktok_url' => $venue->tiktok_url,
                ],
                'media' => [
                    'image_url' => $venue->image_url,
                    'video_type' => $venue->video_type,
                    'video_url' => $venue->video_url,
                    'gallery' => $venue->gallery,
                ],
                'established_at' => $venue->established_at?->toDateString(),
                'meta' => $venue->meta,
                'created_at' => $venue->created_at?->toIso8601String(),
                'updated_at' => $venue->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $formattedVenues,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function venue(string $slug): JsonResponse
    {
        $venue = Venue::where('slug', $slug)
            ->with(['venueType.category', 'events' => function ($q) {
                $q->where('event_date', '>=', now())
                  ->orderBy('event_date')
                  ->limit(20);
            }])
            ->firstOrFail();

        return response()->json([
            'id' => $venue->id,
            'name' => $venue->getTranslation('name', 'en'),
            'name_translations' => $venue->name,
            'slug' => $venue->slug,
            'description' => $venue->getTranslation('description', 'en'),
            'description_translations' => $venue->description,
            'venue_type' => $venue->venueType ? [
                'id' => $venue->venueType->id,
                'name' => $venue->venueType->getTranslation('name', 'en'),
                'name_translations' => $venue->venueType->name,
                'slug' => $venue->venueType->slug,
                'icon' => $venue->venueType->icon,
                'category' => $venue->venueType->category ? [
                    'id' => $venue->venueType->category->id,
                    'name' => $venue->venueType->category->getTranslation('name', 'en'),
                    'name_translations' => $venue->venueType->category->name,
                    'slug' => $venue->venueType->category->slug,
                    'icon' => $venue->venueType->category->icon,
                ] : null,
            ] : null,
            'venue_tag' => $venue->venue_tag ? [
                'key' => $venue->venue_tag,
                'label' => Venue::TAG_OPTIONS[$venue->venue_tag]['label'] ?? null,
                'icon' => Venue::TAG_OPTIONS[$venue->venue_tag]['icon'] ?? null,
            ] : null,
            'facilities' => $venue->getFacilitiesWithLabels(),
            'address' => $venue->address,
            'city' => $venue->city,
            'state' => $venue->state,
            'country' => $venue->country,
            'capacity' => [
                'total' => $venue->capacity ?? $venue->capacity_total,
                'standing' => $venue->capacity_standing,
                'seated' => $venue->capacity_seated,
            ],
            'location' => [
                'latitude' => $venue->lat,
                'longitude' => $venue->lng,
                'google_maps_url' => $venue->google_maps_url,
            ],
            'contact' => [
                'website' => $venue->website_url,
                'phone' => $venue->phone,
                'phone2' => $venue->phone2,
                'email' => $venue->email,
                'email2' => $venue->email2,
            ],
            'social' => [
                'facebook_url' => $venue->facebook_url,
                'instagram_url' => $venue->instagram_url,
                'tiktok_url' => $venue->tiktok_url,
            ],
            'media' => [
                'image_url' => $venue->image_url,
                'video_type' => $venue->video_type,
                'video_url' => $venue->video_url,
                'gallery' => $venue->gallery,
            ],
            'established_at' => $venue->established_at?->toDateString(),
            'meta' => $venue->meta,
            'upcoming_events' => $venue->events->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->getTranslation('title', 'en'),
                'slug' => $event->slug,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'poster_url' => $event->poster_url,
            ]),
            'created_at' => $venue->created_at?->toIso8601String(),
            'updated_at' => $venue->updated_at?->toIso8601String(),
        ]);
    }

    public function venueTypes(): JsonResponse
    {
        $categories = VenueCategory::with(['venueTypes' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->getTranslation('name', 'en'),
                'name_translations' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'types' => $category->venueTypes->map(fn ($type) => [
                    'id' => $type->id,
                    'name' => $type->getTranslation('name', 'en'),
                    'name_translations' => $type->name,
                    'slug' => $type->slug,
                    'icon' => $type->icon,
                ]),
            ]),
            'venue_tags' => collect(Venue::TAG_OPTIONS)->map(fn ($info, $key) => [
                'key' => $key,
                'label' => $info['label'],
                'icon' => $info['icon'],
            ])->values(),
            'facilities' => collect(Venue::FACILITIES)->map(fn ($category, $key) => [
                'key' => $key,
                'label' => $category['label'],
                'items' => collect($category['items'])->map(fn ($label, $itemKey) => [
                    'key' => $itemKey,
                    'label' => $label,
                ])->values(),
            ])->values(),
        ]);
    }

    public function artists(Request $request): JsonResponse
    {
        $query = Artist::query();

        if ($request->has('active')) {
            $query->where('is_active', (bool) $request->get('active'));
        }

        if ($request->has('country')) {
            $query->where('country', $request->get('country'));
        }

        if ($request->has('city')) {
            $query->where('city', $request->get('city'));
        }

        // Filter by first letter
        if ($request->has('letter')) {
            $query->where('letter', mb_strtoupper($request->get('letter')));
        }

        // Filter by artist type (name or slug)
        if ($request->has('artist_type')) {
            $typeValue = $request->get('artist_type');
            $query->whereHas('artistTypes', function ($q) use ($typeValue) {
                $q->where('slug', $typeValue)
                  ->orWhere('name->en', $typeValue)
                  ->orWhere('name->ro', $typeValue);
            });
        }

        // Filter by artist genre (name or slug)
        if ($request->has('artist_genre')) {
            $genreValue = $request->get('artist_genre');
            $query->whereHas('artistGenres', function ($q) use ($genreValue) {
                $q->where('slug', $genreValue)
                  ->orWhere('name->en', $genreValue)
                  ->orWhere('name->ro', $genreValue);
            });
        }

        $perPage = min((int) $request->get('per_page', 50), 500);
        $paginator = $query
            ->with([
                'artistTypes',
                'artistGenres',
                'events' => fn($q) => $q->where('event_date', '>=', now())->orderBy('event_date')->select('events.id'),
            ])
            ->paginate($perPage);

        $formattedArtists = collect($paginator->items())->map(function ($artist) {
            return [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'is_active' => $artist->is_active,
                'meta' => [
                    'letter' => $artist->letter,
                ],
                'bio' => $artist->getTranslation('bio_html', 'en'),
                'bio_translations' => $artist->bio_html,
                'location' => [
                    'city' => $artist->city,
                    'country' => $artist->country,
                ],
                'contact' => [
                    'website' => $artist->website,
                    'phone' => $artist->phone,
                    'email' => $artist->email,
                ],
                'social' => [
                    'facebook_url' => $artist->facebook_url,
                    'instagram_url' => $artist->instagram_url,
                    'tiktok_url' => $artist->tiktok_url,
                    'youtube_url' => $artist->youtube_url,
                    'spotify_url' => $artist->spotify_url,
                ],
                'platform_ids' => [
                    'youtube_id' => $artist->youtube_id,
                    'spotify_id' => $artist->spotify_id,
                ],
                'images' => [
                    'main_image_url' => $artist->main_image_url,
                    'logo_url' => $artist->logo_url,
                    'portrait_url' => $artist->portrait_url,
                ],
                'youtube_videos' => $artist->youtube_videos,
                'followers' => [
                    'facebook' => $artist->followers_facebook ?? $artist->facebook_followers,
                    'instagram' => $artist->followers_instagram ?? $artist->instagram_followers,
                    'tiktok' => $artist->followers_tiktok ?? $artist->tiktok_followers,
                    'youtube' => $artist->followers_youtube ?? $artist->youtube_followers,
                    'spotify' => $artist->spotify_followers,
                    'spotify_monthly_listeners' => $artist->spotify_monthly_listeners,
                ],
                'youtube_stats' => [
                    'total_views' => $artist->youtube_total_views,
                    'total_likes' => $artist->youtube_total_likes,
                ],
                'spotify_popularity' => $artist->spotify_popularity,
                'social_stats_updated_at' => $artist->social_stats_updated_at?->toIso8601String(),
                'artist_types' => $artist->artistTypes->map(fn($type) => [
                    'id' => $type->id,
                    'name' => is_array($type->name) ? ($type->name['en'] ?? $type->name['ro'] ?? reset($type->name)) : $type->name,
                ])->toArray(),
                'artist_genres' => $artist->artistGenres->map(fn($genre) => [
                    'id' => $genre->id,
                    'name' => is_array($genre->name) ? ($genre->name['en'] ?? $genre->name['ro'] ?? reset($genre->name)) : $genre->name,
                ])->toArray(),
                'manager' => [
                    'first_name' => $artist->manager_first_name,
                    'last_name' => $artist->manager_last_name,
                    'email' => $artist->manager_email,
                    'phone' => $artist->manager_phone,
                    'website' => $artist->manager_website,
                ],
                'agent' => [
                    'first_name' => $artist->agent_first_name,
                    'last_name' => $artist->agent_last_name,
                    'email' => $artist->agent_email,
                    'phone' => $artist->agent_phone,
                    'website' => $artist->agent_website,
                ],
                'upcoming_event_ids' => $artist->events->pluck('id')->toArray(),
                'created_at' => $artist->created_at?->toIso8601String(),
                'updated_at' => $artist->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $formattedArtists,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function artist(string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)
            ->with(['artistTypes', 'artistGenres', 'events' => function ($q) {
                $q->where('event_date', '>=', now())
                  ->orderBy('event_date')
                  ->limit(20);
            }])
            ->firstOrFail();

        return response()->json([
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'is_active' => $artist->is_active,
            'meta' => [
                'letter' => $artist->letter,
            ],
            'bio' => $artist->getTranslation('bio_html', 'en'),
            'bio_translations' => $artist->bio_html,
            'location' => [
                'city' => $artist->city,
                'country' => $artist->country,
            ],
            'contact' => [
                'website' => $artist->website,
                'phone' => $artist->phone,
                'email' => $artist->email,
            ],
            'social' => [
                'facebook_url' => $artist->facebook_url,
                'instagram_url' => $artist->instagram_url,
                'tiktok_url' => $artist->tiktok_url,
                'youtube_url' => $artist->youtube_url,
                'spotify_url' => $artist->spotify_url,
            ],
            'platform_ids' => [
                'youtube_id' => $artist->youtube_id,
                'spotify_id' => $artist->spotify_id,
            ],
            'images' => [
                'main_image_url' => $artist->main_image_url,
                'logo_url' => $artist->logo_url,
                'portrait_url' => $artist->portrait_url,
            ],
            'youtube_videos' => $artist->youtube_videos,
            'followers' => [
                'facebook' => $artist->followers_facebook ?? $artist->facebook_followers,
                'instagram' => $artist->followers_instagram ?? $artist->instagram_followers,
                'tiktok' => $artist->followers_tiktok ?? $artist->tiktok_followers,
                'youtube' => $artist->followers_youtube ?? $artist->youtube_followers,
                'spotify' => $artist->spotify_followers,
                'spotify_monthly_listeners' => $artist->spotify_monthly_listeners,
            ],
            'youtube_stats' => [
                'total_views' => $artist->youtube_total_views,
                'total_likes' => $artist->youtube_total_likes,
            ],
            'spotify_popularity' => $artist->spotify_popularity,
            'social_stats_updated_at' => $artist->social_stats_updated_at?->toIso8601String(),
            'artist_types' => $artist->artistTypes->map(fn($type) => [
                'id' => $type->id,
                'name' => is_array($type->name) ? ($type->name['en'] ?? $type->name['ro'] ?? reset($type->name)) : $type->name,
            ])->toArray(),
            'artist_genres' => $artist->artistGenres->map(fn($genre) => [
                'id' => $genre->id,
                'name' => is_array($genre->name) ? ($genre->name['en'] ?? $genre->name['ro'] ?? reset($genre->name)) : $genre->name,
            ])->toArray(),
            'manager' => [
                'first_name' => $artist->manager_first_name,
                'last_name' => $artist->manager_last_name,
                'email' => $artist->manager_email,
                'phone' => $artist->manager_phone,
                'website' => $artist->manager_website,
            ],
            'agent' => [
                'first_name' => $artist->agent_first_name,
                'last_name' => $artist->agent_last_name,
                'email' => $artist->agent_email,
                'phone' => $artist->agent_phone,
                'website' => $artist->agent_website,
            ],
            'upcoming_events' => $artist->events->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->getTranslation('title', 'en'),
                'slug' => $event->slug,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'poster_url' => $event->poster_url,
                'venue' => $event->venue ? [
                    'name' => $event->venue->getTranslation('name', 'en'),
                    'city' => $event->venue->city,
                ] : null,
            ]),
            'created_at' => $artist->created_at?->toIso8601String(),
            'updated_at' => $artist->updated_at?->toIso8601String(),
        ]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $query = Tenant::where('is_active', true);

        $tenants = $query->select([
            'id', 'name', 'public_name', 'slug', 'city', 'country', 'created_at'
        ])->get();

        return response()->json($tenants);
    }

    public function tenant(string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($tenant->only([
            'id', 'name', 'public_name', 'slug', 'city', 'country', 'created_at'
        ]));
    }

    public function events(Request $request): JsonResponse
    {
        $query = Event::query();

        if ($request->has('upcoming')) {
            $query->where('event_date', '>=', now());
        }

        $perPage = min((int) $request->get('per_page', 50), 500);
        $paginator = $query->with([
            'venue:id,name,slug,address,city,lat as latitude,lng as longitude',
            'tenant:id,name,public_name,website',
            'tenant.domains' => function ($query) {
                $query->where('is_primary', true)
                      ->where('is_active', true)
                      ->select('id', 'tenant_id', 'domain');
            },
            'eventTypes:id,name',
            'eventGenres:id,name',
            'artists:id,name,slug,main_image_url',
            'tags:id,name',
            'ticketTypes'
        ])->paginate($perPage);

        $formattedEvents = collect($paginator->items())->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->getTranslation('title', 'en'),
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
                'duration_mode' => $event->duration_mode,
                'start_date' => $event->event_date,
                'end_date' => $event->end_date,
                'start_time' => $event->start_time,
                'door_time' => $event->door_time,
                'end_time' => $event->end_time,
                'address' => $event->address,
                'website_url' => $event->website_url,
                'facebook_url' => $event->facebook_url,
                'event_website_url' => $event->event_website_url,
                'poster_url' => $event->poster_url,
                'hero_image_url' => $event->hero_image_url,
                'short_description' => $event->getTranslation('short_description', 'en'),
                'description' => $event->getTranslation('description', 'en'),
                'venue' => $event->venue ? [
                    'id' => $event->venue->id,
                    'name' => $event->venue->getTranslation('name', 'en'),
                    'slug' => $event->venue->slug,
                    'address' => $event->venue->address,
                    'city' => $event->venue->city,
                    'latitude' => $event->venue->latitude,
                    'longitude' => $event->venue->longitude,
                ] : null,
                'tenant' => $event->tenant ? [
                    'id' => $event->tenant->id,
                    'name' => $event->tenant->name,
                    'public_name' => $event->tenant->public_name,
                    'website' => $event->tenant->domains->first()
                        ? 'https://' . $event->tenant->domains->first()->domain
                        : $event->tenant->website,
                    'event_url' => $event->tenant->domains->first()
                        ? 'https://' . $event->tenant->domains->first()->domain . '/event/' . $event->slug
                        : null,
                ] : null,
                'event_types' => $event->eventTypes->map(fn($type) => [
                    'id' => $type->id,
                    'name' => $type->getTranslation('name', 'en'),
                ])->toArray(),
                'event_genres' => $event->eventGenres->map(fn($genre) => [
                    'id' => $genre->id,
                    'name' => $genre->getTranslation('name', 'en'),
                ])->toArray(),
                'artists' => $event->artists->map(fn($artist) => [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'slug' => $artist->slug,
                    'image' => $artist->main_image_url,
                ])->toArray(),
                'tags' => $event->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->getTranslation('name', 'en'),
                ])->toArray(),
                'ticket_types' => $event->ticketTypes->map(fn($ticket) => [
                    'id' => $ticket->id,
                    'name' => $ticket->name,
                    'description' => $ticket->description,
                    'sku' => $ticket->sku,
                    'price' => $ticket->price_cents / 100,
                    'sale_price' => $ticket->sale_price_cents ? $ticket->sale_price_cents / 100 : null,
                    'discount_percent' => $ticket->sale_price_cents && $ticket->price_cents > 0
                        ? round((($ticket->price_cents - $ticket->sale_price_cents) / $ticket->price_cents) * 100)
                        : null,
                    'currency' => $ticket->currency,
                    'available' => max(0, ($ticket->quota_total ?? 0) - ($ticket->quota_sold ?? 0)),
                    'capacity' => $ticket->quota_total,
                    'status' => $ticket->status,
                    'sales_start_at' => $ticket->sales_start_at,
                    'sales_end_at' => $ticket->sales_end_at,
                    'bulk_discounts' => $ticket->bulk_discounts ?? [],
                ])->toArray(),
                'price_from' => $event->ticketTypes->min(fn($t) => $t->sale_price_cents ?? $t->price_cents) / 100,
            ];
        });

        return response()->json([
            'data' => $formattedEvents,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function event(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->with(['venue', 'artists', 'tenant:id,name,public_name'])
            ->firstOrFail();

        return response()->json($event);
    }

    /**
     * Get artist stats including YouTube and Spotify data
     */
    public function artistStats(string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        $stats = [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'social' => [
                'website' => $artist->website,
                'facebook_url' => $artist->facebook_url,
                'instagram_url' => $artist->instagram_url,
                'tiktok_url' => $artist->tiktok_url,
                'youtube_url' => $artist->youtube_url,
                'spotify_url' => $artist->spotify_url,
            ],
            'followers' => [
                'facebook' => $artist->followers_facebook,
                'instagram' => $artist->followers_instagram,
                'tiktok' => $artist->followers_tiktok,
                'youtube' => $artist->followers_youtube,
                'spotify' => $artist->spotify_followers,
                'spotify_monthly_listeners' => $artist->spotify_monthly_listeners,
            ],
            'youtube' => null,
            'spotify' => null,
            'kpis' => $artist->computeKpis(),
        ];

        // Fetch YouTube stats if channel ID exists
        if (!empty($artist->youtube_id)) {
            $youtubeService = app(YouTubeService::class);
            $stats['youtube'] = [
                'channel' => $youtubeService->getChannelStats($artist->youtube_id),
                'videos' => !empty($artist->youtube_videos)
                    ? $youtubeService->getVideosStats($artist->youtube_videos)
                    : [],
            ];
        }

        // Fetch Spotify stats if artist ID exists
        if (!empty($artist->spotify_id)) {
            $spotifyService = app(SpotifyService::class);
            $artistData = $spotifyService->getArtist($artist->spotify_id);
            $stats['spotify'] = [
                'artist' => $artistData,
                'top_tracks' => $spotifyService->getTopTracks($artist->spotify_id),
                'embed_html' => $spotifyService->getEmbedHtml($artist->spotify_id),
            ];
        }

        return response()->json($stats);
    }

    /**
     * Get just YouTube stats for an artist
     */
    public function artistYoutubeStats(string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        if (empty($artist->youtube_id)) {
            return response()->json([
                'error' => 'No YouTube channel ID configured for this artist',
            ], 404);
        }

        $youtubeService = app(YouTubeService::class);

        return response()->json([
            'channel' => $youtubeService->getChannelStats($artist->youtube_id),
            'videos' => !empty($artist->youtube_videos)
                ? $youtubeService->getVideosStats($artist->youtube_videos)
                : [],
            'recent_videos' => $youtubeService->getRecentVideos($artist->youtube_id, 5),
        ]);
    }

    /**
     * Get just Spotify stats for an artist
     */
    public function artistSpotifyStats(string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        if (empty($artist->spotify_id)) {
            return response()->json([
                'error' => 'No Spotify artist ID configured for this artist',
            ], 404);
        }

        $spotifyService = app(SpotifyService::class);

        return response()->json([
            'artist' => $spotifyService->getArtist($artist->spotify_id),
            'top_tracks' => $spotifyService->getTopTracks($artist->spotify_id),
            'albums' => $spotifyService->getAlbums($artist->spotify_id),
            'related_artists' => $spotifyService->getRelatedArtists($artist->spotify_id),
            'embed_html' => $spotifyService->getEmbedHtml($artist->spotify_id),
        ]);
    }
}
