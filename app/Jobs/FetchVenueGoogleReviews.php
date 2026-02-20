<?php

namespace App\Jobs;

use App\Models\Venue;
use App\Services\GooglePlacesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchVenueGoogleReviews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 60;

    protected int $venueId;

    public function __construct(int $venueId)
    {
        $this->venueId = $venueId;
        $this->onQueue('google-reviews');
    }

    public function handle(): void
    {
        $venue = Venue::find($this->venueId);

        if (!$venue) {
            Log::warning("FetchVenueGoogleReviews: Venue {$this->venueId} not found");
            return;
        }

        $service = new GooglePlacesService();

        if (!$service->isConfigured()) {
            Log::warning('FetchVenueGoogleReviews: Google Places API key not configured');
            return;
        }

        $placeId = $venue->google_place_id;

        // If no place_id, try to extract from google_maps_url or search by name
        if (empty($placeId)) {
            // Try extracting from existing Google Maps URL
            if (!empty($venue->google_maps_url)) {
                $placeId = GooglePlacesService::extractPlaceIdFromUrl($venue->google_maps_url);
            }

            // If still no place_id, search by name + city
            if (empty($placeId)) {
                $venueName = is_array($venue->name)
                    ? ($venue->name['ro'] ?? $venue->name['en'] ?? reset($venue->name))
                    : $venue->name;

                if (!empty($venueName)) {
                    $placeId = $service->findPlaceId($venueName, $venue->city, $venue->country);
                }
            }

            // Save the discovered place_id so we don't search again
            if (!empty($placeId)) {
                $venue->update(['google_place_id' => $placeId]);
            } else {
                Log::info("FetchVenueGoogleReviews: Could not find place_id for venue {$venue->id}");
                return;
            }
        }

        // Fetch place details (rating, reviews_count, reviews)
        $details = $service->getPlaceDetails($placeId);

        if (!$details) {
            Log::warning("FetchVenueGoogleReviews: Could not fetch details for place {$placeId}");
            return;
        }

        $venue->update([
            'google_rating' => $details['rating'],
            'google_reviews_count' => $details['reviews_count'],
            'google_reviews' => $details['reviews'],
            'google_reviews_updated_at' => now(),
        ]);

        Log::info("FetchVenueGoogleReviews: Updated venue {$venue->id}", [
            'place_id' => $placeId,
            'rating' => $details['rating'],
            'reviews_count' => $details['reviews_count'],
            'reviews_fetched' => count($details['reviews']),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("FetchVenueGoogleReviews job failed for venue {$this->venueId}: {$exception->getMessage()}");
    }
}
