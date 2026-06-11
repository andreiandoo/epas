<?php

namespace App\Services\Geo;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
use Database\Seeders\BackfillBileteonlineMissingRomanianCitiesSeeder;
use Database\Seeders\FixBileteonlineGeoHierarchySeeder;
use Database\Seeders\LinkBileteonlineExtraCitiesToCountiesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Bridge between the Marketplace Settings UI and the per-country geo
 * seeders.
 *
 * Each marketplace that opts in (via the new "Import country geo data"
 * action in Settings → Personalization) gets the full taxonomy for the
 * selected country in one shot: macro-regions + counties + cities,
 * non-destructive on any city rows that already exist for that
 * marketplace.
 *
 * Today only Romania is wired up — the three seeders are RO-specific
 * and well tested on bilete.online. Adding a country later means
 * adding a new method here + a corresponding country-specific seeder
 * suite; the public surface (`importCountry`) stays the same so the
 * Filament action doesn't need to change.
 */
class CountryGeoImporter
{
    /** Supported ISO-2 codes — the UI builds its select from this list. */
    public const SUPPORTED_COUNTRIES = ['RO'];

    /**
     * Run the full import for one country into one marketplace and
     * return a structured stats summary the UI can render in a
     * notification.
     *
     * The whole thing is wrapped in a DB transaction so a partial
     * failure leaves the marketplace in its previous state instead of
     * a half-seeded one. Idempotent: re-running the import on a
     * marketplace that's already been seeded just no-ops (the three
     * underlying seeders match by stable codes / slugs and skip
     * anything that's already where it belongs).
     */
    public function importCountry(int $marketplaceClientId, string $countryIso2): array
    {
        $iso2 = strtoupper($countryIso2);
        if (!in_array($iso2, self::SUPPORTED_COUNTRIES, true)) {
            throw new \InvalidArgumentException("Country {$iso2} is not supported yet.");
        }

        $before = $this->snapshotCounts($marketplaceClientId);

        DB::transaction(function () use ($marketplaceClientId, $iso2) {
            match ($iso2) {
                'RO' => $this->runRomania($marketplaceClientId),
            };
        });

        $after = $this->snapshotCounts($marketplaceClientId);

        return [
            'country'   => $iso2,
            'before'    => $before,
            'after'     => $after,
            'delta'     => [
                'regions'  => $after['regions']  - $before['regions'],
                'counties' => $after['counties'] - $before['counties'],
                'cities'   => $after['cities']   - $before['cities'],
            ],
        ];
    }

    /**
     * Romania-specific pipeline. Each of these seeders already supports
     * `forMarketplace($id)` since the recent refactor, so the same code
     * paths that ship bilete.online's data can rebuild any other
     * marketplace's catalogue when an operator picks RO from the new
     * Settings action.
     *
     * Step order matters:
     *   1) regions + counties + relink existing cities (clears old rows
     *      scoped to this marketplace, reseeds the 8 macro-regions and
     *      42 counties, updates any city we already have to point at
     *      its new region_id + county_id)
     *   2) link any extra cities that were typed in manually but aren't
     *      in the curated taxonomy
     *   3) backfill the full curated 319-city catalogue, skipping
     *      anything already present
     */
    protected function runRomania(int $marketplaceClientId): void
    {
        (new FixBileteonlineGeoHierarchySeeder())
            ->forMarketplace($marketplaceClientId)
            ->run();

        (new LinkBileteonlineExtraCitiesToCountiesSeeder())
            ->forMarketplace($marketplaceClientId)
            ->run();

        (new BackfillBileteonlineMissingRomanianCitiesSeeder())
            ->forMarketplace($marketplaceClientId)
            ->run();
    }

    /** Used to compute the delta the UI shows in the success notification. */
    protected function snapshotCounts(int $marketplaceClientId): array
    {
        return [
            'regions'  => MarketplaceRegion::where('marketplace_client_id', $marketplaceClientId)->count(),
            'counties' => MarketplaceCounty::where('marketplace_client_id', $marketplaceClientId)->count(),
            'cities'   => MarketplaceCity::where('marketplace_client_id', $marketplaceClientId)->count(),
        ];
    }
}
