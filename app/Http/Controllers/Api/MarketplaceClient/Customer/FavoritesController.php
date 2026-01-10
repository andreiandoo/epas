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
}
