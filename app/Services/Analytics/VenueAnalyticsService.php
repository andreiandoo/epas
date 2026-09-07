<?php

namespace App\Services\Analytics;

use App\Models\Venue;
use App\Support\VenueAnalyticsMethods;

/**
 * Thin service wrapper around the VenueAnalyticsMethods trait so the same
 * 28 builder methods that power the Filament tenant-panel Venue Analytics
 * page (app/Filament/Tenant/Pages/VenueAnalytics.php) can be consumed from
 * plain controllers — specifically the venue-owner API surface at
 * /api/marketplace-client/venue-owner/analytics/* that feeds the new
 * ambilet.ro /venue/analiza web shell.
 *
 * The trait's methods only reach for two pieces of state on the host —
 * $this->venueIds (array) and $this->venue (nullable model) — plus
 * internal build* helpers. No auth, no session, no Filament coupling.
 * Wrapping it here in a plain PHP class means both consumers (Filament
 * page + web API controllers) end up calling the exact same code paths;
 * changing a metric in one place changes it everywhere.
 *
 * Constructing the service DOES NOT touch the DB — the trait's private
 * cache methods (venueEventIds, venueOrderIds) run lazily when a builder
 * is called. Safe to instantiate per-request and discard.
 */
class VenueAnalyticsService
{
    use VenueAnalyticsMethods;

    /**
     * Venue ids the caller is authorized to see. In the Filament path this
     * is populated from $tenant->venues()->pluck('id'); in the web API path
     * the VenueOwner controller resolves it the same way (partnered venues
     * of the current marketplace client) before instantiating this service.
     */
    public array $venueIds;

    /**
     * Optional single-venue focus. When a user drills into one location the
     * trait's builders that show "this venue" specifics (e.g. capacity in
     * the KPI strip, health-score sub-scores) read from it. Leave null for
     * cross-venue aggregates. Trait methods that don't need it never touch
     * this property.
     */
    public ?Venue $venue;

    public function __construct(array $venueIds, ?Venue $venue = null)
    {
        $this->venueIds = array_values(array_filter(array_map('intval', $venueIds)));
        $this->venue = $venue;
    }
}
