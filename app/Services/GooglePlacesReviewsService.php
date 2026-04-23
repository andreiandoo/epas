<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around Google Places Details API for venue reviews.
 *
 * Call fetchReviewsForPlaceId() from an admin action (e.g. the "Sync reviews"
 * button on VenueResource) and cache the result on the venue model. Never call
 * this on the public request path — the whole point is to keep API quota low.
 */
class GooglePlacesReviewsService
{
    protected const ENDPOINT = 'https://maps.googleapis.com/maps/api/place/details/json';
    protected const FIELDS = 'rating,user_ratings_total,reviews,url';

    /**
     * Fetch reviews + aggregate rating for a given Google Place ID.
     *
     * @return array{
     *     rating: float|null,
     *     review_count: int,
     *     reviews: array<int,array{author_name:?string,author_url:?string,profile_photo_url:?string,rating:?int,text:?string,relative_time_description:?string,time:?int}>,
     *     place_url: string
     * }
     *
     * @throws RuntimeException on missing API key, transport error, or non-OK Google status.
     */
    public function fetchReviewsForPlaceId(string $placeId): array
    {
        $key = (string) config('services.google_places.api_key');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_PLACES_API_KEY is not configured.');
        }

        $placeId = trim($placeId);
        if ($placeId === '') {
            throw new RuntimeException('Empty place_id.');
        }

        // reviews_sort=most_relevant returns Google's helpfulness-ranked
        // set of reviews, which tends to surface higher-rated ones. With
        // 'newest' we got the 5 latest regardless of rating, which on many
        // venues left only ~3 entries clearing the 4.5★ bar used on the
        // public venue page (so the shuffle always picked the same 3).
        $response = Http::timeout(10)->get(self::ENDPOINT, [
            'place_id' => $placeId,
            'fields' => self::FIELDS,
            'reviews_sort' => 'most_relevant',
            'language' => 'ro',
            'key' => $key,
        ]);

        if (!$response->successful()) {
            Log::warning('GooglePlacesReviewsService: HTTP error', [
                'place_id' => $placeId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Google Places request failed (HTTP ' . $response->status() . ').');
        }

        $json = $response->json();
        $status = $json['status'] ?? 'UNKNOWN';

        if ($status !== 'OK') {
            $errorMessage = $json['error_message'] ?? $status;
            Log::warning('GooglePlacesReviewsService: non-OK status', [
                'place_id' => $placeId,
                'status' => $status,
                'error' => $errorMessage,
            ]);
            throw new RuntimeException('Google Places API returned ' . $status . ': ' . $errorMessage);
        }

        $result = $json['result'] ?? [];
        $rawReviews = $result['reviews'] ?? [];
        $reviews = array_map(fn ($r) => [
            'author_name' => $r['author_name'] ?? null,
            'author_url' => $r['author_url'] ?? null,
            'profile_photo_url' => $r['profile_photo_url'] ?? null,
            'rating' => isset($r['rating']) ? (int) $r['rating'] : null,
            'text' => $r['text'] ?? null,
            'relative_time_description' => $r['relative_time_description'] ?? null,
            'time' => isset($r['time']) ? (int) $r['time'] : null,
        ], $rawReviews);

        return [
            'rating' => isset($result['rating']) ? (float) $result['rating'] : null,
            'review_count' => (int) ($result['user_ratings_total'] ?? 0),
            'reviews' => $reviews,
            'place_url' => $result['url'] ?? ('https://www.google.com/maps/place/?q=place_id:' . $placeId),
        ];
    }
}
