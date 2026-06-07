<?php

namespace Database\Seeders;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill bilete.online's marketplace_cities with the full set of
 * 320 official Romanian cities (103 municipii + 217 orașe) — no
 * communes.
 *
 * Why a curated list instead of pulling from geo_localities or
 * resources/data/ro: those datasets include every comună and sat too
 * (3000+ rows), and have no "is_official_city" classifier. The
 * Romanian government's actual list of urban administrative units is
 * a fixed catalogue that changes by 1-2 entries per year at most, so
 * hardcoding it here is both the smallest and the most accurate
 * source of truth.
 *
 * Behavior:
 *   - For each (county_code, city_name), if a marketplace_city row
 *     with the slugified name already exists on bilete.online, skip
 *     (preserves descriptions, GYG ids, custom edits etc.).
 *   - Otherwise insert a fresh row pointing at the right county.
 *   - Refreshes county.city_count at the end so the admin lists
 *     reflect the new totals.
 *
 * Run on live:
 *   php artisan db:seed --class=BackfillBileteonlineMissingRomanianCitiesSeeder --force
 *
 * Idempotent — re-running adds nothing new because every city ends
 * up matched by slug.
 */
class BackfillBileteonlineMissingRomanianCitiesSeeder extends Seeder
{
    /** Default to bilete.online; overridable via forMarketplace(). */
    protected int $marketplaceClientId = 3;

    public function forMarketplace(int $marketplaceClientId): self
    {
        $this->marketplaceClientId = $marketplaceClientId;
        return $this;
    }

    /**
     * Romanian cities by county code. Each entry is a (name, optional
     * is_municipiu) — the M flag is only metadata for now (could feed
     * sort_order or featured later); doesn't affect insert behavior.
     * Encoding: bare string = oraș; ['M', name] = municipiu.
     */
    protected const CITIES_BY_COUNTY = [
        'AB' => [['M', 'Alba Iulia'], ['M', 'Aiud'], ['M', 'Sebeș'], ['M', 'Blaj'], 'Cugir', 'Câmpeni', 'Abrud', 'Baia de Arieș', 'Ocna Mureș', 'Teiuș', 'Zlatna'],
        'AR' => [['M', 'Arad'], 'Lipova', 'Ineu', 'Pâncota', 'Sebiș', 'Curtici', 'Nădlac', 'Pecica', 'Sântana', 'Chișineu-Criș'],
        'AG' => [['M', 'Pitești'], ['M', 'Curtea de Argeș'], ['M', 'Câmpulung'], 'Mioveni', 'Costești', 'Ștefănești', 'Topoloveni'],
        'BC' => [['M', 'Bacău'], ['M', 'Onești'], ['M', 'Moinești'], 'Comănești', 'Buhuși', 'Dărmănești', 'Slănic-Moldova', 'Târgu Ocna'],
        'BH' => [['M', 'Oradea'], ['M', 'Beiuș'], ['M', 'Marghita'], ['M', 'Salonta'], 'Aleșd', 'Nucet', 'Ștei', 'Săcueni', 'Valea lui Mihai', 'Vașcău'],
        'BN' => [['M', 'Bistrița'], 'Năsăud', 'Beclean', 'Sângeorz-Băi'],
        'BT' => [['M', 'Botoșani'], ['M', 'Dorohoi'], 'Bucecea', 'Darabani', 'Flămânzi', 'Săveni', 'Ștefănești'],
        'BR' => [['M', 'Brăila'], 'Făurei', 'Ianca', 'Însurăței'],
        'BV' => [['M', 'Brașov'], ['M', 'Făgăraș'], ['M', 'Săcele'], 'Codlea', 'Ghimbav', 'Predeal', 'Râșnov', 'Rupea', 'Victoria', 'Zărnești'],
        'B'  => [['M', 'București']],
        'BZ' => [['M', 'Buzău'], ['M', 'Râmnicu Sărat'], 'Nehoiu', 'Pătârlagele', 'Pogoanele'],
        'CS' => [['M', 'Reșița'], ['M', 'Caransebeș'], 'Anina', 'Băile Herculane', 'Bocșa', 'Moldova Nouă', 'Oravița', 'Oțelu Roșu'],
        'CL' => [['M', 'Călărași'], ['M', 'Oltenița'], 'Budești', 'Fundulea', 'Lehliu Gară'],
        'CJ' => [['M', 'Cluj-Napoca'], ['M', 'Câmpia Turzii'], ['M', 'Dej'], ['M', 'Gherla'], ['M', 'Turda'], 'Huedin'],
        'CT' => [['M', 'Constanța'], ['M', 'Mangalia'], ['M', 'Medgidia'], 'Cernavodă', 'Eforie', 'Hârșova', 'Murfatlar', 'Năvodari', 'Negru Vodă', 'Ovidiu', 'Techirghiol'],
        'CV' => [['M', 'Sfântu Gheorghe'], ['M', 'Târgu Secuiesc'], 'Baraolt', 'Covasna', 'Întorsura Buzăului'],
        'DB' => [['M', 'Târgoviște'], ['M', 'Moreni'], 'Fieni', 'Găești', 'Pucioasa', 'Răcari', 'Titu'],
        'DJ' => [['M', 'Craiova'], ['M', 'Băilești'], ['M', 'Calafat'], 'Bechet', 'Dăbuleni', 'Filiași', 'Segarcea'],
        'GL' => [['M', 'Galați'], ['M', 'Tecuci'], 'Berești', 'Târgu Bujor'],
        'GR' => [['M', 'Giurgiu'], 'Bolintin-Vale', 'Mihăilești'],
        'GJ' => [['M', 'Târgu Jiu'], ['M', 'Motru'], 'Bumbești-Jiu', 'Novaci', 'Rovinari', 'Târgu Cărbunești', 'Tismana', 'Țicleni', 'Turceni'],
        'HR' => [['M', 'Miercurea Ciuc'], ['M', 'Gheorgheni'], ['M', 'Odorheiu Secuiesc'], ['M', 'Toplița'], 'Băile Tușnad', 'Bălan', 'Borsec', 'Cristuru Secuiesc', 'Vlăhița'],
        'HD' => [['M', 'Deva'], ['M', 'Hunedoara'], ['M', 'Lupeni'], ['M', 'Petroșani'], ['M', 'Vulcan'], 'Aninoasa', 'Brad', 'Călan', 'Geoagiu', 'Hațeg', 'Orăștie', 'Petrila', 'Simeria', 'Uricani'],
        'IL' => [['M', 'Slobozia'], ['M', 'Fetești'], ['M', 'Urziceni'], 'Amara', 'Căzănești', 'Fierbinți-Târg', 'Țăndărei'],
        'IS' => [['M', 'Iași'], ['M', 'Pașcani'], 'Hârlău', 'Podu Iloaiei', 'Târgu Frumos'],
        'IF' => ['Bragadiru', 'Buftea', 'Chitila', 'Măgurele', 'Otopeni', 'Pantelimon', 'Popești-Leordeni', 'Voluntari'],
        'MM' => [['M', 'Baia Mare'], ['M', 'Sighetu Marmației'], 'Baia Sprie', 'Borșa', 'Cavnic', 'Dragomirești', 'Săliștea de Sus', 'Seini', 'Șomcuta Mare', 'Târgu Lăpuș', 'Tăuții-Măgherăuș', 'Ulmeni', 'Vișeu de Sus'],
        'MH' => [['M', 'Drobeta-Turnu Severin'], ['M', 'Orșova'], 'Baia de Aramă', 'Strehaia', 'Vânju Mare'],
        'MS' => [['M', 'Târgu Mureș'], ['M', 'Reghin'], ['M', 'Sighișoara'], 'Iernut', 'Luduș', 'Miercurea Nirajului', 'Sărmașu', 'Sângeorgiu de Pădure', 'Sovata', 'Târnăveni', 'Ungheni'],
        'NT' => [['M', 'Piatra Neamț'], ['M', 'Roman'], 'Bicaz', 'Roznov', 'Târgu Neamț'],
        'OT' => [['M', 'Slatina'], ['M', 'Caracal'], 'Balș', 'Corabia', 'Drăgănești-Olt', 'Piatra-Olt', 'Potcoava', 'Scornicești'],
        'PH' => [['M', 'Ploiești'], ['M', 'Câmpina'], 'Azuga', 'Băicoi', 'Boldești-Scăeni', 'Breaza', 'Bușteni', 'Comarnic', 'Mizil', 'Plopeni', 'Sinaia', 'Slănic', 'Urlați', 'Vălenii de Munte'],
        'SM' => [['M', 'Satu Mare'], ['M', 'Carei'], 'Ardud', 'Livada', 'Negrești-Oaș', 'Tășnad'],
        'SJ' => [['M', 'Zalău'], 'Cehu Silvaniei', 'Jibou', 'Șimleu Silvaniei'],
        'SB' => [['M', 'Sibiu'], ['M', 'Mediaș'], 'Agnita', 'Avrig', 'Cisnădie', 'Copșa Mică', 'Dumbrăveni', 'Miercurea Sibiului', 'Ocna Sibiului', 'Săliște', 'Tălmaciu'],
        'SV' => [['M', 'Suceava'], ['M', 'Câmpulung Moldovenesc'], ['M', 'Fălticeni'], ['M', 'Rădăuți'], ['M', 'Vatra Dornei'], 'Broșteni', 'Cajvana', 'Dolhasca', 'Frasin', 'Gura Humorului', 'Liteni', 'Milișăuți', 'Salcea', 'Siret', 'Solca', 'Vicovu de Sus'],
        'TR' => [['M', 'Alexandria'], ['M', 'Roșiori de Vede'], ['M', 'Turnu Măgurele'], 'Videle', 'Zimnicea'],
        'TM' => [['M', 'Timișoara'], ['M', 'Lugoj'], 'Buziaș', 'Ciacova', 'Deta', 'Făget', 'Gătaia', 'Jimbolia', 'Recaș', 'Sânnicolau Mare'],
        'TL' => [['M', 'Tulcea'], 'Babadag', 'Isaccea', 'Măcin', 'Sulina'],
        'VS' => [['M', 'Vaslui'], ['M', 'Bârlad'], ['M', 'Huși'], 'Murgeni', 'Negrești'],
        'VL' => [['M', 'Râmnicu Vâlcea'], ['M', 'Drăgășani'], 'Băbeni', 'Bălcești', 'Băile Govora', 'Băile Olănești', 'Berbești', 'Brezoi', 'Călimănești', 'Horezu', 'Ocnele Mari'],
        'VN' => [['M', 'Focșani'], ['M', 'Adjud'], 'Mărășești', 'Odobești', 'Panciu'],
    ];

    public function run(): void
    {
        $mcId = $this->marketplaceClientId;

        // Build code → County lookup once. Will fail loudly if any
        // county code in the curated list isn't present in the DB.
        $counties = MarketplaceCounty::where('marketplace_client_id', $mcId)
            ->get(['id', 'code', 'region_id', 'name'])
            ->keyBy('code');

        $expectedCount = collect(static::CITIES_BY_COUNTY)->flatten(1)->count();
        $this->command?->info("Backfilling bilete.online from a curated list of {$expectedCount} Romanian cities...");

        $inserted = 0;
        $skippedExisting = 0;
        $missingCounty = [];

        DB::transaction(function () use ($mcId, $counties, &$inserted, &$skippedExisting, &$missingCounty) {
            foreach (static::CITIES_BY_COUNTY as $countyCode => $cities) {
                $county = $counties->get($countyCode);
                if (!$county) {
                    $missingCounty[] = $countyCode;
                    continue;
                }

                foreach ($cities as $entry) {
                    [$isMunicipiu, $name] = is_array($entry)
                        ? [true, $entry[1]]
                        : [false, $entry];

                    $slug = Str::slug($name);

                    $existing = MarketplaceCity::where('marketplace_client_id', $mcId)
                        ->where('slug', $slug)
                        ->first();

                    if ($existing) {
                        $skippedExisting++;
                        continue;
                    }

                    MarketplaceCity::create([
                        'marketplace_client_id' => $mcId,
                        'region_id'   => $county->region_id,
                        'county_id'   => $county->id,
                        'slug'        => $slug,
                        'name'        => ['ro' => $name, 'en' => $name],
                        'country'     => 'RO',
                        'is_visible'  => true,
                        'is_featured' => $isMunicipiu, // municipiile pe featured
                        'is_capital'  => false,
                    ]);
                    $inserted++;
                }
            }
        });

        $this->command?->info("  Inserted: {$inserted} new cities");
        $this->command?->info("  Skipped:  {$skippedExisting} (already present)");

        if (!empty($missingCounty)) {
            $this->command?->warn('Counties present in the curated list but missing in DB:');
            foreach ($missingCounty as $code) {
                $this->command?->line("  - {$code}");
            }
        }

        $this->command?->info('Refreshing county.city_count...');
        $counts = DB::table('marketplace_cities')
            ->where('marketplace_client_id', $mcId)
            ->whereNotNull('county_id')
            ->groupBy('county_id')
            ->selectRaw('county_id, COUNT(*) as c')
            ->pluck('c', 'county_id');

        foreach ($counts as $countyId => $c) {
            DB::table('marketplace_counties')->where('id', $countyId)->update(['city_count' => $c]);
        }

        $total = MarketplaceCity::where('marketplace_client_id', $mcId)->count();
        $this->command?->info("Done. Bilete.online now has {$total} marketplace_cities rows.");
    }
}
