<?php

namespace App\Services\VenueOwner;

use App\Models\Venue;
use App\Support\VenueAnalyticsMethods;

/**
 * Analytics service for the authenticated-venue-owner surface (the new
 * ambilet.ro /venue/analiza web shell and its /api/marketplace-client/
 * venue-owner/analytics/* controllers).
 *
 * A separate class from App\Services\Analytics\VenueAnalyticsService
 * on purpose — that one is the PUBLIC API surface with PII masking on
 * customer-level rows + 5-minute Cache::remember. Here the caller has
 * already been authenticated as a venue owner via EnsureVenueOwner
 * middleware, so:
 *
 *   - Superfans / attendee rows return real names/emails (private view).
 *   - No cache layer — the shell already coordinates its own refresh
 *     cadence per tab, and staleness here would leak between owners
 *     who share a marketplace client's cache namespace.
 *
 * Both services reuse the same 1544-line VenueAnalyticsMethods trait,
 * so metric definitions stay identical across public + owner views
 * — only the wrapping (masking, caching) differs.
 */
class VenueOwnerAnalyticsService
{
    use VenueAnalyticsMethods;

    /**
     * Venue ids the caller owns (via tenant partnership with the current
     * marketplace client). Resolved by the controller BEFORE instantiation,
     * so this class never has to authorize.
     */
    public array $venueIds;

    /**
     * Optional single-venue focus for KPIs that show "this venue" specifics
     * (capacity headline, health-score breakdown, etc.). Cross-venue
     * aggregates leave this null.
     */
    public ?Venue $venue;

    public function __construct(array $venueIds, ?Venue $venue = null)
    {
        $this->venueIds = array_values(array_filter(array_map('intval', $venueIds)));
        $this->venue = $venue;
    }
}
