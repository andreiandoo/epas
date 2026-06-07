<?php

namespace Database\Seeders;

use App\Models\MarketplaceCounty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up to FixBileteonlineGeoHierarchySeeder.
 *
 * The curated taxonomy in RomaniaLocationSeeder covers the 155 most
 * common Romanian cities. Bilete.online's catalogue has 264 — the
 * extra 109 were added by the user over time and aren't in the seeder
 * data, so the previous run reported them as "unlinked" and left
 * their county_id NULL.
 *
 * This seeder maps each of those 109 cities to its real Romanian
 * county using the standard `code` column on marketplace_counties
 * (CJ for Cluj, PH for Prahova, etc.). region_id is derived from
 * the county.
 *
 * Bilete.online only — scoped to marketplace_client_id=3.
 *
 * Safe to re-run: idempotent (just UPDATE on the two FK columns; if
 * the row is already linked correctly the write is a no-op).
 */
class LinkBileteonlineExtraCitiesToCountiesSeeder extends Seeder
{
    /** Default to bilete.online's id; overridable via forMarketplace(). */
    protected int $marketplaceClientId = 3;

    public function forMarketplace(int $marketplaceClientId): self
    {
        $this->marketplaceClientId = $marketplaceClientId;
        return $this;
    }

    /**
     * slug ⇒ county code. Codes match the standard 2-letter county
     * codes used by the rest of the system (see RomaniaLocationSeeder
     * for the canonical list).
     */
    protected const CITY_SLUG_TO_COUNTY_CODE = [
        // Bihor (BH)
        'baile-1-mai'           => 'BH',
        'beius'                 => 'BH',
        'alesd'                 => 'BH',
        'stei'                  => 'BH',

        // Vrancea (VN)
        'marasesti'             => 'VN',
        'panciu'                => 'VN',
        'odobesti'              => 'VN',

        // Teleorman (TR)
        'zimnicea'              => 'TR',
        'videle'                => 'TR',

        // Tulcea (TL)
        'babadag'               => 'TL',
        'isaccea'               => 'TL',

        // Argeș (AG)
        'costesti'              => 'AG',
        'topoloveni'            => 'AG',

        // Prahova (PH)
        'baicoi'                => 'PH',
        'comarnic'              => 'PH',
        'valenii-de-munte'      => 'PH',

        // Timiș (TM)
        'faget'                 => 'TM',
        'buzias'                => 'TM',
        'deta'                  => 'TM',
        'recas'                 => 'TM',
        'giroc'                 => 'TM',

        // Cluj (CJ)
        'huedin'                => 'CJ',
        'baciu'                 => 'CJ',

        // Constanța (CT)
        'cernavoda'             => 'CT',
        'eforie-sud'            => 'CT',

        // Ilfov (IF)
        'pantelimon'            => 'IF',
        'chitila'               => 'IF',
        'magurele'              => 'IF',

        // Brașov (BV)
        'codlea'                => 'BV',
        'zarnesti'              => 'BV',

        // Sibiu (SB)
        'avrig'                 => 'SB',
        'dumbraveni'            => 'SB',
        'talmaciu'              => 'SB',

        // Mureș (MS)
        'tarnaveni'             => 'MS',
        'ludus'                 => 'MS',

        // Dolj (DJ)
        'filiasi'               => 'DJ',
        'segarcea'              => 'DJ',

        // Iași (IS)
        'harlau'                => 'IS',

        // Galați (GL)
        'targu-bujor'           => 'GL',
        'beresti'               => 'GL',

        // Arad (AR)
        'pecica'                => 'AR',
        'curtici'               => 'AR',
        'nadlac'                => 'AR',
        'chisineu-cris'         => 'AR',

        // Bacău (BC)
        'comanesti'             => 'BC',
        'buhusi'                => 'BC',
        'darmanesti'            => 'BC',
        'targu-ocna'            => 'BC',

        // Hunedoara (HD)
        'lupeni'                => 'HD',
        'vulcan'                => 'HD',
        'brad'                  => 'HD',
        'calan'                 => 'HD',

        // Suceava (SV)
        'siret'                 => 'SV',
        'campulung-moldovenesc' => 'SV',

        // Alba (AB)
        'cugir'                 => 'AB',
        'ocna-mures'            => 'AB',
        'campeni'               => 'AB',

        // Buzău (BZ)
        'nehoiu'                => 'BZ',
        'patarlagele'           => 'BZ',
        'pogoanele'             => 'BZ',

        // Botoșani (BT)
        'darabani'              => 'BT',
        'saveni'                => 'BT',

        // Mehedinți (MH)
        'strehaia'              => 'MH',
        'vanju-mare'            => 'MH',
        'baia-de-arama'         => 'MH',

        // Gorj (GJ)
        'rovinari'              => 'GJ',
        'bumbesti-jiu'          => 'GJ',
        'targu-carbunesti'      => 'GJ',
        'novaci'                => 'GJ',

        // Bistrița-Năsăud (BN)
        'sangeorz-bai'          => 'BN',

        // Brăila (BR)
        'insuratei'             => 'BR',
        'faurei'                => 'BR',

        // Giurgiu (GR)
        'mihailesti'            => 'GR',

        // Neamț (NT)
        'roznov'                => 'NT',

        // Caraș-Severin (CS)
        'bocsa'                 => 'CS',
        'oravita'               => 'CS',
        'anina'                 => 'CS',
        'moldova-noua'          => 'CS',

        // Harghita (HR)
        'toplita'               => 'HR',
        'cristuru-secuiesc'     => 'HR',
        'balan'                 => 'HR',
        'borsec'                => 'HR',

        // Călărași (CL)
        'budesti'               => 'CL',
        'fundulea'              => 'CL',
        'lehliu-gara'           => 'CL',

        // Olt (OT)
        'corabia'               => 'OT',
        'bals'                  => 'OT',
        'scornicesti'           => 'OT',
        'draganesti-olt'        => 'OT',

        // Covasna (CV)
        'baraolt'               => 'CV',
        'intorsura-buzaului'    => 'CV',

        // Ialomița (IL)
        'tandarei'              => 'IL',
        'amara'                 => 'IL',

        // Dâmbovița (DB)
        'gaesti'                => 'DB',
        'titu'                  => 'DB',
        'fieni'                 => 'DB',

        // Vâlcea (VL)
        'baile-govora'          => 'VL',
        'brezoi'                => 'VL',
        'berbesti'              => 'VL',

        // Satu Mare (SM)
        'tasnad'                => 'SM',
        'livada'                => 'SM',
        'ardud'                 => 'SM',

        // Maramureș (MM)
        'baia-sprie'            => 'MM',
        'cavnic'                => 'MM',
        'seini'                 => 'MM',
        'tautii-magheraus'      => 'MM',

        // Sălaj (SJ)
        'cehu-silvaniei'        => 'SJ',

        // Vaslui (VS)
        'negresti'              => 'VS',
        'murgeni'               => 'VS',
    ];

    public function run(): void
    {
        $mcId = $this->marketplaceClientId;

        // Build code → County lookup ONCE so the per-city update loop
        // stays cheap.
        $counties = MarketplaceCounty::where('marketplace_client_id', $mcId)
            ->get(['id', 'code', 'region_id'])
            ->keyBy('code');

        $this->command?->info('Linking 109 extra bilete.online cities to their counties...');

        $linked = 0;
        $missingCounty = [];
        $missingCity = [];

        foreach (static::CITY_SLUG_TO_COUNTY_CODE as $citySlug => $countyCode) {
            $county = $counties->get($countyCode);
            if (!$county) {
                $missingCounty[] = "{$citySlug} → {$countyCode}";
                continue;
            }

            $updated = DB::table('marketplace_cities')
                ->where('marketplace_client_id', $mcId)
                ->where('slug', $citySlug)
                ->update([
                    'region_id'  => $county->region_id,
                    'county_id'  => $county->id,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                $missingCity[] = $citySlug;
            } else {
                $linked++;
            }
        }

        $this->command?->info("  Linked: {$linked} extra cities");
        if (!empty($missingCounty)) {
            $this->command?->warn('  Missing counties (run FixBileteonlineGeoHierarchySeeder first?):');
            foreach ($missingCounty as $m) {
                $this->command?->line("    - {$m}");
            }
        }
        if (!empty($missingCity)) {
            $this->command?->warn('  Cities the curated slug list expected but the DB did not contain:');
            foreach ($missingCity as $m) {
                $this->command?->line("    - {$m}");
            }
        }

        $this->command?->info('Refreshing county.city_count for bilete.online...');
        $counts = DB::table('marketplace_cities')
            ->where('marketplace_client_id', $mcId)
            ->whereNotNull('county_id')
            ->groupBy('county_id')
            ->selectRaw('county_id, COUNT(*) as c')
            ->pluck('c', 'county_id');

        foreach ($counts as $countyId => $c) {
            DB::table('marketplace_counties')
                ->where('id', $countyId)
                ->update(['city_count' => $c]);
        }

        $this->command?->info('Done.');
    }
}
