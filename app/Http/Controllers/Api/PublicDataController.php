<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Artist;
use App\Models\ArtistGenre;
use App\Models\ArtistType;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\ExchangeRate;
use App\Models\MarketplaceCustomer;
use App\Models\Microservice;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Venue;
use App\Models\VenueCategory;
use App\Models\VenueType;
use App\Services\SpotifyService;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicDataController extends Controller
{
    /**
     * Cache TTL in seconds for public API responses.
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Cache TTL for daily summary data (24 hours).
     */
    private const DAILY_CACHE_TTL = 86400; // 24 hours

    /**
     * Build a cached JSON response with proper Cache-Control headers.
     */
    private function cachedJson(string $cacheKey, int $ttl, callable $builder): JsonResponse
    {
        $data = Cache::remember($cacheKey, $ttl, $builder);

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=' . $ttl)
            ->header('X-Cache-Key', $cacheKey);
    }

    public function stats(): JsonResponse
    {
        return $this->cachedJson('api:public:stats', self::CACHE_TTL, fn () => [
            'events' => Event::count(),
            'venues' => Venue::count(),
            'artists' => Artist::count(),
            'tenants' => Tenant::where('status', 'active')->count(),
        ]);
    }

    public function data(): JsonResponse
    {
        return $this->cachedJson('api:public:data', self::CACHE_TTL, fn () => [
            'tickets_sold' => Ticket::count(),
            'customers' => Customer::count(),
            'tenants' => Tenant::where('status', 'active')->count(),
            'venues' => Venue::count(),
            'events' => Event::count(),
            'artists' => Artist::count(),
            'event_genres' => EventGenre::count(),
            'event_types' => EventType::count(),
            'venue_categories' => VenueCategory::count(),
            'venue_types' => VenueType::count(),
            'artist_types' => ArtistType::count(),
            'artist_genres' => ArtistGenre::count(),
            'microservices' => Microservice::count(),
            'affiliates' => Affiliate::count(),
            'orders' => Order::count(),
            'orders_total_cents' => (int) Order::sum('total_cents'),
            'orders_paid_total_cents' => (int) Order::where('status', 'paid')->sum('total_cents'),
        ]);
    }

    public function venues(Request $request): JsonResponse
    {
        $cacheKey = 'api:public:venues:' . md5($request->getQueryString() ?? '');

        return $this->cachedJson($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Venue::query()->with(['venueType.category']);

            if ($request->boolean('has_coordinates')) {
                $query->whereNotNull('lat')->whereNotNull('lng')
                    ->where('lat', '!=', 0)->where('lng', '!=', 0);
            }

            if ($request->has('city')) {
                $query->where('city', $request->input('city'));
            }

            if ($request->has('country')) {
                $query->where('country', $request->input('country'));
            }

            if ($request->has('venue_type')) {
                $query->whereHas('venueType', fn($q) => $q->where('slug', $request->input('venue_type')));
            }

            if ($request->has('venue_category')) {
                $query->whereHas('venueType.category', fn($q) => $q->where('slug', $request->input('venue_category')));
            }

            if ($request->has('venue_tag')) {
                $query->where('venue_tag', $request->input('venue_tag'));
            }

            $perPage = min((int) $request->input('per_page', 50), 500);
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

            return [
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
            ];
        });
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
        return $this->cachedJson('api:public:venue-types', self::CACHE_TTL, function () {
            $categories = VenueCategory::with(['venueTypes' => fn($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get();

            return [
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
            ];
        });
    }

    public function artists(Request $request): JsonResponse
    {
        $cacheKey = 'api:public:artists:' . md5($request->getQueryString() ?? '');

        return $this->cachedJson($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Artist::query();

            if ($request->has('active')) {
                $query->where('is_active', (bool) $request->input('active'));
            }

            if ($request->has('country')) {
                $query->where('country', $request->input('country'));
            }

            if ($request->has('city')) {
                $query->where('city', $request->input('city'));
            }

            // Filter by first letter
            if ($request->has('letter')) {
                $query->where('letter', mb_strtoupper($request->input('letter')));
            }

            // Search by name
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'LIKE', "%{$search}%");
            }

            // Filter by artist type (name or slug)
            if ($request->has('artist_type')) {
                $typeValue = $request->input('artist_type');
                $query->whereHas('artistTypes', function ($q) use ($typeValue) {
                    $q->where('slug', $typeValue)
                      ->orWhere('name->en', $typeValue)
                      ->orWhere('name->ro', $typeValue);
                });
            }

            // Filter by artist genre (name or slug) - supports both ?genre= and ?artist_genre=
            if ($request->has('genre') || $request->has('artist_genre')) {
                $genreValue = $request->input('genre') ?? $request->input('artist_genre');
                $query->whereHas('artistGenres', function ($q) use ($genreValue) {
                    $q->where('slug', $genreValue)
                      ->orWhere('name->en', $genreValue)
                      ->orWhere('name->ro', $genreValue);
                });
            }

            $perPage = min((int) $request->input('per_page', 50), 500);
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

            return [
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
            ];
        });
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
        return $this->cachedJson('api:public:tenants', self::CACHE_TTL, function () {
            return Tenant::where('is_active', true)
                ->select(['id', 'name', 'public_name', 'slug', 'city', 'country', 'created_at'])
                ->get()
                ->toArray();
        });
    }

    public function tenant(string $slug): JsonResponse
    {
        return $this->cachedJson('api:public:tenant:' . $slug, self::CACHE_TTL, function () use ($slug) {
            $tenant = Tenant::where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            return $tenant->only(['id', 'name', 'public_name', 'slug', 'city', 'country', 'created_at']);
        });
    }

    public function events(Request $request): JsonResponse
    {
        $cacheKey = 'api:public:events:' . md5($request->getQueryString() ?? '');

        return $this->cachedJson($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Event::query();

            if ($request->has('upcoming')) {
                $query->where('event_date', '>=', now());
            }

            $perPage = min((int) $request->input('per_page', 50), 500);
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
                        'available' => $ticket->quota_total < 0 ? PHP_INT_MAX : max(0, $ticket->quota_total - ($ticket->quota_sold ?? 0)),
                        'capacity' => $ticket->quota_total < 0 ? -1 : $ticket->quota_total,
                        'status' => $ticket->status,
                        'sales_start_at' => $ticket->sales_start_at,
                        'sales_end_at' => $ticket->sales_end_at,
                        'bulk_discounts' => $ticket->bulk_discounts ?? [],
                    ])->toArray(),
                    'price_from' => $event->ticketTypes->min(fn($t) => $t->sale_price_cents ?? $t->price_cents) / 100,
                ];
            });

            return [
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
            ];
        });
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

    /**
     * Daily-cached summary with all totals for tixello.com pages.
     * Returns counts, city lists, and breakdowns - all cached for 24h.
     */
    public function summary(): JsonResponse
    {
        return $this->cachedJson('api:public:summary', self::DAILY_CACHE_TTL, function () {
            try {
                $venuesWithCoordsCount = Venue::whereNotNull('lat')->whereNotNull('lng')
                    ->where('lat', '!=', 0)->where('lng', '!=', 0)
                    ->count();

                $venueCities = Venue::whereNotNull('lat')->whereNotNull('lng')
                    ->where('lat', '!=', 0)->where('lng', '!=', 0)
                    ->whereNotNull('city')->where('city', '!=', '')
                    ->select('city')->distinct()->pluck('city');

                $upcomingCount = Event::where('event_date', '>=', now())->count();
                $upcomingThisMonth = Event::where('event_date', '>=', now())
                    ->where('event_date', '<=', now()->endOfMonth())
                    ->count();

                // Revenue calculation (same logic as admin dashboard StatsOverview)
                $paidStatuses = ['paid', 'confirmed', 'completed'];

                try {
                    $eurRon = ExchangeRate::getLatestRate('EUR', 'RON') ?: 1;
                } catch (\Throwable $e) {
                    $eurRon = 4.97; // fallback rate
                }

                $revenueEur = $this->sumRevenueByCurrency($paidStatuses, 'EUR');
                $revenueRon = $this->sumRevenueByCurrency($paidStatuses, 'RON');
                $totalRevenueEur = $revenueEur + ($eurRon > 0 ? $revenueRon / $eurRon : 0);
                $totalRevenueRon = $totalRevenueEur * $eurRon;

                // Safe counts with fallbacks for tables/columns that may not exist
                $artistsTotal = $this->safeCount(fn () => Artist::count());
                $artistsActive = $this->safeCount(fn () => Artist::where('is_active', true)->count());
                $tenantsTotal = $this->safeCount(fn () => Tenant::count());
                $tenantsActive = $this->safeCount(fn () => Tenant::where('status', 'active')->count());
                $ordersTotal = $this->safeCount(fn () => Order::count());
                $ordersPaid = $this->safeCount(fn () => Order::whereIn('status', $paidStatuses)->count());
                $tenantCustomers = $this->safeCount(fn () => Customer::count());
                $marketplaceCustomers = $this->safeCount(fn () => MarketplaceCustomer::count());
                $ticketsSold = $this->safeCount(fn () => Ticket::count());

                return [
                    'venues' => [
                        'total' => Venue::count(),
                        'with_coordinates' => $venuesWithCoordsCount,
                        'cities_on_map' => $venueCities->count(),
                        'cities_list' => $venueCities->sort()->values(),
                    ],
                    'events' => [
                        'total' => Event::count(),
                        'upcoming' => $upcomingCount,
                        'upcoming_this_month' => $upcomingThisMonth,
                    ],
                    'artists' => [
                        'total' => $artistsTotal,
                        'active' => $artistsActive,
                    ],
                    'tenants' => [
                        'total' => $tenantsTotal,
                        'active' => $tenantsActive,
                    ],
                    'orders' => [
                        'total' => $ordersTotal,
                        'paid' => $ordersPaid,
                    ],
                    'customers' => $tenantCustomers + $marketplaceCustomers,
                    'customers_breakdown' => [
                        'tenant' => $tenantCustomers,
                        'marketplace' => $marketplaceCustomers,
                    ],
                    'tickets_sold' => $ticketsSold,
                    'revenue' => [
                        'total_eur' => round($totalRevenueEur, 2),
                        'total_ron' => round($totalRevenueRon, 2),
                        'native_eur' => round($revenueEur, 2),
                        'native_ron' => round($revenueRon, 2),
                        'exchange_rate_eur_ron' => $eurRon,
                    ],
                    'cached_at' => now()->toIso8601String(),
                    'cache_ttl_hours' => self::DAILY_CACHE_TTL / 3600,
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Summary endpoint error: ' . $e->getMessage());
                return [
                    'error' => 'Failed to generate summary',
                    'cached_at' => now()->toIso8601String(),
                ];
            }
        });
    }

    /**
     * Safe count helper — returns 0 if the query fails (missing table/column).
     */
    private function safeCount(callable $query): int
    {
        try {
            return $query();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Sum paid order revenue by currency (same logic as StatsOverview widget).
     */
    private function sumRevenueByCurrency(array $statuses, string $currency): float
    {
        try {
            $total = (float) Order::where('currency', $currency)
                ->whereIn('status', $statuses)
                ->sum('total');

            // Fallback: try total_cents / 100 if 'total' column is 0
            if ($total == 0) {
                $total = (float) Order::where('currency', $currency)
                    ->whereIn('status', $statuses)
                    ->sum('total_cents') / 100;
            }

            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Lightweight endpoint returning only venue map pins (id, name, slug, lat, lng, city).
     * Returns ALL venues with coordinates in a single response, cached for 24h.
     */
    public function venuesMap(): JsonResponse
    {
        return $this->cachedJson('api:public:venues-map', self::DAILY_CACHE_TTL, function () {
            $venues = Venue::whereNotNull('lat')->whereNotNull('lng')
                ->where('lat', '!=', 0)->where('lng', '!=', 0)
                ->select(['id', 'name', 'slug', 'lat', 'lng', 'city', 'country', 'address', 'capacity', 'capacity_total', 'venue_type_id'])
                ->with('venueType:id,name,slug,icon')
                ->get();

            $cities = $venues->pluck('city')->filter()->unique()->sort()->values();

            return [
                'pins' => $venues->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->getTranslation('name', 'en'),
                    'name_translations' => $v->name,
                    'slug' => $v->slug,
                    'lat' => (float) $v->lat,
                    'lng' => (float) $v->lng,
                    'city' => $v->city,
                    'country' => $v->country,
                    'address' => $v->address,
                    'capacity' => $v->capacity ?? $v->capacity_total,
                    'venue_type' => $v->venueType ? [
                        'name' => $v->venueType->getTranslation('name', 'en'),
                        'slug' => $v->venueType->slug,
                        'icon' => $v->venueType->icon,
                    ] : null,
                ])->values(),
                'total' => $venues->count(),
                'cities' => $cities,
                'cities_count' => $cities->count(),
            ];
        });
    }
}
