<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Locations
{
    /** In-memory cache per request */
    protected static array $mem = [
        'countries' => null,
        'states'    => [],   // [countryKey => [...]]
        'cities'    => [],   // [countryKey][stateSlug] => [...]
    ];

    /** Alias-uri pentru găsirea folderelor de țară */
    protected static array $countryAliases = [
        'ro' => ['ro','romania'],
        'ae' => ['ae','uae','united-arab-emirates','united arab emirates'],
        // adaugă alte aliasuri dacă ai nevoie
    ];

    /** Sinonime acceptate pentru fișierul listelor de subdiviziuni */
    protected static array $stateFiles = ['states.php','counties.php','provinces.php','regions.php'];

    /**
     * Returnează lista țărilor (valoare = etichetă), sortată alfabetic.
     * Citește resources/data/countries.php dacă există (listă simplă de nume).
     */
    public static function countries(): array
    {
        if (self::$mem['countries'] !== null) {
            return self::$mem['countries'];
        }

        $path = resource_path('data/countries.php');
        $list = file_exists($path) ? include $path : [];

        // normalizăm în map [name => name]
        $countries = [];
        foreach ($list as $name) {
            $name = (string) $name;
            $countries[$name] = $name;
        }

        ksort($countries, SORT_NATURAL | SORT_FLAG_CASE);
        return self::$mem['countries'] = $countries;
    }

    /**
     * Returnează lista de state/județe pentru o țară.
     * $countryKey poate fi "ro", "Romania", "united-arab-emirates" etc.
     */
    public static function states(string $countryKey): array
    {
        $folder = self::resolveCountryFolder($countryKey);
        $cacheKey = "loc:states:$folder";

        if (isset(self::$mem['states'][$folder])) {
            return self::$mem['states'][$folder];
        }

        $result = Cache::rememberForever(self::cacheKeyWithMTime($cacheKey, self::probeStatesFilePath($folder)), function () use ($folder) {
            $file = self::probeStatesFilePath($folder);
            if (! $file) return [];

            $data = include $file;
            $out  = [];

            foreach ((array) $data as $name) {
                $name = (string) $name;
                if ($name !== '') {
                    $out[$name] = $name;
                }
            }
            ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
            return $out;
        });

        return self::$mem['states'][$folder] = $result;
    }

    /**
     * Returnează orașele pentru o țară + stat/județ.
     * - Dacă $detailed = true → întoarce array cu ['id','name','latitude','longitude'] (dacă există).
     * - Dacă $detailed = false → întoarce map simplu [name => name].
     */
    public static function cities(string $countryKey, string $stateName, bool $detailed = false): array
    {
        $folder    = self::resolveCountryFolder($countryKey);
        $stateSlug = self::slug($stateName);
        $file      = self::resolveCityFilePath($folder, $stateName); // <— nou

        $cacheKey = "loc:cities:$folder:$stateSlug";

        if (isset(self::$mem['cities'][$folder][$stateSlug][$detailed ? 'd' : 's'])) {
            return self::$mem['cities'][$folder][$stateSlug][$detailed ? 'd' : 's'];
        }

        $result = \Illuminate\Support\Facades\Cache::rememberForever(
            self::cacheKeyWithMTime($cacheKey, $file), // <— cache „auto-bust” pe mtime
            function () use ($file, $detailed) {
                if (! $file || ! file_exists($file)) return [];

                $raw = include $file;

                if (! $detailed) {
                    $names = [];
                    foreach ((array) $raw as $row) {
                        $name = is_array($row) ? (string) ($row['name'] ?? '') : (string) $row;
                        if ($name !== '') {
                            $names[$name] = $name;
                        }
                    }
                    ksort($names, SORT_NATURAL | SORT_FLAG_CASE);
                    return $names;
                }

                $rows = [];
                foreach ((array) $raw as $row) {
                    if (is_array($row)) {
                        $rows[] = [
                            'id'        => \Illuminate\Support\Arr::get($row, 'id'),
                            'name'      => (string) \Illuminate\Support\Arr::get($row, 'name', ''),
                            'latitude'  => self::toFloat(\Illuminate\Support\Arr::get($row, 'latitude')),
                            'longitude' => self::toFloat(\Illuminate\Support\Arr::get($row, 'longitude')),
                        ];
                    } else {
                        $rows[] = ['id'=>null,'name'=>(string)$row,'latitude'=>null,'longitude'=>null];
                    }
                }
                usort($rows, fn($a,$b) => strnatcasecmp($a['name'], $b['name']));
                return $rows;
            }
        );

        self::$mem['cities'][$folder][$stateSlug][$detailed ? 'd' : 's'] = $result;
        return $result;
    }

    /**
     * 🔧 Helper pentru Filament: options() simplu pentru orașe (nume => nume)
     */
    public static function cityOptions(string $countryKey, string $stateName): array
    {
        return self::cities($countryKey, $stateName, false);
    }

    // ----------------- internals -----------------

    protected static function resolveCountryFolder(string $key): string
    {
        $k = strtolower(trim($key));

        // dacă există folder exact, îl folosim
        $direct = resource_path("data/{$k}");
        if (is_dir($direct)) return $k;

        // map aliasuri
        foreach (self::$countryAliases as $folder => $aliases) {
            if (in_array($k, $aliases, true)) return $folder;
            // încearcă și slug-ul numelui țării
            if (in_array(self::slug($k), $aliases, true)) return $folder;
        }

        // fallback: slug ca nume de folder
        $slugFolder = self::slug($k);
        if (is_dir(resource_path("data/{$slugFolder}"))) return $slugFolder;

        // ultimul fallback: folosim exact cheia (poate ai alt naming)
        return $k;
    }

    protected static function probeStatesFilePath(string $folder): ?string
    {
        foreach (self::$stateFiles as $fname) {
            $p = resource_path("data/{$folder}/{$fname}");
            if (file_exists($p)) return $p;
        }
        return null;
    }

    protected static function slug(string $value): string
    {
        $v = trim($value);
        // încearcă Str::slug cu locale ro, apoi fallback
        try {
            $s = \Illuminate\Support\Str::slug($v, '-', 'ro');
            if ($s !== '') return $s;
        } catch (\Throwable $e) { /* ignore */ }

        $v = str_replace(["’","‘","`","´","ʼ"], "'", $v);
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
        $v = strtolower($v);
        $v = preg_replace('~[^a-z0-9]+~', '-', $v);
        return trim($v, '-');
    }

    protected static function toFloat($val): ?float
    {
        if ($val === null || $val === '') return null;
        if (is_float($val) || is_int($val)) return (float) $val;
        // înlocuiește virgula și convertește
        $val = str_replace(',', '.', (string) $val);
        return is_numeric($val) ? (float) $val : null;
    }

    /**
     * Cheie de cache „auto-bust” bazată pe mtime-ul fișierului.
     */
    protected static function cacheKeyWithMTime(string $base, ?string $filePath): string
    {
        if ($filePath && file_exists($filePath)) {
            return $base . ':m' . filemtime($filePath);
        }
        // pentru fișiere de orașe pe care le includem direct:
        if ($filePath && is_string($filePath)) {
            return $base . ':m0';
        }
        return $base . ':m0';
    }

    protected static function resolveCityFilePath(string $folder, string $stateName): ?string
    {
        $dir  = resource_path("data/{$folder}/cities");
        if (! is_dir($dir)) {
            return null;
        }

        $wanted = self::slug($stateName);

        // 1) Încercare directă (nume deja „slug”)
        $direct = "{$dir}/{$wanted}.php";
        if (file_exists($direct)) {
            return $direct;
        }

        // 2) Scanare tolerantă: găsim fișierul al cărui nume slugificat == $wanted
        foreach (glob($dir . '/*.php') as $path) {
            $base = pathinfo($path, PATHINFO_FILENAME); // poate conține diacritice
            if (self::slug($base) === $wanted) {
                return $path;
            }
        }

        return null;
    }
}
