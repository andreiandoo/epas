<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SpotifyService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl = 'https://api.spotify.com/v1';
    protected string $authUrl = 'https://accounts.spotify.com/api/token';

    public function __construct()
    {
        $this->clientId = config('services.spotify.client_id', '');
        $this->clientSecret = config('services.spotify.client_secret', '');
    }

    /**
     * Get access token using client credentials flow
     */
    protected function getAccessToken(): ?string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return null;
        }

        $cacheKey = 'spotify_access_token';

        return Cache::remember($cacheKey, now()->addMinutes(55), function () {
            try {
                $response = Http::asForm()
                    ->withBasicAuth($this->clientId, $this->clientSecret)
                    ->post($this->authUrl, [
                        'grant_type' => 'client_credentials',
                    ]);

                if (!$response->successful()) {
                    return null;
                }

                return $response->json('access_token');
            } catch (\Exception $e) {
                \Log::error('Spotify Auth error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get artist information by Spotify artist ID
     */
    public function getArtist(string $artistId): ?array
    {
        $token = $this->getAccessToken();
        if (!$token || empty($artistId)) {
            return null;
        }

        $cacheKey = "spotify_artist_{$artistId}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($token, $artistId) {
            try {
                $response = Http::withToken($token)
                    ->get("{$this->baseUrl}/artists/{$artistId}");

                if (!$response->successful()) {
                    return null;
                }

                $artist = $response->json();

                return [
                    'id' => $artist['id'] ?? '',
                    'name' => $artist['name'] ?? '',
                    'genres' => $artist['genres'] ?? [],
                    'popularity' => (int) ($artist['popularity'] ?? 0),
                    'followers' => (int) ($artist['followers']['total'] ?? 0),
                    'images' => $artist['images'] ?? [],
                    'external_url' => $artist['external_urls']['spotify'] ?? '',
                    'fetched_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                \Log::error('Spotify Artist API error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get artist's top tracks
     */
    public function getTopTracks(string $artistId, string $market = 'RO'): array
    {
        $token = $this->getAccessToken();
        if (!$token || empty($artistId)) {
            return [];
        }

        $cacheKey = "spotify_top_tracks_{$artistId}_{$market}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($token, $artistId, $market) {
            try {
                $response = Http::withToken($token)
                    ->get("{$this->baseUrl}/artists/{$artistId}/top-tracks", [
                        'market' => $market,
                    ]);

                if (!$response->successful()) {
                    return [];
                }

                $tracks = [];
                foreach ($response->json('tracks') ?? [] as $track) {
                    $tracks[] = [
                        'id' => $track['id'] ?? '',
                        'name' => $track['name'] ?? '',
                        'album' => $track['album']['name'] ?? '',
                        'album_image' => $track['album']['images'][0]['url'] ?? '',
                        'duration_ms' => (int) ($track['duration_ms'] ?? 0),
                        'popularity' => (int) ($track['popularity'] ?? 0),
                        'preview_url' => $track['preview_url'] ?? null,
                        'external_url' => $track['external_urls']['spotify'] ?? '',
                    ];
                }

                return $tracks;
            } catch (\Exception $e) {
                \Log::error('Spotify Top Tracks API error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get artist's albums
     */
    public function getAlbums(string $artistId, int $limit = 10): array
    {
        $token = $this->getAccessToken();
        if (!$token || empty($artistId)) {
            return [];
        }

        $cacheKey = "spotify_albums_{$artistId}_{$limit}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($token, $artistId, $limit) {
            try {
                $response = Http::withToken($token)
                    ->get("{$this->baseUrl}/artists/{$artistId}/albums", [
                        'include_groups' => 'album,single',
                        'limit' => $limit,
                    ]);

                if (!$response->successful()) {
                    return [];
                }

                $albums = [];
                foreach ($response->json('items') ?? [] as $album) {
                    $albums[] = [
                        'id' => $album['id'] ?? '',
                        'name' => $album['name'] ?? '',
                        'type' => $album['album_type'] ?? '',
                        'release_date' => $album['release_date'] ?? '',
                        'total_tracks' => (int) ($album['total_tracks'] ?? 0),
                        'images' => $album['images'] ?? [],
                        'external_url' => $album['external_urls']['spotify'] ?? '',
                    ];
                }

                return $albums;
            } catch (\Exception $e) {
                \Log::error('Spotify Albums API error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get related artists
     */
    public function getRelatedArtists(string $artistId): array
    {
        $token = $this->getAccessToken();
        if (!$token || empty($artistId)) {
            return [];
        }

        $cacheKey = "spotify_related_{$artistId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($token, $artistId) {
            try {
                $response = Http::withToken($token)
                    ->get("{$this->baseUrl}/artists/{$artistId}/related-artists");

                if (!$response->successful()) {
                    return [];
                }

                $artists = [];
                foreach (array_slice($response->json('artists') ?? [], 0, 10) as $artist) {
                    $artists[] = [
                        'id' => $artist['id'] ?? '',
                        'name' => $artist['name'] ?? '',
                        'genres' => array_slice($artist['genres'] ?? [], 0, 3),
                        'popularity' => (int) ($artist['popularity'] ?? 0),
                        'followers' => (int) ($artist['followers']['total'] ?? 0),
                        'image' => $artist['images'][0]['url'] ?? '',
                    ];
                }

                return $artists;
            } catch (\Exception $e) {
                \Log::error('Spotify Related Artists API error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get full artist stats (combined data)
     */
    public function getArtistStats(string $artistId, string $market = 'RO'): ?array
    {
        $artist = $this->getArtist($artistId);
        if (!$artist) {
            return null;
        }

        return [
            'artist' => $artist,
            'top_tracks' => $this->getTopTracks($artistId, $market),
            'albums' => $this->getAlbums($artistId),
            'related_artists' => $this->getRelatedArtists($artistId),
        ];
    }

    /**
     * Generate embed HTML for artist
     */
    public function getEmbedHtml(string $artistId, string $type = 'artist', int $height = 352): string
    {
        if (empty($artistId)) {
            return '';
        }

        $uri = match ($type) {
            'artist' => "artist/{$artistId}",
            'album' => "album/{$artistId}",
            'track' => "track/{$artistId}",
            'playlist' => "playlist/{$artistId}",
            default => "artist/{$artistId}",
        };

        return sprintf(
            '<iframe style="border-radius:12px" src="https://open.spotify.com/embed/%s?utm_source=generator&theme=0" width="100%%" height="%d" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>',
            $uri,
            $height
        );
    }

    /**
     * Extract artist ID from Spotify URL
     */
    public static function extractArtistId(string $url): ?string
    {
        // Standard artist URL
        if (preg_match('/spotify\.com\/artist\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        // URI format
        if (preg_match('/spotify:artist:([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Clear cache for a specific artist
     */
    public function clearCache(string $artistId): void
    {
        Cache::forget("spotify_artist_{$artistId}");
        Cache::forget("spotify_top_tracks_{$artistId}_RO");
        Cache::forget("spotify_albums_{$artistId}_10");
        Cache::forget("spotify_related_{$artistId}");
    }
}
