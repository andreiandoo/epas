<?php

namespace App\Observers;

use App\Models\Venue;
use App\Jobs\FetchVenueGoogleReviews;

class VenueObserver
{
    /**
     * When a new venue is created, automatically fetch Google Reviews.
     */
    public function created(Venue $venue): void
    {
        FetchVenueGoogleReviews::dispatch($venue->id)->delay(now()->addSeconds(5));
    }

    /**
     * When google_place_id or google_maps_url changes, re-fetch reviews.
     */
    public function updated(Venue $venue): void
    {
        // Only re-fetch if google_place_id was manually changed (not by our own job)
        if ($venue->wasChanged('google_place_id') && !$venue->wasChanged('google_reviews_updated_at')) {
            FetchVenueGoogleReviews::dispatch($venue->id)->delay(now()->addSeconds(5));
        }
    }
}
