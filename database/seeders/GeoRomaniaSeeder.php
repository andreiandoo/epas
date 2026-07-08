<?php

namespace Database\Seeders;

use App\Models\GeoCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the centralized geo dataset for Romania from the existing static
 * files in resources/data/ro/ (counties.php + cities/{county}.php), with:
 *
 *   - native-language county names (fixes the anglicized "Bucharest" /
 *     "Arges" / "Braila" entries → "București" / "Argeș" / "Brăila"),
 *   - diacritic normalization (cedilla ţ/ş → comma-below ț/ș),
 *   - a folded `name_ascii` for diacritic-insensitive matching.
 *
 * Idempotent: clears RO geo rows and re-inserts inside a transaction, so
 * re-running it after a data refresh is safe. Other countries are left
 * untouched.
 *
 *   php artisan db:seed --class=Database\\Seeders\\GeoRomaniaSeeder
 */
class GeoRomaniaSeeder extends Seeder
{
    /**
     * 42 Romanian counties: ISO code, canonical native name, and the
     * source filename under resources/data/ro/cities/ (some are
     * anglicized / underscored in the file system).
     */
    private array $counties = [
        ['code' => 'AB', 'name' => 'Alba', 'file' => 'Alba'],
        ['code' => 'AR', 'name' => 'Arad', 'file' => 'Arad'],
        ['code' => 'AG', 'name' => 'Argeș', 'file' => 'Arges'],
        ['code' => 'BC', 'name' => 'Bacău', 'file' => 'Bacău'],
        ['code' => 'BH', 'name' => 'Bihor', 'file' => 'Bihor'],
        ['code' => 'BN', 'name' => 'Bistrița-Năsăud', 'file' => 'Bistrița-Năsăud'],
        ['code' => 'BT', 'name' => 'Botoșani', 'file' => 'Botoșani'],
        ['code' => 'BR', 'name' => 'Brăila', 'file' => 'Braila'],
        ['code' => 'BV', 'name' => 'Brașov', 'file' => 'Brașov'],
        ['code' => 'B', 'name' => 'București', 'file' => 'Bucharest'],
        ['code' => 'BZ', 'name' => 'Buzău', 'file' => 'Buzău'],
        ['code' => 'CS', 'name' => 'Caraș-Severin', 'file' => 'Caraș-Severin'],
        ['code' => 'CL', 'name' => 'Călărași', 'file' => 'Călărași'],
        ['code' => 'CJ', 'name' => 'Cluj', 'file' => 'Cluj'],
        ['code' => 'CT', 'name' => 'Constanța', 'file' => 'Constanța'],
        ['code' => 'CV', 'name' => 'Covasna', 'file' => 'Covasna'],
        ['code' => 'DB', 'name' => 'Dâmbovița', 'file' => 'Dâmbovița'],
        ['code' => 'DJ', 'name' => 'Dolj', 'file' => 'Dolj'],
        ['code' => 'GL', 'name' => 'Galați', 'file' => 'Galați'],
        ['code' => 'GR', 'name' => 'Giurgiu', 'file' => 'Giurgiu'],
        ['code' => 'GJ', 'name' => 'Gorj', 'file' => 'Gorj'],
        ['code' => 'HR', 'name' => 'Harghita', 'file' => 'Harghita'],
        ['code' => 'HD', 'name' => 'Hunedoara', 'file' => 'Hunedoara'],
        ['code' => 'IL', 'name' => 'Ialomița', 'file' => 'Ialomița'],
        ['code' => 'IS', 'name' => 'Iași', 'file' => 'Iași'],
        ['code' => 'IF', 'name' => 'Ilfov', 'file' => 'Ilfov'],
        ['code' => 'MM', 'name' => 'Maramureș', 'file' => 'Maramureș'],
        ['code' => 'MH', 'name' => 'Mehedinți', 'file' => 'Mehedinți'],
        ['code' => 'MS', 'name' => 'Mureș', 'file' => 'Mureș'],
        ['code' => 'NT', 'name' => 'Neamț', 'file' => 'Neamț'],
        ['code' => 'OT', 'name' => 'Olt', 'file' => 'Olt'],
        ['code' => 'PH', 'name' => 'Prahova', 'file' => 'Prahova'],
        ['code' => 'SM', 'name' => 'Satu Mare', 'file' => 'Satu_Mare'],
        ['code' => 'SJ', 'name' => 'Sălaj', 'file' => 'Sălaj'],
        ['code' => 'SB', 'name' => 'Sibiu', 'file' => 'Sibiu'],
        ['code' => 'SV', 'name' => 'Suceava', 'file' => 'Suceava'],
        ['code' => 'TR', 'name' => 'Teleorman', 'file' => 'Teleorman'],
        ['code' => 'TM', 'name' => 'Timiș', 'file' => 'Timiș'],
        ['code' => 'TL', 'name' => 'Tulcea', 'file' => 'Tulcea'],
        ['code' => 'VS', 'name' => 'Vaslui', 'file' => 'Vaslui'],
        ['code' => 'VL', 'name' => 'Vâlcea', 'file' => 'Vâlcea'],
        ['code' => 'VN', 'name' => 'Vrancea', 'file' => 'Vrancea'],
    ];

    /** Known anglicized settlement names to fix to native spelling. */
    private array $localityNameFixes = [
        'Bucharest' => 'București',
    ];

    /**
     * County-scoped overrides for localities where the source data lacks
     * diacritics. Keyed by "{county_code}|{ASCII-folded name}"; the value
     * is the canonical native spelling that gets stored as name_native.
     *
     * Grow this list as `geo:normalize-venues` reports new
     * `would_downgrade` entries (the diacritic safety guard flags any
     * geo entry missing diacritics that an operator typed correctly).
     */
    private array $localityCanonicalOverrides = [
        'BC|onesti' => 'Onești',
    ];

    /**
     * Extra localities to seed on top of the static source files —
     * primarily resorts and well-known sub-localities operators reach
     * for as venue addresses. Keyed by county code; each entry carries
     * native name, lat/lng (approximate, sourced from public coords),
     * and a `type` tag for future use. Skipped if a same-named entry
     * already exists in that county (dedup-on-fold), so growing this
     * list is always safe to re-run.
     */
    private array $localityAdditions = [
        // Constanța — Black Sea resorts.
        'CT' => [
            ['name' => 'Mamaia', 'lat' => 44.2306, 'lng' => 28.6306, 'type' => 'statiune'],
            ['name' => 'Neptun', 'lat' => 43.9703, 'lng' => 28.6497, 'type' => 'statiune'],
            ['name' => 'Olimp', 'lat' => 43.9628, 'lng' => 28.6481, 'type' => 'statiune'],
            ['name' => 'Jupiter', 'lat' => 43.9519, 'lng' => 28.6464, 'type' => 'statiune'],
            ['name' => 'Venus', 'lat' => 43.9319, 'lng' => 28.6536, 'type' => 'statiune'],
            ['name' => 'Saturn', 'lat' => 43.8203, 'lng' => 28.5894, 'type' => 'statiune'],
            ['name' => 'Cap Aurora', 'lat' => 43.9408, 'lng' => 28.6519, 'type' => 'statiune'],
            ['name' => 'Eforie Nord', 'lat' => 44.0578, 'lng' => 28.6383, 'type' => 'statiune'],
            ['name' => 'Eforie Sud', 'lat' => 44.0233, 'lng' => 28.6422, 'type' => 'statiune'],
            ['name' => 'Vama Veche', 'lat' => 43.7494, 'lng' => 28.5722, 'type' => 'statiune'],
            ['name' => '2 Mai', 'lat' => 43.7894, 'lng' => 28.5750, 'type' => 'sat'],
        ],
        // Brăila — spa.
        'BR' => [
            ['name' => 'Lacul Sărat', 'lat' => 45.2289, 'lng' => 27.9319, 'type' => 'statiune'],
        ],
        // Brașov — mountain resorts.
        'BV' => [
            ['name' => 'Poiana Brașov', 'lat' => 45.5867, 'lng' => 25.5575, 'type' => 'statiune'],
            ['name' => 'Timișu de Jos', 'lat' => 45.5089, 'lng' => 25.5453, 'type' => 'sat'],
            ['name' => 'Timișu de Sus', 'lat' => 45.4992, 'lng' => 25.5742, 'type' => 'sat'],
        ],
        // Prahova — Valea Prahovei resorts.
        'PH' => [
            ['name' => 'Sinaia', 'lat' => 45.3508, 'lng' => 25.5483, 'type' => 'oras'],
            ['name' => 'Bușteni', 'lat' => 45.4083, 'lng' => 25.5333, 'type' => 'oras'],
            ['name' => 'Azuga', 'lat' => 45.4533, 'lng' => 25.5500, 'type' => 'oras'],
            ['name' => 'Slănic', 'lat' => 45.2386, 'lng' => 25.9433, 'type' => 'oras'],
        ],
        // Hunedoara — Retezat / Parâng winter resorts.
        'HD' => [
            ['name' => 'Straja', 'lat' => 45.3500, 'lng' => 23.2333, 'type' => 'statiune'],
        ],
        // Sibiu — mountain.
        'SB' => [
            ['name' => 'Păltiniș', 'lat' => 45.6500, 'lng' => 23.9333, 'type' => 'statiune'],
        ],
        // Maramureș.
        'MM' => [
            ['name' => 'Borșa', 'lat' => 47.6553, 'lng' => 24.6664, 'type' => 'oras'],
        ],
        // Harghita — sate care lipsesc din datasetul static din
        // resources/data/ro/cities/Harghita.php. Adăugate ca operatorii să
        // le poată selecta direct în picker (fără sa cadă pe fallback-ul de
        // country-wide match, care le-ar remapa pe alt județ cu acelasi nume).
        'HR' => [
            // Sat aparținând comunei Cozmeni. Există un Lăzărești și în
            // Argeș — fără intrarea asta, GeoLocations::matchLocality dă
            // fallback country-wide și remapează venue-ul în Argeș.
            ['name' => 'Lăzărești', 'lat' => 46.2200, 'lng' => 25.9500, 'type' => 'sat'],
        ],
    ];

    public function run(): void
    {
        $dir = resource_path('data/ro/cities');

        DB::transaction(function () use ($dir) {
            // Resolve / create the RO country row.
            $country = GeoCountry::updateOrCreate(
                ['iso2' => 'RO'],
                [
                    'iso3' => 'ROU',
                    'name_native' => 'România',
                    'name_en' => 'Romania',
                    'phone_code' => '+40',
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );

            // Clear existing RO geo rows (children first) for a clean re-seed.
            DB::table('geo_localities')->where('country_id', $country->id)->delete();
            DB::table('geo_counties')->where('country_id', $country->id)->delete();

            $now = now();
            $countySort = 0;

            foreach ($this->counties as $c) {
                $countySort++;
                $countyName = $this->normalizeDiacritics($c['name']);

                $countyId = DB::table('geo_counties')->insertGetId([
                    'country_id' => $country->id,
                    'code' => $c['code'],
                    'name_native' => $countyName,
                    'name_ascii' => $this->fold($countyName),
                    'slug' => Str::slug($this->fold($countyName)),
                    'latitude' => null,
                    'longitude' => null,
                    'sort_order' => $countySort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $file = "{$dir}/{$c['file']}.php";
                if (! is_file($file)) {
                    $this->command?->warn("  [skip] missing city file: {$c['file']}.php");
                    continue;
                }

                $rows = include $file;
                $batch = [];
                $locSort = 0;
                $seenFoldKeys = []; // dedup-on-fold within this county

                foreach ((array) $rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $rawName = (string) ($row['name'] ?? '');
                    if ($rawName === '') {
                        continue;
                    }

                    // Apply known anglicized fixes, then normalize diacritics.
                    $name = $this->localityNameFixes[$rawName] ?? $rawName;
                    $name = $this->normalizeDiacritics($name);

                    // County-scoped canonical override: source files store
                    // some major-city names without diacritics (e.g. "Onesti"
                    // in Bacău.php); the table promotes them to the proper
                    // native spelling before insert.
                    $overrideKey = $c['code'] . '|' . $this->fold($name);
                    if (isset($this->localityCanonicalOverrides[$overrideKey])) {
                        $name = $this->localityCanonicalOverrides[$overrideKey];
                    }

                    $foldKey = $this->fold($name);
                    if (isset($seenFoldKeys[$foldKey])) {
                        continue;
                    }
                    $seenFoldKeys[$foldKey] = true;

                    $locSort++;
                    $batch[] = [
                        'country_id' => $country->id,
                        'county_id' => $countyId,
                        'name_native' => $name,
                        'name_ascii' => $foldKey,
                        'slug' => Str::slug($foldKey),
                        'type' => null,
                        'latitude' => $this->toDecimal($row['latitude'] ?? null),
                        'longitude' => $this->toDecimal($row['longitude'] ?? null),
                        'sort_order' => $locSort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($batch) >= 500) {
                        DB::table('geo_localities')->insert($batch);
                        $batch = [];
                    }
                }

                // Manual additions (resorts, sub-localities) for this county
                // — skipped when already present by fold-key.
                foreach ($this->localityAdditions[$c['code']] ?? [] as $add) {
                    $addName = $this->normalizeDiacritics((string) $add['name']);
                    $addFold = $this->fold($addName);
                    if (isset($seenFoldKeys[$addFold])) {
                        continue;
                    }
                    $seenFoldKeys[$addFold] = true;
                    $locSort++;
                    $batch[] = [
                        'country_id' => $country->id,
                        'county_id' => $countyId,
                        'name_native' => $addName,
                        'name_ascii' => $addFold,
                        'slug' => Str::slug($addFold),
                        'type' => $add['type'] ?? null,
                        'latitude' => $this->toDecimal($add['lat'] ?? null),
                        'longitude' => $this->toDecimal($add['lng'] ?? null),
                        'sort_order' => $locSort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($batch) >= 500) {
                        DB::table('geo_localities')->insert($batch);
                        $batch = [];
                    }
                }

                if ($batch) {
                    DB::table('geo_localities')->insert($batch);
                }
            }
        });

        $country = GeoCountry::where('iso2', 'RO')->first();
        $counties = DB::table('geo_counties')->where('country_id', $country->id)->count();
        $localities = DB::table('geo_localities')->where('country_id', $country->id)->count();
        $this->command?->info("GeoRomaniaSeeder: {$counties} counties, {$localities} localities seeded.");
    }

    /**
     * Normalize legacy cedilla diacritics to the correct modern Romanian
     * comma-below forms (ţ→ț, ş→ș and their uppercase variants).
     */
    private function normalizeDiacritics(string $value): string
    {
        return strtr(trim($value), [
            "\u{0163}" => "\u{021B}", // ţ → ț
            "\u{0162}" => "\u{021A}", // Ţ → Ț
            "\u{015F}" => "\u{0219}", // ş → ș
            "\u{015E}" => "\u{0218}", // Ş → Ș
        ]);
    }

    /** Diacritic-folded, lowercased key for matching (e.g. "Bucuresti"). */
    private function fold(string $value): string
    {
        return strtolower(trim(Str::ascii($value)));
    }

    private function toDecimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = str_replace(',', '.', (string) $value);
        return is_numeric($value) ? $value : null;
    }
}
