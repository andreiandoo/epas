<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Artist;
use App\Models\ArtistGenre;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ArtistsController extends BaseController
{
    /**
     * Get artists listing with filters
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Artist::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true);

        // Filter by letter
        if ($request->filled('letter')) {
            $query->where('letter', strtoupper($request->input('letter')));
        }

        // Filter by genre
        if ($request->filled('genre')) {
            $genreSlug = $request->input('genre');
            $query->whereHas('artistGenres', function ($q) use ($genreSlug) {
                $q->where('slug', $genreSlug);
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $typeSlug = $request->input('type');
            $query->whereHas('artistTypes', function ($q) use ($typeSlug) {
                $q->where('slug', $typeSlug);
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', $request->input('city'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        // Featured only
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // With upcoming events only
        if ($request->boolean('with_events')) {
            $query->whereHas('events', function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->where('is_cancelled', false);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');

        switch ($sort) {
            case 'followers':
                $query->orderByRaw('COALESCE(spotify_monthly_listeners, 0) + COALESCE(instagram_followers, 0) DESC');
                break;
            case 'events':
                $query->withCount(['events' => function ($q) {
                    $q->where('event_date', '>=', now()->toDateString());
                }])->orderBy('events_count', 'desc');
                break;
            default:
                $query->orderBy('name', $order);
        }

        $perPage = min($request->input('per_page', 12), 50);
        $paginator = $query->paginate($perPage);

        $language = $client->language ?? 'ro';

        $artists = collect($paginator->items())->map(function ($artist) use ($language) {
            return $this->formatArtist($artist, $language);
        });

        return response()->json([
            'success' => true,
            'data' => $artists,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get featured artists
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $artists = Artist::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_featured', true)
            ->where('is_active', true)
            ->limit($request->input('limit', 6))
            ->get();

        $language = $client->language ?? 'ro';

        return $this->success([
            'artists' => $artists->map(fn ($a) => $this->formatArtist($a, $language)),
        ]);
    }

    /**
     * Get trending artists (by upcoming events + followers)
     */
    public function trending(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $artists = Artist::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->whereHas('events', function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->where('is_cancelled', false);
            })
            ->withCount(['events' => function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->where('is_cancelled', false);
            }])
            ->orderByRaw('COALESCE(spotify_monthly_listeners, 0) + COALESCE(instagram_followers, 0) DESC')
            ->limit($request->input('limit', 8))
            ->get();

        $language = $client->language ?? 'ro';

        return $this->success([
            'artists' => $artists->map(fn ($a) => $this->formatArtist($a, $language)),
        ]);
    }

    /**
     * Get genre counts for filter
     */
    public function genreCounts(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $genres = ArtistGenre::query()
            ->withCount(['artists' => function ($q) use ($client) {
                $q->where('marketplace_client_id', $client->id)
                  ->where('is_active', true);
            }])
            ->having('artists_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function ($genre) {
                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'slug' => $genre->slug,
                    'count' => $genre->artists_count,
                ];
            });

        return $this->success([
            'genres' => $genres,
        ]);
    }

    /**
     * Get available alphabet letters
     */
    public function alphabet(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $letters = Artist::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->whereNotNull('letter')
            ->distinct()
            ->pluck('letter')
            ->sort()
            ->values();

        return $this->success([
            'letters' => $letters,
        ]);
    }

    /**
     * Get single artist by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $artist = Artist::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$artist) {
            return $this->error('Artist not found', 404);
        }

        $language = $client->language ?? 'ro';

        // Get upcoming events
        $upcomingEvents = $artist->events()
            ->where('event_date', '>=', now()->toDateString())
            ->where('is_cancelled', false)
            ->orderBy('event_date')
            ->limit(10)
            ->get()
            ->map(function ($event) use ($language) {
                return [
                    'id' => $event->id,
                    'title' => $event->getTranslation('title', $language),
                    'slug' => $event->slug,
                    'event_date' => $event->event_date,
                    'start_time' => $event->start_time,
                    'venue' => $event->venue ? [
                        'name' => $event->venue->getTranslation('name', $language) ?? $event->venue->name,
                        'city' => $event->venue->city,
                    ] : null,
                    'min_price' => $event->min_price_minor ? ($event->min_price_minor / 100) : null,
                    'currency' => $event->currency ?? 'RON',
                    'image' => $event->main_image_url,
                    'is_sold_out' => $event->is_sold_out ?? false,
                ];
            });

        // Get past events count
        $pastEventsCount = $artist->events()
            ->where('event_date', '<', now()->toDateString())
            ->count();

        // Get similar artists (same genres)
        $genreIds = $artist->artistGenres()->pluck('id');
        $similarArtists = collect();

        if ($genreIds->isNotEmpty()) {
            $similarArtists = Artist::query()
                ->where('marketplace_client_id', $client->id)
                ->where('is_active', true)
                ->where('id', '!=', $artist->id)
                ->whereHas('artistGenres', function ($q) use ($genreIds) {
                    $q->whereIn('artist_genres.id', $genreIds);
                })
                ->limit(6)
                ->get()
                ->map(fn ($a) => $this->formatArtist($a, $language, true));
        }

        // Build response
        $data = [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'image' => $artist->main_image_url,
            'logo' => $artist->logo_url,
            'portrait' => $artist->portrait_url,
            'biography' => $artist->getTranslation('bio_html', $language),
            'city' => $artist->city,
            'country' => $artist->country,
            'is_verified' => $artist->is_featured,
            'genres' => $artist->artistGenres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
            ]),
            'types' => $artist->artistTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ]),
            'stats' => [
                'spotify_listeners' => $artist->spotify_monthly_listeners,
                'spotify_popularity' => $artist->spotify_popularity,
                'instagram_followers' => $artist->instagram_followers,
                'youtube_subscribers' => $artist->youtube_followers,
                'facebook_followers' => $artist->facebook_followers,
                'tiktok_followers' => $artist->tiktok_followers,
                'upcoming_events' => $upcomingEvents->count(),
                'past_events' => $pastEventsCount,
            ],
            'social' => [
                'instagram' => $artist->instagram_url,
                'youtube' => $artist->youtube_url,
                'spotify' => $artist->spotify_url,
                'facebook' => $artist->facebook_url,
                'tiktok' => $artist->tiktok_url,
                'website' => $artist->website,
            ],
            'external_ids' => [
                'spotify_id' => $artist->spotify_id,
                'youtube_id' => $artist->youtube_id,
            ],
            'youtube_videos' => $artist->youtube_videos ?? [],
            'booking_agency' => $artist->booking_agency ?? null,
            'upcoming_events' => $upcomingEvents,
            'similar_artists' => $similarArtists,
        ];

        return $this->success($data);
    }

    /**
     * Get artist events
     */
    public function events(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $artist = Artist::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$artist) {
            return $this->error('Artist not found', 404);
        }

        $language = $client->language ?? 'ro';

        $query = $artist->events()
            ->where('is_cancelled', false);

        // Filter: upcoming or past
        if ($request->input('filter') === 'past') {
            $query->where('event_date', '<', now()->toDateString())
                  ->orderBy('event_date', 'desc');
        } else {
            $query->where('event_date', '>=', now()->toDateString())
                  ->orderBy('event_date');
        }

        $perPage = min($request->input('per_page', 10), 50);
        $paginator = $query->paginate($perPage);

        $events = collect($paginator->items())->map(function ($event) use ($language) {
            return [
                'id' => $event->id,
                'title' => $event->getTranslation('title', $language),
                'slug' => $event->slug,
                'event_date' => $event->event_date,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'venue' => $event->venue ? [
                    'name' => $event->venue->getTranslation('name', $language) ?? $event->venue->name,
                    'city' => $event->venue->city,
                    'slug' => $event->venue->slug,
                ] : null,
                'min_price' => $event->min_price_minor ? ($event->min_price_minor / 100) : null,
                'currency' => $event->currency ?? 'RON',
                'image' => $event->main_image_url,
                'is_sold_out' => $event->is_sold_out ?? false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Format artist for list display
     */
    protected function formatArtist(Artist $artist, string $language, bool $minimal = false): array
    {
        $data = [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'image' => $artist->main_image_url,
            'portrait' => $artist->portrait_url,
            'logo' => $artist->logo_url,
            'city' => $artist->city,
            'is_verified' => $artist->is_featured,
        ];

        if (!$minimal) {
            $data['genres'] = $artist->artistGenres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
            ]);
            $data['types'] = $artist->artistTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ]);
            $data['stats'] = [
                'spotify_listeners' => $artist->spotify_monthly_listeners,
                'instagram_followers' => $artist->instagram_followers,
            ];
            // Upcoming events count
            $data['upcoming_events_count'] = $artist->events()
                ->where('event_date', '>=', now()->toDateString())
                ->where('is_cancelled', false)
                ->count();
        }

        return $data;
    }
}
