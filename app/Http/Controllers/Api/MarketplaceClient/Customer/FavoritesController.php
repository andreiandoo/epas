<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\MarketplaceCustomer;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoritesController extends BaseController
{
    /**
     * Toggle favorite status for an artist
     */
    public function toggleArtist(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find artist by ID or slug
        $artist = is_numeric($identifier)
            ? Artist::find($identifier)
            : Artist::where('slug', $identifier)->first();

        if (!$artist) {
            return $this->error('Artist not found', 404);
        }

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Authentication required', 401);
        }

        // Check if already favorited
        $existing = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('favoriteable_type', 'artist')
            ->where('favoriteable_id', $artist->id)
            ->first();

        if ($existing) {
            // Remove from favorites
            DB::table('marketplace_customer_favorites')
                ->where('id', $existing->id)
                ->delete();
            $isFavorite = false;
        } else {
            // Add to favorites
            DB::table('marketplace_customer_favorites')->insert([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'favoriteable_type' => 'artist',
                'favoriteable_id' => $artist->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $isFavorite = true;
        }

        return $this->success([
            'is_favorite' => $isFavorite,
            'artist_id' => $artist->id,
        ]);
    }

    /**
     * Check if artist is favorited
     */
    public function checkArtist(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find artist by ID or slug
        $artist = is_numeric($identifier)
            ? Artist::find($identifier)
            : Artist::where('slug', $identifier)->first();

        if (!$artist) {
            return $this->error('Artist not found', 404);
        }

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->success([
                'is_favorite' => false,
                'artist_id' => $artist->id,
            ]);
        }

        $isFavorite = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('favoriteable_type', 'artist')
            ->where('favoriteable_id', $artist->id)
            ->exists();

        return $this->success([
            'is_favorite' => $isFavorite,
            'artist_id' => $artist->id,
        ]);
    }

    /**
     * Toggle favorite status for a venue
     */
    public function toggleVenue(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find venue by ID or slug
        $venue = is_numeric($identifier)
            ? Venue::find($identifier)
            : Venue::where('slug', $identifier)->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Authentication required', 401);
        }

        // Check if already favorited
        $existing = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('favoriteable_type', 'venue')
            ->where('favoriteable_id', $venue->id)
            ->first();

        if ($existing) {
            // Remove from favorites
            DB::table('marketplace_customer_favorites')
                ->where('id', $existing->id)
                ->delete();
            $isFavorite = false;
        } else {
            // Add to favorites
            DB::table('marketplace_customer_favorites')->insert([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'favoriteable_type' => 'venue',
                'favoriteable_id' => $venue->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $isFavorite = true;
        }

        return $this->success([
            'is_favorite' => $isFavorite,
            'venue_id' => $venue->id,
        ]);
    }

    /**
     * Check if venue is favorited
     */
    public function checkVenue(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find venue by ID or slug
        $venue = is_numeric($identifier)
            ? Venue::find($identifier)
            : Venue::where('slug', $identifier)->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->success([
                'is_favorite' => false,
                'venue_id' => $venue->id,
            ]);
        }

        $isFavorite = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('favoriteable_type', 'venue')
            ->where('favoriteable_id', $venue->id)
            ->exists();

        return $this->success([
            'is_favorite' => $isFavorite,
            'venue_id' => $venue->id,
        ]);
    }

    /**
     * Get all favorite artists for the current user
     */
    public function listArtists(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Authentication required', 401);
        }

        $favorites = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('favoriteable_type', 'artist')
            ->orderByDesc('created_at')
            ->get();

        $artistIds = $favorites->pluck('favoriteable_id');

        // If no favorites, return empty array
        if ($artistIds->isEmpty()) {
            return $this->success([]);
        }

        $artists = Artist::whereIn('id', $artistIds)
            ->with('artistGenres')
            ->withCount(['events' => function ($query) {
                $query->where('event_date', '>=', now()->toDateString());
            }])
            ->get()
            ->keyBy('id');

        $formattedArtists = $favorites->map(function ($fav) use ($artists) {
            $artist = $artists->get($fav->favoriteable_id);
            if (!$artist) {
                return null;
            }
            // Get genre name - it's a translatable field (JSON), so extract the string
            $genreName = null;
            $firstGenre = $artist->artistGenres->first();
            if ($firstGenre) {
                $genreName = $firstGenre->getTranslation('name', 'ro') ?? $firstGenre->getTranslation('name', 'en');
            }

            return [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'image' => $artist->main_image_full_url ?? $artist->image_url,
                'genre' => $genreName,
                'events' => $artist->events_count ?? 0,
                'added_at' => $fav->created_at,
            ];
        })->filter()->values();

        return $this->success($formattedArtists);
    }

    /**
     * Get all favorite venues for the current user
     */
    public function listVenues(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Authentication required', 401);
        }

        $favorites = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('favoriteable_type', 'venue')
            ->orderByDesc('created_at')
            ->get();

        $venueIds = $favorites->pluck('favoriteable_id');

        // If no favorites, return empty array
        if ($venueIds->isEmpty()) {
            return $this->success([]);
        }

        $venues = Venue::whereIn('id', $venueIds)
            ->withCount(['events' => function ($query) {
                $query->where('event_date', '>=', now()->toDateString());
            }])
            ->get()
            ->keyBy('id');

        $formattedVenues = $favorites->map(function ($fav) use ($venues) {
            $venue = $venues->get($fav->favoriteable_id);
            if (!$venue) {
                return null;
            }

            // Handle image URL - use APP_URL for consistent domain
            $imageUrl = null;
            if ($venue->image_url) {
                if (str_starts_with($venue->image_url, 'http://') || str_starts_with($venue->image_url, 'https://')) {
                    $imageUrl = $venue->image_url;
                } else {
                    $imageUrl = rtrim(config('app.url'), '/') . '/storage/' . ltrim($venue->image_url, '/');
                }
            }

            return [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'image' => $imageUrl,
                'city' => $venue->city,
                'events' => $venue->events_count ?? 0,
                'added_at' => $fav->created_at,
            ];
        })->filter()->values();

        return $this->success($formattedVenues);
    }

    /**
     * Get favorites summary (counts) for the current user
     */
    public function summary(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Check if user is authenticated
        $customer = Auth::guard('sanctum')->user();
        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Authentication required', 401);
        }

        $artistsCount = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('favoriteable_type', 'artist')
            ->count();

        $venuesCount = DB::table('marketplace_customer_favorites')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('favoriteable_type', 'venue')
            ->count();

        $eventsCount = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->count();

        return $this->success([
            'events_count' => $eventsCount,
            'artists_count' => $artistsCount,
            'venues_count' => $venuesCount,
            'total' => $eventsCount + $artistsCount + $venuesCount,
        ]);
    }
}
