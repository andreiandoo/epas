<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Venue;
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
            'tenants' => Tenant::where('is_active', true)->count(),
        ]);
    }

    public function venues(Request $request): JsonResponse
    {
        $query = Venue::query();

        if ($request->has('active')) {
            $query->where('is_active', true);
        }

        $venues = $query->select([
            'id', 'name', 'slug', 'city', 'country', 'capacity',
            'address', 'latitude', 'longitude', 'created_at'
        ])->get();

        return response()->json($venues);
    }

    public function venue(string $slug): JsonResponse
    {
        $venue = Venue::where('slug', $slug)->firstOrFail();

        return response()->json($venue);
    }

    public function artists(Request $request): JsonResponse
    {
        $query = Artist::query();

        if ($request->has('active')) {
            $query->where('is_active', true);
        }

        $artists = $query->select([
            'id', 'name', 'slug', 'country', 'bio', 'created_at'
        ])->get();

        return response()->json($artists);
    }

    public function artist(string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        return response()->json($artist);
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

        $events = $query->with([
            'venue:id,name,slug,address,city,lat as latitude,lng as longitude',
            'tenant:id,name,public_name,website',
            'eventTypes:id,name',
            'eventGenres:id,name',
            'artists:id,name,slug,main_image_url',
            'tags:id,name',
            'ticketTypes'
        ])->limit(100)->get();

        $formattedEvents = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->getTranslation('title', 'en'),
                'slug' => $event->slug,
                'is_sold_out' => $event->is_sold_out ?? false,
                'door_sales_only' => $event->door_sales_only ?? false,
                'is_cancelled' => $event->is_cancelled ?? false,
                'cancel_reason' => $event->cancel_reason,
                'is_postponed' => $event->is_postponed ?? false,
                'postponed_date' => $event->postponed_date,
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
                    'website' => $event->tenant->website,
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

        return response()->json($formattedEvents);
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
