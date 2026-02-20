<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_places.api_key', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Extract Google Place ID from a Google Maps URL.
     * Supports formats like:
     * - https://maps.google.com/?cid=XXXXX
     * - https://www.google.com/maps/place/.../@.../data=!...!1s0x...!2s<PLACE_ID>
     * - https://maps.app.goo.gl/XXXXX (short links - not resolvable without redirect)
     */
    public static function extractPlaceIdFromUrl(string $url): ?string
    {
        // Pattern: place_id or ChIJ... style IDs in URL
        if (preg_match('/place_id[=:]([A-Za-z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern: /maps/place/...data=...!1sChIJ...
        if (preg_match('/!1s(ChIJ[A-Za-z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Search for a place by name and location, return the place_id.
     * Uses Google Places API (New) - Text Search.
     */
    public function findPlaceId(string $name, ?string $city = null, ?string $country = null): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $query = $name;
        if ($city) {
            $query .= ', ' . $city;
        }
        if ($country) {
            $query .= ', ' . $country;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'places.id',
            ])->post('https://places.googleapis.com/v1/places:searchText', [
                'textQuery' => $query,
                'maxResultCount' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $places = $data['places'] ?? [];

                if (!empty($places)) {
                    // places.id returns "places/ChIJ..." format, extract just the ID
                    $placeId = $places[0]['id'] ?? null;
                    return $placeId;
                }
            } else {
                Log::warning('GooglePlacesService::findPlaceId failed', [
                    'query' => $query,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('GooglePlacesService::findPlaceId exception', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get place details: rating, review count, and reviews.
     * Uses Google Places API (New) - Place Details.
     *
     * Returns: ['rating' => float, 'reviews_count' => int, 'reviews' => array]
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'rating,userRatingCount,reviews',
            ])->get("https://places.googleapis.com/v1/places/{$placeId}");

            if ($response->successful()) {
                $data = $response->json();

                $reviews = [];
                foreach (($data['reviews'] ?? []) as $review) {
                    $reviews[] = [
                        'author' => $review['authorAttribution']['displayName'] ?? null,
                        'profile_photo_url' => $review['authorAttribution']['photoUri'] ?? null,
                        'rating' => $review['rating'] ?? null,
                        'text' => $review['text']['text'] ?? null,
                        'language' => $review['text']['languageCode'] ?? null,
                        'time' => $review['publishTime'] ?? null,
                        'relative_time' => $review['relativePublishTimeDescription'] ?? null,
                    ];
                }

                return [
                    'rating' => $data['rating'] ?? null,
                    'reviews_count' => $data['userRatingCount'] ?? null,
                    'reviews' => $reviews,
                ];
            }

            Log::warning('GooglePlacesService::getPlaceDetails failed', [
                'placeId' => $placeId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('GooglePlacesService::getPlaceDetails exception', [
                'placeId' => $placeId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
