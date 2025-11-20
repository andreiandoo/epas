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
            $query->where('start_date', '>=', now());
        }

        $events = $query->select([
            'id', 'title', 'slug', 'start_date', 'end_date',
            'venue_id', 'tenant_id', 'created_at'
        ])->with(['venue:id,name,slug', 'tenant:id,name,public_name'])
          ->limit(100)
          ->get();

        return response()->json($events);
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
