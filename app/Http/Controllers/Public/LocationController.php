<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    /**
     * Get all countries
     */
    public function countries(): JsonResponse
    {
        $countries = $this->locationService->getCountries();

        return response()->json([
            'success' => true,
            'data' => $countries
        ]);
    }

    /**
     * Get states/counties for a country
     */
    public function states(string $country): JsonResponse
    {
        $states = $this->locationService->getStates($country);

        return response()->json([
            'success' => true,
            'data' => $states
        ]);
    }

    /**
     * Get cities for a country and state
     */
    public function cities(string $country, string $state): JsonResponse
    {
        $cities = $this->locationService->getCities($country, $state);

        return response()->json([
            'success' => true,
            'data' => $cities
        ]);
    }

    /**
     * Get event genres filtered by event type
     */
    public function eventGenresByType(?string $typeSlug = null): JsonResponse
    {
        // If no type specified, return all genres
        if (!$typeSlug) {
            $genres = \App\Models\EventGenre::orderBy('name')->get(['id', 'name', 'slug']);
            return response()->json([
                'success' => true,
                'data' => $genres
            ]);
        }

        // Find event type by slug
        $eventType = EventType::where('slug', $typeSlug)->first();

        if (!$eventType) {
            return response()->json([
                'success' => false,
                'message' => 'Event type not found',
                'data' => []
            ], 404);
        }

        // Get allowed genres for this type
        $genres = $eventType->allowedEventGenres()
            ->orderBy('name')
            ->get(['event_genres.id', 'event_genres.name', 'event_genres.slug']);

        return response()->json([
            'success' => true,
            'data' => $genres
        ]);
    }
}
