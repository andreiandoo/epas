<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Models\AttractionType;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import attractions (points of interest) from the bilete.online CSV into the
 * `attractions` table, mapping the `tip` column onto existing attraction_types
 * and `oras` onto existing marketplace_cities, and downloading the
 * `imagine_principala` URL into the public disk as the cover image.
 *
 * CSV columns: nume, nume_en, slug, subtitlu, descriere, oras, judet, tip,
 *   adresa, latitudine, longitudine, meta_title, meta_description,
 *   cuvinte_cheie, imagine_principala, galerie_foto
 *
 * Idempotent: upserts on (marketplace_client_id, slug). Re-running refreshes
 * the text fields; the cover image is only (re)downloaded when missing, or with
 * --force-images. Run a fast text-only pass first with --no-images, then a
 * second pass for images if you like.
 *
 * Examples:
 *   php artisan import:bilete-attractions --no-images
 *   php artisan import:bilete-attractions --offset=0 --limit=500
 *   php artisan import:bilete-attractions --force-images
 *   php artisan import:bilete-attractions --report        (reads only: coverage + what is unlinked)
 */
class ImportBileteAttractionsCommand extends Command
{
    protected $signature = 'import:bilete-attractions
        {--file= : CSV path (defaults to the bundled atractii_romania_final_import.csv)}
        {--marketplace=3 : marketplace_client_id}
        {--limit=0 : Max data rows to process (0 = all)}
        {--offset=0 : Skip the first N data rows}
        {--no-images : Skip downloading cover images (fast text-only pass)}
        {--force-images : Re-download cover even if one is already set}
        {--no-create-types : Do NOT auto-create attraction types missing from DB}
        {--relink-cities : Only pass: re-link city = null attractions using aggressive name normalisation (no new cities, no other writes)}
        {--create-cities : Create marketplace_cities for genuinely-missing localities that have at least --min-attractions, then relink (is_visible=false)}
        {--min-attractions=4 : Threshold for --create-cities}
        {--backfill-counties : Only pass: fill marketplace_county_id from the linked city county, falling back to the CSV judet column}
        {--report : Only pass: what the CSV covers, what landed, and what is still unlinked (reads only)}
        {--dry-run : Parse + map but write nothing}';

    protected $description = 'Import attractions from CSV (maps types + cities, downloads cover images).';

    private array $cityMap = [];   // normalized city name => id
    private array $typeMap = [];   // type slug => id
    private array $countyMap = []; // normalized judet => ['id' => , 'region_id' => ]
    private array $citySlugs = []; // existing/created city slugs (client-scoped) for uniqueness

    public function handle(): int
    {
        $file = $this->option('file')
            ?: base_path('resources/marketplaces/bileteonline/csvs/atractii_romania_final_import.csv');
        $clientId = (int) $this->option('marketplace');
        $limit    = (int) $this->option('limit');
        $offset   = (int) $this->option('offset');
        $doImages = ! $this->option('no-images');
        $forceImg = (bool) $this->option('force-images');
        $createTypes = ! $this->option('no-create-types');
        $dry      = (bool) $this->option('dry-run');

        if (! is_file($file)) {
            $this->error("CSV not found: {$file}");
            return self::FAILURE;
        }

        if ($this->option('report')) {
            return $this->report($file, $clientId);
        }

        if ($this->option('backfill-counties')) {
            return $this->backfillCounties($file, $clientId, $dry);
        }

        if ($this->option('create-cities')) {
            return $this->createCitiesAndRelink($file, $clientId, max(1, (int) $this->option('min-attractions')), $dry);
        }

        if ($this->option('relink-cities')) {
            return $this->relinkCities($file, $clientId, $dry);
        }

        $this->preloadLookups($clientId);
        $this->info('Loaded ' . count($this->cityMap) . ' city keys, ' . count($this->typeMap) . ' type keys for client #' . $clientId . '.');

        $fh = fopen($file, 'r');
        if (! $fh) {
            $this->error('Cannot open CSV.');
            return self::FAILURE;
        }

        // Header (strip UTF-8 BOM from the first cell). escape='' = standard CSV
        // ("" escapes a quote), and silences the PHP 8.4 fgetcsv deprecation.
        $header = fgetcsv($fh, 0, ',', '"', '');
        if (! $header) {
            $this->error('Empty CSV.');
            fclose($fh);
            return self::FAILURE;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $col = array_flip(array_map(fn ($h) => trim((string) $h), $header));

        foreach (['nume', 'slug', 'tip', 'oras'] as $req) {
            if (! isset($col[$req])) {
                $this->error("Missing required column: {$req}");
                fclose($fh);
                return self::FAILURE;
            }
        }

        $get = fn (array $row, string $k) => isset($col[$k]) ? trim((string) ($row[$col[$k]] ?? '')) : '';

        $stats = ['read' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
            'img_ok' => 0, 'img_fail' => 0, 'img_skip' => 0, 'city_miss' => 0, 'type_new' => 0];
        $rowIndex = 0;

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rowIndex++;
            if ($rowIndex <= $offset) continue;
            if ($limit > 0 && $stats['read'] >= $limit) break;
            $stats['read']++;

            $nume = $get($row, 'nume');
            $slug = $get($row, 'slug') ?: Str::slug($nume);
            if ($nume === '' || $slug === '') {
                $stats['skipped']++;
                continue;
            }

            // Resolve type (auto-create when missing + allowed).
            $tip = $get($row, 'tip');
            $typeId = $this->resolveType($tip, $clientId, $createTypes, $dry, $stats);

            // Resolve city (best-effort; null when not present in marketplace_cities).
            $oras = $get($row, 'oras');
            $cityId = $this->resolveCity($oras);
            if ($oras !== '' && $cityId === null) $stats['city_miss']++;

            $numeEn = $get($row, 'nume_en');
            $name = ['ro' => $nume];
            if ($numeEn !== '') $name['en'] = $numeEn;

            $lat = $get($row, 'latitudine');
            $lng = $get($row, 'longitudine');

            $seo = array_filter([
                'title_ro'       => $get($row, 'meta_title'),
                'description_ro' => $get($row, 'meta_description'),
                'keywords_ro'    => $get($row, 'cuvinte_cheie'),
            ], fn ($v) => $v !== '');

            $payload = [
                'attraction_type_id'  => $typeId,
                'marketplace_city_id' => $cityId,
                'name'                => $name,
                'subtitle'            => ($s = $get($row, 'subtitlu')) !== '' ? ['ro' => $s] : null,
                'description'         => ($d = $get($row, 'descriere')) !== '' ? ['ro' => $d] : null,
                'address'             => ($a = $get($row, 'adresa')) !== '' ? $a : null,
                'latitude'            => is_numeric($lat) ? (float) $lat : null,
                'longitude'           => is_numeric($lng) ? (float) $lng : null,
                'seo'                 => $seo ?: null,
                'is_visible'          => true,
            ];

            if ($dry) {
                $stats[Attraction::where('marketplace_client_id', $clientId)->where('slug', $slug)->exists() ? 'updated' : 'created']++;
            } else {
                $existing = Attraction::where('marketplace_client_id', $clientId)->where('slug', $slug)->first();
                $isNew = ! $existing;
                /** @var Attraction $attraction */
                $attraction = Attraction::updateOrCreate(
                    ['marketplace_client_id' => $clientId, 'slug' => $slug],
                    $payload
                );
                $stats[$isNew ? 'created' : 'updated']++;

                // Cover image — only when wanted, present, and (missing or forced).
                if ($doImages) {
                    $imgUrl = $get($row, 'imagine_principala');
                    if ($imgUrl !== '' && ($forceImg || empty($attraction->cover_image_url))) {
                        $path = $this->downloadImage($imgUrl, $slug, $clientId);
                        if ($path) {
                            $attraction->forceFill(['cover_image_url' => $path])->save();
                            $stats['img_ok']++;
                        } else {
                            $stats['img_fail']++;
                        }
                    } else {
                        $stats['img_skip']++;
                    }
                }
            }

            if ($stats['read'] % 100 === 0) {
                $this->line(sprintf(
                    '… %d read | %d new, %d upd | img %d ok/%d fail | %d city-miss',
                    $stats['read'], $stats['created'], $stats['updated'], $stats['img_ok'], $stats['img_fail'], $stats['city_miss']
                ));
            }
        }

        fclose($fh);

        $this->newLine();
        $this->info('Done.');
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($stats['city_miss'] > 0) {
            $this->warn($stats['city_miss'] . ' rows had a city not present in marketplace_cities (saved with city = null).');
        }
        if (! $doImages) {
            $this->warn('Images skipped. Re-run without --no-images (optionally with --force-images) to fetch covers.');
        }

        return self::SUCCESS;
    }

    /**
     * --create-cities pass: create marketplace_cities (is_visible=false) for
     * genuinely-missing localities that have >= $threshold attractions, map
     * `judet` -> existing county, set a centroid lat/lng from the attractions,
     * then relink. Created cities are hidden so /orase stays clean until you
     * review them.
     */
    private function createCitiesAndRelink(string $file, int $clientId, int $threshold, bool $dry): int
    {
        $this->preloadLookups($clientId);
        $this->preloadCounties($clientId);
        $this->info('Loaded ' . count($this->countyMap) . ' county keys; min-attractions = ' . $threshold . ($dry ? ' (dry-run)' : ''));

        // One CSV pass: slug=>oras (for relink) + group genuinely-missing oras.
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $col = array_flip(array_map(fn ($h) => trim((string) $h), $header));

        $slugToOras = [];
        $groups = []; // cityKey => ['name','judet','count','latSum','lngSum','coordN']
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $oras = trim((string) ($row[$col['oras']] ?? ''));
            $slug = trim((string) ($row[$col['slug']] ?? '')) ?: Str::slug(trim((string) ($row[$col['nume']] ?? '')));
            if ($slug !== '' && $oras !== '') $slugToOras[$slug] = $oras;
            if ($oras === '' || $this->resolveCity($oras) !== null) {
                continue; // empty or already an existing city → not missing
            }
            $k = $this->cityKey($oras);
            if ($k === '') continue;
            if (! isset($groups[$k])) {
                $groups[$k] = ['name' => $oras, 'judet' => trim((string) ($row[$col['judet']] ?? '')), 'count' => 0, 'latSum' => 0.0, 'lngSum' => 0.0, 'coordN' => 0];
            }
            $groups[$k]['count']++;
            if ($groups[$k]['judet'] === '' && ($j = trim((string) ($row[$col['judet']] ?? ''))) !== '') {
                $groups[$k]['judet'] = $j;
            }
            $lat = $row[$col['latitudine']] ?? '';
            $lng = $row[$col['longitudine']] ?? '';
            if (is_numeric($lat) && is_numeric($lng)) {
                $groups[$k]['latSum'] += (float) $lat;
                $groups[$k]['lngSum'] += (float) $lng;
                $groups[$k]['coordN']++;
            }
        }
        fclose($fh);

        // Create cities above threshold.
        $created = 0;
        $countyMiss = 0;
        $eligible = array_filter($groups, fn ($g) => $g['count'] >= $threshold);
        uasort($eligible, fn ($a, $b) => $b['count'] <=> $a['count']);
        $this->info(count($eligible) . ' localities meet the threshold (out of ' . count($groups) . ' missing).');

        foreach ($eligible as $g) {
            $county = $this->resolveCounty($g['judet']);
            if ($g['judet'] !== '' && ! $county) $countyMiss++;
            $slug = $this->uniqueCitySlug($g['name'], $g['judet']);
            $lat = $g['coordN'] > 0 ? round($g['latSum'] / $g['coordN'], 7) : null;
            $lng = $g['coordN'] > 0 ? round($g['lngSum'] / $g['coordN'], 7) : null;

            if (! $dry) {
                $city = MarketplaceCity::create([
                    'marketplace_client_id' => $clientId,
                    'name'      => ['ro' => $g['name']],
                    'slug'      => $slug,
                    'county_id' => $county['id'] ?? null,
                    'region_id' => $county['region_id'] ?? null,
                    'country'   => 'RO',
                    'latitude'  => $lat,
                    'longitude' => $lng,
                    'is_visible' => false,
                ]);
                // Register so the relink below + later rows find it.
                $this->cityMap[$this->norm($g['name'])] = $city->id;
                $this->cityMap[Str::slug($g['name'])] = $city->id;
                $this->cityMap[$this->cityKey($g['name'])] = $city->id;
            }
            $created++;
        }

        // Relink null-city attractions (now that new cities exist).
        $recovered = 0;
        $stillNull = 0;
        if (! $dry) {
            Attraction::where('marketplace_client_id', $clientId)
                ->whereNull('marketplace_city_id')
                ->select('id', 'slug')
                ->chunkById(500, function ($chunk) use (&$recovered, &$stillNull, $slugToOras) {
                    foreach ($chunk as $att) {
                        $oras = $slugToOras[$att->slug] ?? '';
                        $cid = $oras !== '' ? $this->resolveCity($oras) : null;
                        if ($cid) {
                            $att->forceFill(['marketplace_city_id' => $cid])->save();
                            $recovered++;
                        } else {
                            $stillNull++;
                        }
                    }
                });
        }

        $this->newLine();
        $this->info(($dry ? '[dry-run] would create ' : 'Created ') . $created . ' cities (is_visible=false)'
            . ($dry ? '.' : "; relinked {$recovered} attractions; {$stillNull} still null."));
        if ($countyMiss > 0) {
            $this->warn($countyMiss . ' created cities had a judet not matched to a county (county_id = null).');
        }
        if (! $dry) {
            $this->line('Review + publish the new cities at /marketplace/cities (filter: not visible).');
        }

        return self::SUCCESS;
    }

    /**
     * --backfill-counties pass: fills marketplace_county_id on attractions that
     * have it null. Prefers the linked city's county; falls back to the CSV
     * `judet` column (mapped to an existing county). No other field touched.
     */
    private function backfillCounties(string $file, int $clientId, bool $dry): int
    {
        $this->preloadCounties($clientId);
        $cityCounty = MarketplaceCity::where('marketplace_client_id', $clientId)->pluck('county_id', 'id')->toArray();

        // slug => judet from CSV.
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $col = array_flip(array_map(fn ($h) => trim((string) $h), $header));
        $slugToJudet = [];
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $slug = trim((string) ($row[$col['slug']] ?? '')) ?: Str::slug(trim((string) ($row[$col['nume']] ?? '')));
            $judet = trim((string) ($row[$col['judet']] ?? ''));
            if ($slug !== '' && $judet !== '') $slugToJudet[$slug] = $judet;
        }
        fclose($fh);

        $fromCity = 0;
        $fromCsv = 0;
        $still = 0;

        Attraction::where('marketplace_client_id', $clientId)
            ->whereNull('marketplace_county_id')
            ->select('id', 'slug', 'marketplace_city_id')
            ->chunkById(500, function ($chunk) use (&$fromCity, &$fromCsv, &$still, $cityCounty, $slugToJudet, $dry) {
                foreach ($chunk as $att) {
                    $cid = null;
                    $src = '';
                    if ($att->marketplace_city_id && ! empty($cityCounty[$att->marketplace_city_id])) {
                        $cid = $cityCounty[$att->marketplace_city_id];
                        $src = 'city';
                    } elseif (($j = $slugToJudet[$att->slug] ?? '') !== '') {
                        $county = $this->resolveCounty($j);
                        if ($county) {
                            $cid = $county['id'];
                            $src = 'csv';
                        }
                    }
                    if ($cid) {
                        if (! $dry) $att->forceFill(['marketplace_county_id' => $cid])->save();
                        $src === 'city' ? $fromCity++ : $fromCsv++;
                    } else {
                        $still++;
                    }
                }
            });

        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '') . "County backfill: {$fromCity} from city, {$fromCsv} from CSV judet, {$still} still null.");

        return self::SUCCESS;
    }

    private function preloadCounties(int $clientId): void
    {
        foreach (MarketplaceCounty::where('marketplace_client_id', $clientId)->get(['id', 'name', 'slug', 'code', 'region_id']) as $c) {
            $val = ['id' => $c->id, 'region_id' => $c->region_id];
            $ro = is_array($c->name) ? ($c->name['ro'] ?? $c->name['en'] ?? '') : (string) $c->name;
            if ($ro !== '') {
                $this->countyMap[$this->cityKey($ro)] = $val;
                $this->countyMap[$this->norm($ro)] = $val;
            }
            if ($c->slug) $this->countyMap[$this->cityKey($c->slug)] = $val;
            if ($c->code) $this->countyMap[$this->cityKey((string) $c->code)] = $val;
        }
    }

    private function resolveCounty(string $judet): ?array
    {
        if ($judet === '') return null;
        return $this->countyMap[$this->cityKey($judet)]
            ?? $this->countyMap[$this->norm($judet)]
            ?? null;
    }

    /**
     * The county seats, so the report can say which of them the source file never mentions.
     * Fifteen of them were missing from the first CSV — Brașov, Sibiu, Iași, Constanța among
     * them — which is not something a per-row importer can notice on its own.
     */
    private const COUNTY_SEATS = [
        'alba' => 'Alba Iulia', 'arad' => 'Arad', 'arges' => 'Pitești', 'bacau' => 'Bacău',
        'bihor' => 'Oradea', 'bistritanasaud' => 'Bistrița', 'botosani' => 'Botoșani',
        'brasov' => 'Brașov', 'braila' => 'Brăila', 'buzau' => 'Buzău', 'carasseverin' => 'Reșița',
        'calarasi' => 'Călărași', 'cluj' => 'Cluj-Napoca', 'constanta' => 'Constanța',
        'covasna' => 'Sfântu Gheorghe', 'dambovita' => 'Târgoviște', 'dolj' => 'Craiova',
        'galati' => 'Galați', 'giurgiu' => 'Giurgiu', 'gorj' => 'Târgu Jiu',
        'harghita' => 'Miercurea Ciuc', 'hunedoara' => 'Deva', 'ialomita' => 'Slobozia',
        'iasi' => 'Iași', 'ilfov' => 'Buftea', 'maramures' => 'Baia Mare',
        'mehedinti' => 'Drobeta-Turnu Severin', 'mures' => 'Târgu Mureș', 'neamt' => 'Piatra Neamț',
        'olt' => 'Slatina', 'prahova' => 'Ploiești', 'satumare' => 'Satu Mare', 'salaj' => 'Zalău',
        'sibiu' => 'Sibiu', 'suceava' => 'Suceava', 'teleorman' => 'Alexandria', 'timis' => 'Timișoara',
        'tulcea' => 'Tulcea', 'vaslui' => 'Vaslui', 'valcea' => 'Râmnicu Vâlcea', 'vrancea' => 'Focșani',
        'bucuresti' => 'București',
    ];

    /**
     * --report: what the CSV covers, what landed, and what is still unlinked. Reads only; run it
     * before and after an import, and again whenever a new source file arrives.
     */
    private function report(string $file, int $clientId): int
    {
        $this->preloadLookups($clientId);

        // ---------------------------------------------------------------- the source file
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $col = array_flip(array_map(fn ($h) => trim((string) $h), $header));

        $rows = 0;
        $byCounty = [];        // county key => rows
        $byLocality = [];      // locality key => ['name','judet','count']
        $seatRows = [];        // county-seat key => rows
        $noCoords = 0;
        $noImage = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rows++;
            $oras  = trim((string) ($row[$col['oras']] ?? ''));
            $judet = trim((string) ($row[$col['judet']] ?? ''));
            $jk = $this->cityKey($judet);
            if ($jk !== '') {
                $byCounty[$jk] = ($byCounty[$jk] ?? 0) + 1;
            }
            $ok = $this->cityKey($oras);
            if ($ok !== '') {
                $seatRows[$ok] = ($seatRows[$ok] ?? 0) + 1;
                if ($this->resolveCity($oras) === null) {
                    $byLocality[$ok] ??= ['name' => $oras, 'judet' => $judet, 'count' => 0];
                    $byLocality[$ok]['count']++;
                }
            }
            $lat = $row[$col['latitudine']] ?? '';
            $lng = $row[$col['longitudine']] ?? '';
            if (!is_numeric($lat) || !is_numeric($lng)) {
                $noCoords++;
            }
            if (trim((string) ($row[$col['imagine_principala']] ?? '')) === '') {
                $noImage++;
            }
        }
        fclose($fh);

        $this->line('');
        $this->info('=== Fișierul sursă ===  ' . basename($file));
        $this->line(sprintf('  %-34s %s', 'rânduri', number_format($rows, 0, ',', '.')));
        $this->line(sprintf('  %-34s %d din 42', 'județe acoperite', count($byCounty)));
        $this->line(sprintf('  %-34s %d', 'localități distincte', count($seatRows)));
        $this->line(sprintf('  %-34s %s', 'fără coordonate', $noCoords));
        $this->line(sprintf('  %-34s %s', 'fără poză', number_format($noImage, 0, ',', '.')));

        // ---------------------------------------------------------------- county seats
        $missingSeats = [];
        foreach (self::COUNTY_SEATS as $ck => $seat) {
            if (($seatRows[$this->cityKey($seat)] ?? 0) === 0) {
                $missingSeats[] = [$seat, $byCounty[$ck] ?? 0];
            }
        }
        usort($missingSeats, fn ($a, $b) => $b[1] <=> $a[1]);
        $this->line('');
        $this->info('=== Reședințe de județ fără nicio atracție în fișier: ' . count($missingSeats) . ' din 42 ===');
        if ($missingSeats) {
            $this->line('  (județul are atracții, dar niciuna chiar în orașul de reședință)');
            foreach ($missingSeats as [$seat, $n]) {
                $this->line(sprintf('  %-26s %5d în județ', $seat, $n));
            }
        }

        // ---------------------------------------------------------------- what landed
        $q = fn () => Attraction::where('marketplace_client_id', $clientId);
        $total = $q()->count();
        $noCity = $q()->whereNull('marketplace_city_id')->count();
        $noCounty = $q()->whereNull('marketplace_county_id')->count();
        $noType = $q()->whereNull('attraction_type_id')->count();
        $noGeo = $q()->where(fn ($w) => $w->whereNull('latitude')->orWhereNull('longitude'))->count();
        $noCover = $q()->where(fn ($w) => $w->whereNull('cover_image_url')->orWhere('cover_image_url', ''))->count();
        $pc = fn (int $n) => $total ? sprintf('%5.1f%%', 100 * $n / $total) : '    —';

        $this->line('');
        $this->info('=== În baza de date (client #' . $clientId . ') ===');
        $this->line(sprintf('  %-34s %s', 'atracții', number_format($total, 0, ',', '.')));
        $this->line(sprintf('  %-34s %6s  %s', 'fără oraș', number_format($noCity, 0, ',', '.'), $pc($noCity)));
        $this->line(sprintf('  %-34s %6s  %s', 'fără județ', number_format($noCounty, 0, ',', '.'), $pc($noCounty)));
        $this->line(sprintf('  %-34s %6s  %s', 'fără tip', number_format($noType, 0, ',', '.'), $pc($noType)));
        $this->line(sprintf('  %-34s %6s  %s', 'fără coordonate', number_format($noGeo, 0, ',', '.'), $pc($noGeo)));
        $this->line(sprintf('  %-34s %6s  %s', 'fără poză de copertă', number_format($noCover, 0, ',', '.'), $pc($noCover)));
        if ($total !== $rows) {
            $this->warn('  Atenție: ' . number_format(abs($total - $rows), 0, ',', '.') . ' ' .
                ($total < $rows ? 'rânduri din fișier nu au ajuns în baza de date.' : 'atracții în plus față de fișier (alt import?).'));
        }

        // ---------------------------------------------------------------- localities with no city row
        uasort($byLocality, fn ($a, $b) => $b['count'] <=> $a['count']);
        $buckets = [];
        foreach ($byLocality as $g) {
            $b = $g['count'] >= 10 ? '10+' : ($g['count'] >= 4 ? '4–9' : ($g['count'] >= 2 ? '2–3' : '1'));
            $buckets[$b] = ($buckets[$b] ?? 0) + 1;
        }
        $stranded = array_sum(array_column($byLocality, 'count'));
        $this->line('');
        $this->info('=== Localități din fișier care nu există în marketplace_cities: ' . count($byLocality) . ' ===');
        $this->line('  ' . number_format($stranded, 0, ',', '.') . ' atracții rămân fără oraș din cauza lor.');
        foreach (['10+', '4–9', '2–3', '1'] as $b) {
            if (!empty($buckets[$b])) {
                $this->line(sprintf('  %-12s %5d localități', $b . ' atracții', $buckets[$b]));
            }
        }
        $top = array_slice($byLocality, 0, 15, true);
        if ($top) {
            $this->line('  cele mai mari:');
            foreach ($top as $g) {
                $this->line(sprintf('    %-30s %4d   (%s)', mb_substr($g['name'], 0, 30), $g['count'], $g['judet'] ?: '?'));
            }
        }
        $this->line('');
        $this->line('  Ca să le legi pe toate, cu orașe invizibile create din fișier:');
        $this->line('    php artisan import:bilete-attractions --create-cities --min-attractions=1 --dry-run');
        $this->line('');

        return self::SUCCESS;
    }

    private function uniqueCitySlug(string $name, string $judet): string
    {
        $base = Str::slug($name) ?: 'oras';
        $slug = $base;
        if (isset($this->citySlugs[$slug]) && $judet !== '') {
            $slug = $base . '-' . Str::slug($judet);
        }
        $i = 2;
        while (isset($this->citySlugs[$slug])) {
            $slug = $base . '-' . $i++;
        }
        $this->citySlugs[$slug] = true;
        return $slug;
    }

    private function preloadLookups(int $clientId): void
    {
        foreach (MarketplaceCity::where('marketplace_client_id', $clientId)->get(['id', 'name', 'slug']) as $c) {
            $ro = is_array($c->name) ? ($c->name['ro'] ?? $c->name['en'] ?? '') : (string) $c->name;
            if ($ro !== '') {
                $this->cityMap[$this->norm($ro)] = $c->id;
                $this->cityMap[Str::slug($ro)] = $c->id;
                $this->cityMap[$this->cityKey($ro)] = $c->id; // aggressive (â/î, separators)
            }
            if ($c->slug) {
                $this->cityMap[$c->slug] = $c->id;
                $this->cityMap[$this->cityKey($c->slug)] = $c->id;
                $this->citySlugs[$c->slug] = true;
            }
        }

        foreach (AttractionType::where('marketplace_client_id', $clientId)->get(['id', 'name', 'slug']) as $t) {
            $ro = is_array($t->name) ? ($t->name['ro'] ?? $t->name['en'] ?? '') : (string) $t->name;
            if ($t->slug) $this->typeMap[$t->slug] = $t->id;
            if ($ro !== '') $this->typeMap[Str::slug($ro)] = $t->id;
        }
    }

    private function norm(string $s): string
    {
        return mb_strtolower(trim($s), 'UTF-8');
    }

    /**
     * Aggressive city key for recovery matching: lowercase, fold Romanian
     * diacritics (â and î both → 'a' so Târgu == Tîrgu), drop admin prefixes
     * (municipiul/orașul/comuna/satul/sectorul), then strip every non-alnum so
     * "Cluj-Napoca" == "Cluj Napoca". Lossy by design — used only as a fallback.
     */
    private function cityKey(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, [
            'ă' => 'a', 'â' => 'a', 'î' => 'a',
            'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ö' => 'o',
        ]);
        $s = preg_replace('/\b(municipiul|orasul|comuna|satul|sectorul|sector)\b/u', ' ', $s);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        return $s;
    }

    private function resolveCity(string $oras): ?int
    {
        if ($oras === '') return null;
        return $this->cityMap[$this->norm($oras)]
            ?? $this->cityMap[Str::slug($oras)]
            ?? $this->cityMap[$this->cityKey($oras)]
            ?? null;
    }

    /**
     * --relink-cities pass: only fills marketplace_city_id on attractions that
     * currently have it null, using the aggressive matcher. Creates no cities,
     * touches no other field. Reports recovered + the top genuinely-missing
     * city names so you can decide which (if any) to add by hand.
     */
    private function relinkCities(string $file, int $clientId, bool $dry): int
    {
        $this->preloadLookups($clientId);
        $this->info('Loaded ' . count($this->cityMap) . ' city keys for client #' . $clientId . '.');

        // slug => oras from the CSV.
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $col = array_flip(array_map(fn ($h) => trim((string) $h), $header));
        $slugToOras = [];
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $slug = trim((string) ($row[$col['slug']] ?? '')) ?: Str::slug(trim((string) ($row[$col['nume']] ?? '')));
            $oras = trim((string) ($row[$col['oras']] ?? ''));
            if ($slug !== '' && $oras !== '') $slugToOras[$slug] = $oras;
        }
        fclose($fh);

        $recovered = 0;
        $stillNull = 0;
        $remaining = [];

        Attraction::where('marketplace_client_id', $clientId)
            ->whereNull('marketplace_city_id')
            ->select('id', 'slug')
            ->chunkById(500, function ($chunk) use (&$recovered, &$stillNull, &$remaining, $slugToOras, $dry) {
                foreach ($chunk as $att) {
                    $oras = $slugToOras[$att->slug] ?? '';
                    if ($oras === '') {
                        $stillNull++;
                        continue;
                    }
                    $cid = $this->resolveCity($oras);
                    if ($cid) {
                        if (! $dry) {
                            $att->forceFill(['marketplace_city_id' => $cid])->save();
                        }
                        $recovered++;
                    } else {
                        $stillNull++;
                        $remaining[$oras] = ($remaining[$oras] ?? 0) + 1;
                    }
                }
            });

        arsort($remaining);
        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '') . "Recovered {$recovered} city links; {$stillNull} still null ("
            . count($remaining) . ' distinct cities genuinely missing from marketplace_cities).');
        if ($remaining) {
            $this->line('Top genuinely-missing cities (name × attractions):');
            $i = 0;
            foreach ($remaining as $o => $n) {
                $this->line("  • {$o} ({$n})");
                if (++$i >= 30) {
                    $this->line('  …');
                    break;
                }
            }
        }

        return self::SUCCESS;
    }

    private function resolveType(string $tip, int $clientId, bool $createTypes, bool $dry, array &$stats): ?int
    {
        if ($tip === '') return null;
        $slug = Str::slug($tip);
        if (isset($this->typeMap[$slug])) return $this->typeMap[$slug];
        if (! $createTypes || $dry) {
            return null;
        }
        $type = AttractionType::create([
            'marketplace_client_id' => $clientId,
            'slug'                  => $slug,
            'name'                  => ['ro' => $tip],
            'is_visible'            => true,
        ]);
        $this->typeMap[$slug] = $type->id;
        $stats['type_new']++;
        return $type->id;
    }

    private function downloadImage(string $url, string $slug, int $clientId): ?string
    {
        try {
            $resp = Http::withHeaders([
                'User-Agent' => 'TixelloBot/1.0 (+https://bilete.online; attractions import)',
                'Accept'     => 'image/*',
            ])->timeout(25)->retry(1, 250)->get($url);

            if (! $resp->ok()) return null;

            $body = $resp->body();
            if (strlen($body) < 200) return null; // error page / empty

            $ct = strtolower((string) $resp->header('Content-Type'));
            if ($ct !== '' && ! str_contains($ct, 'image')) return null;

            $ext = match (true) {
                str_contains($ct, 'jpeg'), str_contains($ct, 'jpg') => 'jpg',
                str_contains($ct, 'png')  => 'png',
                str_contains($ct, 'webp') => 'webp',
                str_contains($ct, 'svg')  => 'svg',
                str_contains($ct, 'gif')  => 'gif',
                default => $this->extFromUrl($url),
            };

            $path = "attractions/covers/{$clientId}/" . Str::slug($slug) . '.' . $ext;
            Storage::disk('public')->put($path, $body);
            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extFromUrl(string $url): string
    {
        $p = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $p = $p === 'jpeg' ? 'jpg' : $p;
        return in_array($p, ['jpg', 'png', 'webp', 'svg', 'gif'], true) ? $p : 'jpg';
    }
}
