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

                    $locSort++;
                    $batch[] = [
                        'country_id' => $country->id,
                        'county_id' => $countyId,
                        'name_native' => $name,
                        'name_ascii' => $this->fold($name),
                        'slug' => Str::slug($this->fold($name)),
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
