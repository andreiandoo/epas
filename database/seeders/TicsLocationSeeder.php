<?php

namespace Database\Seeders;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
use Illuminate\Database\Seeder;

class TicsLocationSeeder extends Seeder
{
    /**
     * Seed locations for TICS marketplace (default id=2).
     * Countries: Romania (RO), Moldova (MD), Hungary (HU), Bulgaria (BG)
     *
     * Usage: MARKETPLACE_ID=2 php artisan db:seed --class=TicsLocationSeeder
     *
     * Hierarchy: Region → County/District/Province → City
     */
    public function run(): void
    {
        $mpcId = (int) env('MARKETPLACE_ID', 2);

        $this->command->info("Seeding TICS locations (marketplace #{$mpcId})...");

        $this->seedEntries($mpcId, 'RO', 'Europe/Bucharest', $this->getRoRegions(), $this->getRoCounties());
        $this->seedEntries($mpcId, 'MD', 'Europe/Chisinau',  $this->getMdRegions(), $this->getMdCounties());
        $this->seedEntries($mpcId, 'HU', 'Europe/Budapest',  $this->getHuRegions(), $this->getHuCounties());
        $this->seedEntries($mpcId, 'BG', 'Europe/Sofia',     $this->getBgRegions(), $this->getBgCounties());

        $this->command->info('TICS location seeding complete!');
    }

    /* =========================================================================
     * Shared Helper
     * ===================================================================== */

    private function seedEntries(int $mpcId, string $country, string $tz, array $regDefs, array $countyDefs): void
    {
        $this->command->info("  -> {$country}");
        $regionMap = [];

        foreach ($regDefs as $i => $r) {
            $region = MarketplaceRegion::updateOrCreate(
                ['marketplace_client_id' => $mpcId, 'slug' => $r['slug']],
                [
                    'name'        => $r['name'],
                    'code'        => $r['code'],
                    'country'     => $country,
                    'sort_order'  => $i + 1,
                    'is_visible'  => true,
                    'is_featured' => $r['featured'] ?? false,
                ]
            );
            $regionMap[$r['code']] = $region;
        }

        foreach ($countyDefs as $c) {
            $region = $regionMap[$c['region']] ?? null;

            $county = MarketplaceCounty::updateOrCreate(
                ['marketplace_client_id' => $mpcId, 'code' => $c['code']],
                [
                    'region_id'   => $region?->id,
                    'name'        => $c['name'],
                    'slug'        => $c['slug'],
                    'country'     => $country,
                    'sort_order'  => $c['sort'] ?? 0,
                    'is_visible'  => true,
                    'is_featured' => $c['featured'] ?? false,
                ]
            );

            $citySort = 0;
            foreach ($c['cities'] as $city) {
                $citySort++;
                MarketplaceCity::updateOrCreate(
                    ['marketplace_client_id' => $mpcId, 'slug' => $city['slug']],
                    [
                        'county_id'   => $county->id,
                        'region_id'   => $region?->id,
                        'name'        => $city['name'],
                        'country'     => $country,
                        'latitude'    => $city['lat']  ?? null,
                        'longitude'   => $city['lng']  ?? null,
                        'timezone'    => $tz,
                        'population'  => $city['population'] ?? null,
                        'sort_order'  => $citySort,
                        'is_visible'  => true,
                        'is_featured' => $city['featured'] ?? false,
                        'is_capital'  => $city['capital'] ?? false,
                    ]
                );
            }

            $county->update(['city_count' => count($c['cities'])]);
        }
    }

    /* =========================================================================
     * ROMANIA
     * ===================================================================== */

    private function getRoRegions(): array
    {
        return [
            ['slug' => 'ro-transilvania', 'name' => ['ro' => 'Transilvania', 'en' => 'Transylvania'], 'code' => 'RO-TR', 'featured' => true],
            ['slug' => 'ro-muntenia',     'name' => ['ro' => 'Muntenia',     'en' => 'Wallachia'],    'code' => 'RO-MN', 'featured' => true],
            ['slug' => 'ro-moldova',      'name' => ['ro' => 'Moldova',      'en' => 'Moldavia'],     'code' => 'RO-MD', 'featured' => true],
            ['slug' => 'ro-dobrogea',     'name' => ['ro' => 'Dobrogea',     'en' => 'Dobruja'],      'code' => 'RO-DB', 'featured' => true],
            ['slug' => 'ro-banat',        'name' => ['ro' => 'Banat',        'en' => 'Banat'],        'code' => 'RO-BN', 'featured' => true],
            ['slug' => 'ro-oltenia',      'name' => ['ro' => 'Oltenia',      'en' => 'Oltenia'],      'code' => 'RO-OL', 'featured' => true],
            ['slug' => 'ro-crisana',      'name' => ['ro' => 'Crișana',      'en' => 'Crisana'],      'code' => 'RO-CR', 'featured' => false],
            ['slug' => 'ro-maramures',    'name' => ['ro' => 'Maramureș',    'en' => 'Maramures'],    'code' => 'RO-MM', 'featured' => false],
        ];
    }

    private function getRoCounties(): array
    {
        return [
            // ---- MUNTENIA ----
            [
                'code' => 'RO-B', 'region' => 'RO-MN', 'slug' => 'ro-bucuresti', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'București', 'en' => 'Bucharest'],
                'cities' => [
                    ['slug' => 'ro-bucuresti', 'name' => ['ro' => 'București', 'en' => 'Bucharest'], 'lat' => 44.4268, 'lng' => 26.1025, 'population' => 1883425, 'featured' => true, 'capital' => true],
                ],
            ],
            [
                'code' => 'RO-IF', 'region' => 'RO-MN', 'slug' => 'ro-ilfov', 'sort' => 2,
                'name' => ['ro' => 'Ilfov', 'en' => 'Ilfov'],
                'cities' => [
                    ['slug' => 'ro-voluntari',        'name' => ['ro' => 'Voluntari',        'en' => 'Voluntari'],        'lat' => 44.4900, 'lng' => 26.1833, 'population' => 42944, 'capital' => true],
                    ['slug' => 'ro-popesti-leordeni', 'name' => ['ro' => 'Popești-Leordeni', 'en' => 'Popesti-Leordeni'],'lat' => 44.3833, 'lng' => 26.1667, 'population' => 39667],
                    ['slug' => 'ro-buftea',           'name' => ['ro' => 'Buftea',           'en' => 'Buftea'],           'lat' => 44.5667, 'lng' => 25.9500, 'population' => 22178],
                    ['slug' => 'ro-otopeni',          'name' => ['ro' => 'Otopeni',          'en' => 'Otopeni'],          'lat' => 44.5500, 'lng' => 26.0833, 'population' => 17594],
                    ['slug' => 'ro-bragadiru',        'name' => ['ro' => 'Bragadiru',        'en' => 'Bragadiru'],        'lat' => 44.3667, 'lng' => 25.9833, 'population' => 22529],
                ],
            ],
            [
                'code' => 'RO-PH', 'region' => 'RO-MN', 'slug' => 'ro-prahova', 'sort' => 3,
                'name' => ['ro' => 'Prahova', 'en' => 'Prahova'],
                'cities' => [
                    ['slug' => 'ro-ploiesti',  'name' => ['ro' => 'Ploiești',  'en' => 'Ploiesti'],  'lat' => 44.9500, 'lng' => 26.0167, 'population' => 209945, 'capital' => true],
                    ['slug' => 'ro-campina',   'name' => ['ro' => 'Câmpina',   'en' => 'Campina'],   'lat' => 45.1167, 'lng' => 25.7333, 'population' => 33641],
                    ['slug' => 'ro-sinaia',    'name' => ['ro' => 'Sinaia',    'en' => 'Sinaia'],    'lat' => 45.3500, 'lng' => 25.5500, 'population' => 10310, 'featured' => true],
                    ['slug' => 'ro-busteni',   'name' => ['ro' => 'Bușteni',   'en' => 'Busteni'],   'lat' => 45.4167, 'lng' => 25.5500, 'population' => 9154,  'featured' => true],
                    ['slug' => 'ro-azuga',     'name' => ['ro' => 'Azuga',     'en' => 'Azuga'],     'lat' => 45.4500, 'lng' => 25.5833, 'population' => 4626],
                    ['slug' => 'ro-breaza',    'name' => ['ro' => 'Breaza',    'en' => 'Breaza'],    'lat' => 45.1833, 'lng' => 25.6667, 'population' => 15902],
                ],
            ],
            [
                'code' => 'RO-AG', 'region' => 'RO-MN', 'slug' => 'ro-arges', 'sort' => 4,
                'name' => ['ro' => 'Argeș', 'en' => 'Arges'],
                'cities' => [
                    ['slug' => 'ro-pitesti',          'name' => ['ro' => 'Pitești',          'en' => 'Pitesti'],          'lat' => 44.8667, 'lng' => 24.8667, 'population' => 155383, 'capital' => true],
                    ['slug' => 'ro-campulung',        'name' => ['ro' => 'Câmpulung',        'en' => 'Campulung'],        'lat' => 45.2667, 'lng' => 25.0500, 'population' => 34034],
                    ['slug' => 'ro-curtea-de-arges',  'name' => ['ro' => 'Curtea de Argeș',  'en' => 'Curtea de Arges'],  'lat' => 45.1333, 'lng' => 24.6833, 'population' => 27559, 'featured' => true],
                    ['slug' => 'ro-mioveni',          'name' => ['ro' => 'Mioveni',          'en' => 'Mioveni'],          'lat' => 44.9667, 'lng' => 24.9500, 'population' => 33306],
                ],
            ],
            [
                'code' => 'RO-DB', 'region' => 'RO-MN', 'slug' => 'ro-dambovita', 'sort' => 5,
                'name' => ['ro' => 'Dâmbovița', 'en' => 'Dambovita'],
                'cities' => [
                    ['slug' => 'ro-targoviste', 'name' => ['ro' => 'Târgoviște', 'en' => 'Targoviste'], 'lat' => 44.9333, 'lng' => 25.4500, 'population' => 79610, 'capital' => true],
                    ['slug' => 'ro-moreni',     'name' => ['ro' => 'Moreni',     'en' => 'Moreni'],     'lat' => 44.9833, 'lng' => 25.6500, 'population' => 18214],
                    ['slug' => 'ro-pucioasa',   'name' => ['ro' => 'Pucioasa',   'en' => 'Pucioasa'],   'lat' => 45.0667, 'lng' => 25.4333, 'population' => 14294],
                ],
            ],
            [
                'code' => 'RO-BZ', 'region' => 'RO-MN', 'slug' => 'ro-buzau', 'sort' => 6,
                'name' => ['ro' => 'Buzău', 'en' => 'Buzau'],
                'cities' => [
                    ['slug' => 'ro-buzau',          'name' => ['ro' => 'Buzău',          'en' => 'Buzau'],          'lat' => 45.1500, 'lng' => 26.8333, 'population' => 115494, 'capital' => true],
                    ['slug' => 'ro-ramnicu-sarat',  'name' => ['ro' => 'Râmnicu Sărat',  'en' => 'Ramnicu Sarat'],  'lat' => 45.3833, 'lng' => 27.0500, 'population' => 33911],
                ],
            ],
            [
                'code' => 'RO-GR', 'region' => 'RO-MN', 'slug' => 'ro-giurgiu', 'sort' => 7,
                'name' => ['ro' => 'Giurgiu', 'en' => 'Giurgiu'],
                'cities' => [
                    ['slug' => 'ro-giurgiu',       'name' => ['ro' => 'Giurgiu',       'en' => 'Giurgiu'],       'lat' => 43.9000, 'lng' => 25.9667, 'population' => 61353, 'capital' => true],
                    ['slug' => 'ro-bolintin-vale', 'name' => ['ro' => 'Bolintin-Vale', 'en' => 'Bolintin-Vale'], 'lat' => 44.4333, 'lng' => 25.7500, 'population' => 11753],
                ],
            ],
            [
                'code' => 'RO-CL', 'region' => 'RO-MN', 'slug' => 'ro-calarasi', 'sort' => 8,
                'name' => ['ro' => 'Călărași', 'en' => 'Calarasi'],
                'cities' => [
                    ['slug' => 'ro-calarasi', 'name' => ['ro' => 'Călărași', 'en' => 'Calarasi'], 'lat' => 44.2000, 'lng' => 27.3333, 'population' => 65181, 'capital' => true],
                    ['slug' => 'ro-oltenita', 'name' => ['ro' => 'Oltenița', 'en' => 'Oltenita'], 'lat' => 44.0833, 'lng' => 26.6333, 'population' => 24822],
                ],
            ],
            [
                'code' => 'RO-IL', 'region' => 'RO-MN', 'slug' => 'ro-ialomita', 'sort' => 9,
                'name' => ['ro' => 'Ialomița', 'en' => 'Ialomita'],
                'cities' => [
                    ['slug' => 'ro-slobozia', 'name' => ['ro' => 'Slobozia', 'en' => 'Slobozia'], 'lat' => 44.5667, 'lng' => 27.3667, 'population' => 52693, 'capital' => true],
                    ['slug' => 'ro-fetesti',  'name' => ['ro' => 'Fetești',  'en' => 'Fetesti'],  'lat' => 44.3833, 'lng' => 27.8333, 'population' => 30223],
                    ['slug' => 'ro-urziceni', 'name' => ['ro' => 'Urziceni', 'en' => 'Urziceni'], 'lat' => 44.7167, 'lng' => 26.6333, 'population' => 17404],
                ],
            ],
            [
                'code' => 'RO-TR', 'region' => 'RO-MN', 'slug' => 'ro-teleorman', 'sort' => 10,
                'name' => ['ro' => 'Teleorman', 'en' => 'Teleorman'],
                'cities' => [
                    ['slug' => 'ro-alexandria',      'name' => ['ro' => 'Alexandria',      'en' => 'Alexandria'],      'lat' => 43.9667, 'lng' => 25.3333, 'population' => 45434, 'capital' => true],
                    ['slug' => 'ro-rosiori-de-vede', 'name' => ['ro' => 'Roșiori de Vede', 'en' => 'Rosiori de Vede'], 'lat' => 44.1000, 'lng' => 24.9833, 'population' => 27416],
                    ['slug' => 'ro-turnu-magurele',  'name' => ['ro' => 'Turnu Măgurele',  'en' => 'Turnu Magurele'],  'lat' => 43.7500, 'lng' => 24.8833, 'population' => 26000],
                ],
            ],
            // ---- TRANSILVANIA ----
            [
                'code' => 'RO-CJ', 'region' => 'RO-TR', 'slug' => 'ro-cluj', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Cluj', 'en' => 'Cluj'],
                'cities' => [
                    ['slug' => 'ro-cluj-napoca',   'name' => ['ro' => 'Cluj-Napoca',   'en' => 'Cluj-Napoca'],   'lat' => 46.7712, 'lng' => 23.6236, 'population' => 324576, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-turda',         'name' => ['ro' => 'Turda',         'en' => 'Turda'],         'lat' => 46.5667, 'lng' => 23.7833, 'population' => 47744],
                    ['slug' => 'ro-dej',           'name' => ['ro' => 'Dej',           'en' => 'Dej'],           'lat' => 47.1500, 'lng' => 23.8833, 'population' => 33497],
                    ['slug' => 'ro-campia-turzii', 'name' => ['ro' => 'Câmpia Turzii', 'en' => 'Campia Turzii'], 'lat' => 46.5500, 'lng' => 23.8833, 'population' => 23904],
                    ['slug' => 'ro-gherla',        'name' => ['ro' => 'Gherla',        'en' => 'Gherla'],        'lat' => 47.0333, 'lng' => 23.9000, 'population' => 18162],
                    ['slug' => 'ro-floresti-cj',   'name' => ['ro' => 'Florești',      'en' => 'Floresti'],      'lat' => 46.7500, 'lng' => 23.4833, 'population' => 32000],
                ],
            ],
            [
                'code' => 'RO-BV', 'region' => 'RO-TR', 'slug' => 'ro-brasov', 'featured' => true, 'sort' => 2,
                'name' => ['ro' => 'Brașov', 'en' => 'Brasov'],
                'cities' => [
                    ['slug' => 'ro-brasov',        'name' => ['ro' => 'Brașov',        'en' => 'Brasov'],        'lat' => 45.6427, 'lng' => 25.5887, 'population' => 253200, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-fagaras',        'name' => ['ro' => 'Făgăraș',       'en' => 'Fagaras'],       'lat' => 45.8500, 'lng' => 24.9667, 'population' => 30714],
                    ['slug' => 'ro-sacele',         'name' => ['ro' => 'Săcele',         'en' => 'Sacele'],        'lat' => 45.6167, 'lng' => 25.6833, 'population' => 27033],
                    ['slug' => 'ro-rasnov',         'name' => ['ro' => 'Râșnov',         'en' => 'Rasnov'],        'lat' => 45.5833, 'lng' => 25.4667, 'population' => 15022],
                    ['slug' => 'ro-predeal',        'name' => ['ro' => 'Predeal',        'en' => 'Predeal'],       'lat' => 45.5000, 'lng' => 25.5833, 'population' => 4594,  'featured' => true],
                    ['slug' => 'ro-poiana-brasov',  'name' => ['ro' => 'Poiana Brașov',  'en' => 'Poiana Brasov'], 'lat' => 45.6000, 'lng' => 25.5500, 'featured' => true],
                    ['slug' => 'ro-bran',           'name' => ['ro' => 'Bran',           'en' => 'Bran'],          'lat' => 45.5167, 'lng' => 25.3667, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-SB', 'region' => 'RO-TR', 'slug' => 'ro-sibiu', 'featured' => true, 'sort' => 3,
                'name' => ['ro' => 'Sibiu', 'en' => 'Sibiu'],
                'cities' => [
                    ['slug' => 'ro-sibiu',     'name' => ['ro' => 'Sibiu',     'en' => 'Sibiu'],     'lat' => 45.7928, 'lng' => 24.1519, 'population' => 147245, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-medias',    'name' => ['ro' => 'Mediaș',    'en' => 'Medias'],    'lat' => 46.1667, 'lng' => 24.3500, 'population' => 51135],
                    ['slug' => 'ro-cisnadie',  'name' => ['ro' => 'Cisnădie',  'en' => 'Cisnadie'],  'lat' => 45.7167, 'lng' => 24.1500, 'population' => 14322],
                    ['slug' => 'ro-paltinis',  'name' => ['ro' => 'Păltiniș',  'en' => 'Paltinis'],  'lat' => 45.6667, 'lng' => 23.9333, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-MS', 'region' => 'RO-TR', 'slug' => 'ro-mures', 'sort' => 4,
                'name' => ['ro' => 'Mureș', 'en' => 'Mures'],
                'cities' => [
                    ['slug' => 'ro-targu-mures', 'name' => ['ro' => 'Târgu Mureș', 'en' => 'Targu Mures'], 'lat' => 46.5386, 'lng' => 24.5513, 'population' => 134290, 'capital' => true],
                    ['slug' => 'ro-sighisoara',  'name' => ['ro' => 'Sighișoara',  'en' => 'Sighisoara'],  'lat' => 46.2167, 'lng' => 24.7833, 'population' => 26370,  'featured' => true],
                    ['slug' => 'ro-reghin',      'name' => ['ro' => 'Reghin',      'en' => 'Reghin'],      'lat' => 46.7833, 'lng' => 24.7167, 'population' => 33281],
                    ['slug' => 'ro-sovata',      'name' => ['ro' => 'Sovata',      'en' => 'Sovata'],      'lat' => 46.6000, 'lng' => 25.0667, 'population' => 10385,  'featured' => true],
                ],
            ],
            [
                'code' => 'RO-AB', 'region' => 'RO-TR', 'slug' => 'ro-alba', 'sort' => 5,
                'name' => ['ro' => 'Alba', 'en' => 'Alba'],
                'cities' => [
                    ['slug' => 'ro-alba-iulia', 'name' => ['ro' => 'Alba Iulia', 'en' => 'Alba Iulia'], 'lat' => 46.0667, 'lng' => 23.5833, 'population' => 63536, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-aiud',       'name' => ['ro' => 'Aiud',       'en' => 'Aiud'],       'lat' => 46.3167, 'lng' => 23.7167, 'population' => 22876],
                    ['slug' => 'ro-blaj',       'name' => ['ro' => 'Blaj',       'en' => 'Blaj'],       'lat' => 46.1833, 'lng' => 23.9167, 'population' => 17988],
                    ['slug' => 'ro-sebes',      'name' => ['ro' => 'Sebeș',      'en' => 'Sebes'],      'lat' => 45.9500, 'lng' => 23.5667, 'population' => 24850],
                ],
            ],
            [
                'code' => 'RO-HD', 'region' => 'RO-TR', 'slug' => 'ro-hunedoara', 'sort' => 6,
                'name' => ['ro' => 'Hunedoara', 'en' => 'Hunedoara'],
                'cities' => [
                    ['slug' => 'ro-deva',          'name' => ['ro' => 'Deva',          'en' => 'Deva'],          'lat' => 45.8833, 'lng' => 22.9000, 'population' => 61123, 'capital' => true],
                    ['slug' => 'ro-hunedoara',     'name' => ['ro' => 'Hunedoara',     'en' => 'Hunedoara'],     'lat' => 45.7500, 'lng' => 22.9000, 'population' => 60525, 'featured' => true],
                    ['slug' => 'ro-petrosani',     'name' => ['ro' => 'Petroșani',     'en' => 'Petrosani'],     'lat' => 45.4167, 'lng' => 23.3667, 'population' => 37160],
                    ['slug' => 'ro-orastie',       'name' => ['ro' => 'Orăștie',       'en' => 'Orastie'],       'lat' => 45.8500, 'lng' => 23.2000, 'population' => 18654],
                    ['slug' => 'ro-sarmizegetusa', 'name' => ['ro' => 'Sarmizegetusa', 'en' => 'Sarmizegetusa'], 'lat' => 45.5167, 'lng' => 23.3000, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-BN', 'region' => 'RO-TR', 'slug' => 'ro-bistrita-nasaud', 'sort' => 7,
                'name' => ['ro' => 'Bistrița-Năsăud', 'en' => 'Bistrita-Nasaud'],
                'cities' => [
                    ['slug' => 'ro-bistrita', 'name' => ['ro' => 'Bistrița', 'en' => 'Bistrita'], 'lat' => 47.1333, 'lng' => 24.5000, 'population' => 75076, 'capital' => true],
                    ['slug' => 'ro-nasaud',   'name' => ['ro' => 'Năsăud',   'en' => 'Nasaud'],   'lat' => 47.2833, 'lng' => 24.4000, 'population' => 10164],
                    ['slug' => 'ro-beclean',  'name' => ['ro' => 'Beclean',  'en' => 'Beclean'],  'lat' => 47.1833, 'lng' => 24.1833, 'population' => 11209],
                ],
            ],
            [
                'code' => 'RO-CV', 'region' => 'RO-TR', 'slug' => 'ro-covasna', 'sort' => 8,
                'name' => ['ro' => 'Covasna', 'en' => 'Covasna'],
                'cities' => [
                    ['slug' => 'ro-sfantu-gheorghe', 'name' => ['ro' => 'Sfântu Gheorghe', 'en' => 'Sfantu Gheorghe'], 'lat' => 45.8667, 'lng' => 25.7833, 'population' => 56006, 'capital' => true],
                    ['slug' => 'ro-targu-secuiesc',  'name' => ['ro' => 'Târgu Secuiesc',  'en' => 'Targu Secuiesc'],  'lat' => 46.0000, 'lng' => 26.1333, 'population' => 18491],
                    ['slug' => 'ro-covasna',         'name' => ['ro' => 'Covasna',         'en' => 'Covasna'],         'lat' => 45.8500, 'lng' => 26.1833, 'population' => 10464, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-HR', 'region' => 'RO-TR', 'slug' => 'ro-harghita', 'sort' => 9,
                'name' => ['ro' => 'Harghita', 'en' => 'Harghita'],
                'cities' => [
                    ['slug' => 'ro-miercurea-ciuc',     'name' => ['ro' => 'Miercurea Ciuc',     'en' => 'Miercurea Ciuc'],     'lat' => 46.3500, 'lng' => 25.8000, 'population' => 37980, 'capital' => true],
                    ['slug' => 'ro-odorheiu-secuiesc',  'name' => ['ro' => 'Odorheiu Secuiesc',  'en' => 'Odorheiu Secuiesc'],  'lat' => 46.3000, 'lng' => 25.3000, 'population' => 34257],
                    ['slug' => 'ro-gheorgheni',         'name' => ['ro' => 'Gheorgheni',         'en' => 'Gheorgheni'],         'lat' => 46.7167, 'lng' => 25.5833, 'population' => 17634],
                    ['slug' => 'ro-praid',              'name' => ['ro' => 'Praid',              'en' => 'Praid'],              'lat' => 46.5333, 'lng' => 25.1333, 'featured' => true],
                    ['slug' => 'ro-lacu-rosu',          'name' => ['ro' => 'Lacul Roșu',         'en' => 'Lacu Rosu'],          'lat' => 46.7833, 'lng' => 25.8000, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-SJ', 'region' => 'RO-TR', 'slug' => 'ro-salaj', 'sort' => 10,
                'name' => ['ro' => 'Sălaj', 'en' => 'Salaj'],
                'cities' => [
                    ['slug' => 'ro-zalau',           'name' => ['ro' => 'Zalău',           'en' => 'Zalau'],           'lat' => 47.1833, 'lng' => 23.0500, 'population' => 56202, 'capital' => true],
                    ['slug' => 'ro-simleu-silvaniei', 'name' => ['ro' => 'Șimleu Silvaniei', 'en' => 'Simleu Silvaniei'], 'lat' => 47.2333, 'lng' => 22.8000, 'population' => 14401],
                ],
            ],
            // ---- BANAT ----
            [
                'code' => 'RO-TM', 'region' => 'RO-BN', 'slug' => 'ro-timis', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Timiș', 'en' => 'Timis'],
                'cities' => [
                    ['slug' => 'ro-timisoara',     'name' => ['ro' => 'Timișoara',     'en' => 'Timisoara'],     'lat' => 45.7489, 'lng' => 21.2087, 'population' => 319279, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-lugoj',         'name' => ['ro' => 'Lugoj',         'en' => 'Lugoj'],         'lat' => 45.6833, 'lng' => 21.9000, 'population' => 40361],
                    ['slug' => 'ro-jimbolia',      'name' => ['ro' => 'Jimbolia',      'en' => 'Jimbolia'],      'lat' => 45.7833, 'lng' => 20.7167, 'population' => 10376],
                    ['slug' => 'ro-dumbravita-tm', 'name' => ['ro' => 'Dumbrăvița',    'en' => 'Dumbravita'],    'lat' => 45.7833, 'lng' => 21.2333, 'population' => 15000],
                ],
            ],
            [
                'code' => 'RO-CS', 'region' => 'RO-BN', 'slug' => 'ro-caras-severin', 'sort' => 2,
                'name' => ['ro' => 'Caraș-Severin', 'en' => 'Caras-Severin'],
                'cities' => [
                    ['slug' => 'ro-resita',          'name' => ['ro' => 'Reșița',          'en' => 'Resita'],          'lat' => 45.3000, 'lng' => 21.8833, 'population' => 73282, 'capital' => true],
                    ['slug' => 'ro-caransebes',      'name' => ['ro' => 'Caransebeș',      'en' => 'Caransebes'],      'lat' => 45.4167, 'lng' => 22.2167, 'population' => 23775],
                    ['slug' => 'ro-baile-herculane', 'name' => ['ro' => 'Băile Herculane', 'en' => 'Baile Herculane'], 'lat' => 44.8833, 'lng' => 22.4167, 'population' => 4979,  'featured' => true],
                ],
            ],
            // ---- CRIȘANA ----
            [
                'code' => 'RO-BH', 'region' => 'RO-CR', 'slug' => 'ro-bihor', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Bihor', 'en' => 'Bihor'],
                'cities' => [
                    ['slug' => 'ro-oradea',      'name' => ['ro' => 'Oradea',      'en' => 'Oradea'],      'lat' => 47.0722, 'lng' => 21.9211, 'population' => 196367, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-salonta',     'name' => ['ro' => 'Salonta',     'en' => 'Salonta'],     'lat' => 46.8000, 'lng' => 21.6500, 'population' => 15610],
                    ['slug' => 'ro-marghita',    'name' => ['ro' => 'Marghita',    'en' => 'Marghita'],    'lat' => 47.3500, 'lng' => 22.3167, 'population' => 14782],
                    ['slug' => 'ro-baile-felix', 'name' => ['ro' => 'Băile Felix', 'en' => 'Baile Felix'], 'lat' => 46.9833, 'lng' => 21.9833, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-AR', 'region' => 'RO-CR', 'slug' => 'ro-arad', 'sort' => 2,
                'name' => ['ro' => 'Arad', 'en' => 'Arad'],
                'cities' => [
                    ['slug' => 'ro-arad',  'name' => ['ro' => 'Arad',  'en' => 'Arad'],  'lat' => 46.1667, 'lng' => 21.3167, 'population' => 159074, 'capital' => true],
                    ['slug' => 'ro-ineu',  'name' => ['ro' => 'Ineu',  'en' => 'Ineu'],  'lat' => 46.4333, 'lng' => 21.8333, 'population' => 9364],
                    ['slug' => 'ro-lipova','name' => ['ro' => 'Lipova', 'en' => 'Lipova'], 'lat' => 46.0833, 'lng' => 21.7000, 'population' => 10345],
                ],
            ],
            // ---- MARAMUREȘ ----
            [
                'code' => 'RO-MM', 'region' => 'RO-MM', 'slug' => 'ro-maramures', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Maramureș', 'en' => 'Maramures'],
                'cities' => [
                    ['slug' => 'ro-baia-mare',       'name' => ['ro' => 'Baia Mare',       'en' => 'Baia Mare'],       'lat' => 47.6567, 'lng' => 23.5850, 'population' => 123738, 'capital' => true],
                    ['slug' => 'ro-sighetu',         'name' => ['ro' => 'Sighetu Marmației','en' => 'Sighetu Marmatiei'],'lat' => 47.9333, 'lng' => 23.8833, 'population' => 37640,  'featured' => true],
                    ['slug' => 'ro-borsa',           'name' => ['ro' => 'Borșa',           'en' => 'Borsa'],           'lat' => 47.6500, 'lng' => 24.6667, 'population' => 24852,  'featured' => true],
                    ['slug' => 'ro-viseu-de-sus',    'name' => ['ro' => 'Vișeu de Sus',     'en' => 'Viseu de Sus'],    'lat' => 47.7167, 'lng' => 24.4167, 'population' => 15370],
                    ['slug' => 'ro-sapanta',         'name' => ['ro' => 'Săpânța',          'en' => 'Sapanta'],         'lat' => 47.9667, 'lng' => 23.7000, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-SM', 'region' => 'RO-MM', 'slug' => 'ro-satu-mare', 'sort' => 2,
                'name' => ['ro' => 'Satu Mare', 'en' => 'Satu Mare'],
                'cities' => [
                    ['slug' => 'ro-satu-mare',    'name' => ['ro' => 'Satu Mare',    'en' => 'Satu Mare'],    'lat' => 47.7833, 'lng' => 22.8833, 'population' => 102411, 'capital' => true],
                    ['slug' => 'ro-carei',        'name' => ['ro' => 'Carei',        'en' => 'Carei'],        'lat' => 47.6833, 'lng' => 22.4667, 'population' => 21112],
                    ['slug' => 'ro-negresti-oas', 'name' => ['ro' => 'Negrești-Oaș', 'en' => 'Negresti-Oas'], 'lat' => 47.8667, 'lng' => 23.4333, 'population' => 15407],
                ],
            ],
            // ---- MOLDOVA (Romanian region) ----
            [
                'code' => 'RO-IS', 'region' => 'RO-MD', 'slug' => 'ro-iasi', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Iași', 'en' => 'Iasi'],
                'cities' => [
                    ['slug' => 'ro-iasi',        'name' => ['ro' => 'Iași',        'en' => 'Iasi'],        'lat' => 47.1585, 'lng' => 27.6014, 'population' => 290422, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-pascani',     'name' => ['ro' => 'Pașcani',     'en' => 'Pascani'],     'lat' => 47.2500, 'lng' => 26.7167, 'population' => 37307],
                    ['slug' => 'ro-targu-frumos','name' => ['ro' => 'Târgu Frumos', 'en' => 'Targu Frumos'],'lat' => 47.2167, 'lng' => 27.0000, 'population' => 12154],
                ],
            ],
            [
                'code' => 'RO-BC', 'region' => 'RO-MD', 'slug' => 'ro-bacau', 'sort' => 2,
                'name' => ['ro' => 'Bacău', 'en' => 'Bacau'],
                'cities' => [
                    ['slug' => 'ro-bacau',         'name' => ['ro' => 'Bacău',         'en' => 'Bacau'],         'lat' => 46.5833, 'lng' => 26.9000, 'population' => 144307, 'capital' => true],
                    ['slug' => 'ro-onesti',        'name' => ['ro' => 'Onești',        'en' => 'Onesti'],        'lat' => 46.2500, 'lng' => 26.7500, 'population' => 40565],
                    ['slug' => 'ro-moinesti',      'name' => ['ro' => 'Moinești',      'en' => 'Moinesti'],      'lat' => 46.4500, 'lng' => 26.5000, 'population' => 22034],
                    ['slug' => 'ro-slanic-moldova','name' => ['ro' => 'Slănic Moldova', 'en' => 'Slanic Moldova'],'lat' => 46.2000, 'lng' => 26.4333, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-SV', 'region' => 'RO-MD', 'slug' => 'ro-suceava', 'sort' => 3,
                'name' => ['ro' => 'Suceava', 'en' => 'Suceava'],
                'cities' => [
                    ['slug' => 'ro-suceava',      'name' => ['ro' => 'Suceava',      'en' => 'Suceava'],      'lat' => 47.6514, 'lng' => 26.2556, 'population' => 92121, 'capital' => true],
                    ['slug' => 'ro-falticeni',    'name' => ['ro' => 'Fălticeni',    'en' => 'Falticeni'],    'lat' => 47.4667, 'lng' => 26.3000, 'population' => 27508],
                    ['slug' => 'ro-radauti',      'name' => ['ro' => 'Rădăuți',      'en' => 'Radauti'],      'lat' => 47.8500, 'lng' => 25.9167, 'population' => 23822],
                    ['slug' => 'ro-vatra-dornei', 'name' => ['ro' => 'Vatra Dornei', 'en' => 'Vatra Dornei'], 'lat' => 47.3500, 'lng' => 25.3500, 'population' => 14689, 'featured' => true],
                    ['slug' => 'ro-gura-humorului','name' => ['ro' => 'Gura Humorului','en' => 'Gura Humorului'],'lat' => 47.5500, 'lng' => 25.8833, 'population' => 13667, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-NT', 'region' => 'RO-MD', 'slug' => 'ro-neamt', 'sort' => 4,
                'name' => ['ro' => 'Neamț', 'en' => 'Neamt'],
                'cities' => [
                    ['slug' => 'ro-piatra-neamt', 'name' => ['ro' => 'Piatra Neamț', 'en' => 'Piatra Neamt'], 'lat' => 46.9333, 'lng' => 26.3667, 'population' => 85055, 'capital' => true],
                    ['slug' => 'ro-roman',        'name' => ['ro' => 'Roman',        'en' => 'Roman'],        'lat' => 46.9167, 'lng' => 26.9167, 'population' => 50713],
                    ['slug' => 'ro-targu-neamt',  'name' => ['ro' => 'Târgu Neamț',  'en' => 'Targu Neamt'],  'lat' => 47.2000, 'lng' => 26.3667, 'population' => 18695],
                    ['slug' => 'ro-durau',        'name' => ['ro' => 'Durău',        'en' => 'Durau'],        'lat' => 47.0500, 'lng' => 25.9667, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-BT', 'region' => 'RO-MD', 'slug' => 'ro-botosani', 'sort' => 5,
                'name' => ['ro' => 'Botoșani', 'en' => 'Botosani'],
                'cities' => [
                    ['slug' => 'ro-botosani', 'name' => ['ro' => 'Botoșani', 'en' => 'Botosani'], 'lat' => 47.7500, 'lng' => 26.6667, 'population' => 106847, 'capital' => true],
                    ['slug' => 'ro-dorohoi',  'name' => ['ro' => 'Dorohoi',  'en' => 'Dorohoi'],  'lat' => 47.9500, 'lng' => 26.4000, 'population' => 27089],
                ],
            ],
            [
                'code' => 'RO-GL', 'region' => 'RO-MD', 'slug' => 'ro-galati', 'sort' => 6,
                'name' => ['ro' => 'Galați', 'en' => 'Galati'],
                'cities' => [
                    ['slug' => 'ro-galati', 'name' => ['ro' => 'Galați', 'en' => 'Galati'], 'lat' => 45.4353, 'lng' => 28.0497, 'population' => 249432, 'capital' => true],
                    ['slug' => 'ro-tecuci', 'name' => ['ro' => 'Tecuci', 'en' => 'Tecuci'], 'lat' => 45.8500, 'lng' => 27.4167, 'population' => 34871],
                ],
            ],
            [
                'code' => 'RO-BR', 'region' => 'RO-MD', 'slug' => 'ro-braila', 'sort' => 7,
                'name' => ['ro' => 'Brăila', 'en' => 'Braila'],
                'cities' => [
                    ['slug' => 'ro-braila', 'name' => ['ro' => 'Brăila', 'en' => 'Braila'], 'lat' => 45.2692, 'lng' => 27.9575, 'population' => 180302, 'capital' => true],
                    ['slug' => 'ro-ianca',  'name' => ['ro' => 'Ianca',  'en' => 'Ianca'],  'lat' => 45.1333, 'lng' => 27.4667, 'population' => 10948],
                ],
            ],
            [
                'code' => 'RO-VN', 'region' => 'RO-MD', 'slug' => 'ro-vrancea', 'sort' => 8,
                'name' => ['ro' => 'Vrancea', 'en' => 'Vrancea'],
                'cities' => [
                    ['slug' => 'ro-focsani', 'name' => ['ro' => 'Focșani', 'en' => 'Focsani'], 'lat' => 45.7000, 'lng' => 27.1833, 'population' => 79315, 'capital' => true],
                    ['slug' => 'ro-adjud',   'name' => ['ro' => 'Adjud',   'en' => 'Adjud'],   'lat' => 46.1000, 'lng' => 27.1833, 'population' => 18126],
                ],
            ],
            [
                'code' => 'RO-VS', 'region' => 'RO-MD', 'slug' => 'ro-vaslui', 'sort' => 9,
                'name' => ['ro' => 'Vaslui', 'en' => 'Vaslui'],
                'cities' => [
                    ['slug' => 'ro-vaslui', 'name' => ['ro' => 'Vaslui', 'en' => 'Vaslui'], 'lat' => 46.6333, 'lng' => 27.7333, 'population' => 55407, 'capital' => true],
                    ['slug' => 'ro-barlad', 'name' => ['ro' => 'Bârlad', 'en' => 'Barlad'], 'lat' => 46.2333, 'lng' => 27.6667, 'population' => 55837],
                    ['slug' => 'ro-husi',   'name' => ['ro' => 'Huși',   'en' => 'Husi'],   'lat' => 46.6833, 'lng' => 28.0500, 'population' => 26266],
                ],
            ],
            // ---- DOBROGEA ----
            [
                'code' => 'RO-CT', 'region' => 'RO-DB', 'slug' => 'ro-constanta', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Constanța', 'en' => 'Constanta'],
                'cities' => [
                    ['slug' => 'ro-constanta',   'name' => ['ro' => 'Constanța',   'en' => 'Constanta'],   'lat' => 44.1598, 'lng' => 28.6348, 'population' => 283872, 'featured' => true, 'capital' => true],
                    ['slug' => 'ro-mangalia',    'name' => ['ro' => 'Mangalia',    'en' => 'Mangalia'],    'lat' => 43.8167, 'lng' => 28.5833, 'population' => 36364],
                    ['slug' => 'ro-medgidia',    'name' => ['ro' => 'Medgidia',    'en' => 'Medgidia'],    'lat' => 44.2500, 'lng' => 28.2667, 'population' => 39179],
                    ['slug' => 'ro-navodari',    'name' => ['ro' => 'Năvodari',    'en' => 'Navodari'],    'lat' => 44.3167, 'lng' => 28.6333, 'population' => 34669],
                    ['slug' => 'ro-mamaia',      'name' => ['ro' => 'Mamaia',      'en' => 'Mamaia'],      'lat' => 44.2500, 'lng' => 28.6167, 'featured' => true],
                    ['slug' => 'ro-eforie-nord', 'name' => ['ro' => 'Eforie Nord', 'en' => 'Eforie Nord'], 'lat' => 44.0667, 'lng' => 28.6333, 'featured' => true],
                    ['slug' => 'ro-neptun',      'name' => ['ro' => 'Neptun',      'en' => 'Neptun'],      'lat' => 43.8333, 'lng' => 28.6000, 'featured' => true],
                    ['slug' => 'ro-costinesti',  'name' => ['ro' => 'Costinești',  'en' => 'Costinesti'],  'lat' => 43.9500, 'lng' => 28.6333, 'featured' => true],
                    ['slug' => 'ro-vama-veche',  'name' => ['ro' => 'Vama Veche',  'en' => 'Vama Veche'],  'lat' => 43.7500, 'lng' => 28.5833, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-TL', 'region' => 'RO-DB', 'slug' => 'ro-tulcea', 'featured' => true, 'sort' => 2,
                'name' => ['ro' => 'Tulcea', 'en' => 'Tulcea'],
                'cities' => [
                    ['slug' => 'ro-tulcea',              'name' => ['ro' => 'Tulcea',              'en' => 'Tulcea'],              'lat' => 45.1833, 'lng' => 28.8000, 'population' => 73707, 'capital' => true],
                    ['slug' => 'ro-sulina',              'name' => ['ro' => 'Sulina',              'en' => 'Sulina'],              'lat' => 45.1500, 'lng' => 29.6667, 'population' => 3663, 'featured' => true],
                    ['slug' => 'ro-sfantu-gheorghe-tl',  'name' => ['ro' => 'Sfântu Gheorghe (Delta)','en' => 'Sfantu Gheorghe (Delta)'],'lat' => 44.8833, 'lng' => 29.6000, 'featured' => true],
                    ['slug' => 'ro-murighiol',           'name' => ['ro' => 'Murighiol',           'en' => 'Murighiol'],           'lat' => 45.0333, 'lng' => 29.1667, 'featured' => true],
                ],
            ],
            // ---- OLTENIA ----
            [
                'code' => 'RO-DJ', 'region' => 'RO-OL', 'slug' => 'ro-dolj', 'sort' => 1,
                'name' => ['ro' => 'Dolj', 'en' => 'Dolj'],
                'cities' => [
                    ['slug' => 'ro-craiova',  'name' => ['ro' => 'Craiova',  'en' => 'Craiova'],  'lat' => 44.3167, 'lng' => 23.8000, 'population' => 269506, 'capital' => true],
                    ['slug' => 'ro-bailesti', 'name' => ['ro' => 'Băilești', 'en' => 'Bailesti'], 'lat' => 44.0333, 'lng' => 23.3500, 'population' => 17969],
                    ['slug' => 'ro-calafat',  'name' => ['ro' => 'Calafat',  'en' => 'Calafat'],  'lat' => 43.9833, 'lng' => 22.9333, 'population' => 16988],
                ],
            ],
            [
                'code' => 'RO-OT', 'region' => 'RO-OL', 'slug' => 'ro-olt', 'sort' => 2,
                'name' => ['ro' => 'Olt', 'en' => 'Olt'],
                'cities' => [
                    ['slug' => 'ro-slatina', 'name' => ['ro' => 'Slatina', 'en' => 'Slatina'], 'lat' => 44.4333, 'lng' => 24.3667, 'population' => 70293, 'capital' => true],
                    ['slug' => 'ro-caracal', 'name' => ['ro' => 'Caracal', 'en' => 'Caracal'], 'lat' => 44.1167, 'lng' => 24.3500, 'population' => 30954],
                ],
            ],
            [
                'code' => 'RO-VL', 'region' => 'RO-OL', 'slug' => 'ro-valcea', 'sort' => 3,
                'name' => ['ro' => 'Vâlcea', 'en' => 'Valcea'],
                'cities' => [
                    ['slug' => 'ro-ramnicu-valcea',  'name' => ['ro' => 'Râmnicu Vâlcea',  'en' => 'Ramnicu Valcea'],  'lat' => 45.1000, 'lng' => 24.3667, 'population' => 98776, 'capital' => true],
                    ['slug' => 'ro-dragasani',       'name' => ['ro' => 'Drăgășani',       'en' => 'Dragasani'],       'lat' => 44.6667, 'lng' => 24.2500, 'population' => 19202],
                    ['slug' => 'ro-baile-olanesti',  'name' => ['ro' => 'Băile Olănești',  'en' => 'Baile Olanesti'],  'lat' => 45.2000, 'lng' => 24.2500, 'featured' => true],
                    ['slug' => 'ro-calimanesti',     'name' => ['ro' => 'Călimănești',     'en' => 'Calimanesti'],     'lat' => 45.2333, 'lng' => 24.3167, 'featured' => true],
                    ['slug' => 'ro-horezu',          'name' => ['ro' => 'Horezu',          'en' => 'Horezu'],          'lat' => 45.1500, 'lng' => 23.9833, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-GJ', 'region' => 'RO-OL', 'slug' => 'ro-gorj', 'sort' => 4,
                'name' => ['ro' => 'Gorj', 'en' => 'Gorj'],
                'cities' => [
                    ['slug' => 'ro-targu-jiu', 'name' => ['ro' => 'Târgu Jiu', 'en' => 'Targu Jiu'], 'lat' => 45.0333, 'lng' => 23.2833, 'population' => 82504, 'capital' => true],
                    ['slug' => 'ro-motru',     'name' => ['ro' => 'Motru',     'en' => 'Motru'],     'lat' => 44.8000, 'lng' => 22.9667, 'population' => 20875],
                    ['slug' => 'ro-ranca',     'name' => ['ro' => 'Rânca',     'en' => 'Ranca'],     'lat' => 45.2833, 'lng' => 23.6833, 'featured' => true],
                ],
            ],
            [
                'code' => 'RO-MH', 'region' => 'RO-OL', 'slug' => 'ro-mehedinti', 'sort' => 5,
                'name' => ['ro' => 'Mehedinți', 'en' => 'Mehedinti'],
                'cities' => [
                    ['slug' => 'ro-drobeta',  'name' => ['ro' => 'Drobeta-Turnu Severin', 'en' => 'Drobeta-Turnu Severin'], 'lat' => 44.6333, 'lng' => 22.6667, 'population' => 92617, 'capital' => true],
                    ['slug' => 'ro-orsova',   'name' => ['ro' => 'Orșova',                'en' => 'Orsova'],                'lat' => 44.7167, 'lng' => 22.4000, 'population' => 10441, 'featured' => true],
                ],
            ],
        ];
    }

    /* =========================================================================
     * MOLDOVA (Country — Republic of Moldova)
     * ===================================================================== */

    private function getMdRegions(): array
    {
        return [
            ['slug' => 'md-nord',    'name' => ['ro' => 'Nord',    'en' => 'North'],            'code' => 'MD-N',  'featured' => false],
            ['slug' => 'md-centru',  'name' => ['ro' => 'Centru',  'en' => 'Center'],           'code' => 'MD-C',  'featured' => false],
            ['slug' => 'md-sud',     'name' => ['ro' => 'Sud',     'en' => 'South'],            'code' => 'MD-S',  'featured' => false],
            ['slug' => 'md-chisinau','name' => ['ro' => 'Chișinău','en' => 'Chisinau Region'],  'code' => 'MD-CH', 'featured' => true],
        ];
    }

    private function getMdCounties(): array
    {
        return [
            // ---- CHIȘINĂU ----
            [
                'code' => 'MD-MU-CH', 'region' => 'MD-CH', 'slug' => 'md-chisinau-mun', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Municipiul Chișinău', 'en' => 'Chisinau Municipality'],
                'cities' => [
                    ['slug' => 'md-chisinau', 'name' => ['ro' => 'Chișinău', 'en' => 'Chisinau'], 'lat' => 47.0105, 'lng' => 28.8638, 'population' => 688000, 'featured' => true, 'capital' => true],
                    ['slug' => 'md-durlesti', 'name' => ['ro' => 'Durlești', 'en' => 'Durlesti'], 'lat' => 47.0333, 'lng' => 28.7833, 'population' => 16000],
                    ['slug' => 'md-singera',  'name' => ['ro' => 'Sîngera',  'en' => 'Singera'],  'lat' => 46.9167, 'lng' => 28.8833, 'population' => 20000],
                ],
            ],
            [
                'code' => 'MD-MU-BA', 'region' => 'MD-N', 'slug' => 'md-balti-mun', 'featured' => true, 'sort' => 2,
                'name' => ['ro' => 'Municipiul Bălți', 'en' => 'Balti Municipality'],
                'cities' => [
                    ['slug' => 'md-balti', 'name' => ['ro' => 'Bălți', 'en' => 'Balti'], 'lat' => 47.7617, 'lng' => 27.9292, 'population' => 94000, 'featured' => true, 'capital' => true],
                ],
            ],
            // ---- NORD ----
            [
                'code' => 'MD-SO', 'region' => 'MD-N', 'slug' => 'md-soroca', 'sort' => 3,
                'name' => ['ro' => 'Raionul Soroca', 'en' => 'Soroca District'],
                'cities' => [
                    ['slug' => 'md-soroca', 'name' => ['ro' => 'Soroca', 'en' => 'Soroca'], 'lat' => 48.1564, 'lng' => 28.2872, 'population' => 28000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-DR', 'region' => 'MD-N', 'slug' => 'md-drochia', 'sort' => 4,
                'name' => ['ro' => 'Raionul Drochia', 'en' => 'Drochia District'],
                'cities' => [
                    ['slug' => 'md-drochia', 'name' => ['ro' => 'Drochia', 'en' => 'Drochia'], 'lat' => 48.0000, 'lng' => 27.9667, 'population' => 19000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-ED', 'region' => 'MD-N', 'slug' => 'md-edinet', 'sort' => 5,
                'name' => ['ro' => 'Raionul Edineț', 'en' => 'Edinet District'],
                'cities' => [
                    ['slug' => 'md-edinet', 'name' => ['ro' => 'Edineț', 'en' => 'Edinet'], 'lat' => 48.1667, 'lng' => 27.3000, 'population' => 20000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-FL', 'region' => 'MD-N', 'slug' => 'md-floresti', 'sort' => 6,
                'name' => ['ro' => 'Raionul Florești', 'en' => 'Floresti District'],
                'cities' => [
                    ['slug' => 'md-floresti', 'name' => ['ro' => 'Florești', 'en' => 'Floresti'], 'lat' => 47.8833, 'lng' => 28.2833, 'population' => 18000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-RI', 'region' => 'MD-N', 'slug' => 'md-riscani', 'sort' => 7,
                'name' => ['ro' => 'Raionul Rîșcani', 'en' => 'Riscani District'],
                'cities' => [
                    ['slug' => 'md-riscani', 'name' => ['ro' => 'Rîșcani', 'en' => 'Riscani'], 'lat' => 47.9500, 'lng' => 27.5667, 'population' => 14000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-SN', 'region' => 'MD-N', 'slug' => 'md-singerei', 'sort' => 8,
                'name' => ['ro' => 'Raionul Sîngerei', 'en' => 'Singerei District'],
                'cities' => [
                    ['slug' => 'md-singerei', 'name' => ['ro' => 'Sîngerei', 'en' => 'Singerei'], 'lat' => 47.6333, 'lng' => 28.1500, 'population' => 15000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-GL', 'region' => 'MD-N', 'slug' => 'md-glodeni', 'sort' => 9,
                'name' => ['ro' => 'Raionul Glodeni', 'en' => 'Glodeni District'],
                'cities' => [
                    ['slug' => 'md-glodeni', 'name' => ['ro' => 'Glodeni', 'en' => 'Glodeni'], 'lat' => 47.7833, 'lng' => 27.5167, 'population' => 10000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-OC', 'region' => 'MD-N', 'slug' => 'md-ocnita', 'sort' => 10,
                'name' => ['ro' => 'Raionul Ocnița', 'en' => 'Ocnita District'],
                'cities' => [
                    ['slug' => 'md-ocnita', 'name' => ['ro' => 'Ocnița', 'en' => 'Ocnita'], 'lat' => 48.3833, 'lng' => 27.4500, 'population' => 12000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-DO', 'region' => 'MD-N', 'slug' => 'md-donduseni', 'sort' => 11,
                'name' => ['ro' => 'Raionul Dondușeni', 'en' => 'Donduseni District'],
                'cities' => [
                    ['slug' => 'md-donduseni', 'name' => ['ro' => 'Dondușeni', 'en' => 'Donduseni'], 'lat' => 48.2333, 'lng' => 27.6000, 'population' => 10000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-BR', 'region' => 'MD-N', 'slug' => 'md-briceni', 'sort' => 12,
                'name' => ['ro' => 'Raionul Briceni', 'en' => 'Briceni District'],
                'cities' => [
                    ['slug' => 'md-briceni', 'name' => ['ro' => 'Briceni', 'en' => 'Briceni'], 'lat' => 48.3667, 'lng' => 27.0833, 'population' => 15000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-FA', 'region' => 'MD-N', 'slug' => 'md-falesti', 'sort' => 13,
                'name' => ['ro' => 'Raionul Fălești', 'en' => 'Falesti District'],
                'cities' => [
                    ['slug' => 'md-falesti', 'name' => ['ro' => 'Fălești', 'en' => 'Falesti'], 'lat' => 47.5667, 'lng' => 27.7167, 'population' => 15000, 'capital' => true],
                ],
            ],
            // ---- CENTRU ----
            [
                'code' => 'MD-OR', 'region' => 'MD-C', 'slug' => 'md-orhei', 'sort' => 14,
                'name' => ['ro' => 'Raionul Orhei', 'en' => 'Orhei District'],
                'cities' => [
                    ['slug' => 'md-orhei',  'name' => ['ro' => 'Orhei',  'en' => 'Orhei'],  'lat' => 47.3833, 'lng' => 28.8167, 'population' => 26000, 'capital' => true, 'featured' => true],
                    ['slug' => 'md-rezina', 'name' => ['ro' => 'Rezina', 'en' => 'Rezina'], 'lat' => 47.7500, 'lng' => 28.9667, 'population' => 13000],
                ],
            ],
            [
                'code' => 'MD-UN', 'region' => 'MD-C', 'slug' => 'md-ungheni', 'sort' => 15,
                'name' => ['ro' => 'Raionul Ungheni', 'en' => 'Ungheni District'],
                'cities' => [
                    ['slug' => 'md-ungheni', 'name' => ['ro' => 'Ungheni', 'en' => 'Ungheni'], 'lat' => 47.2167, 'lng' => 27.8000, 'population' => 27000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-ST', 'region' => 'MD-C', 'slug' => 'md-straseni', 'sort' => 16,
                'name' => ['ro' => 'Raionul Strășeni', 'en' => 'Straseni District'],
                'cities' => [
                    ['slug' => 'md-straseni', 'name' => ['ro' => 'Strășeni', 'en' => 'Straseni'], 'lat' => 47.1333, 'lng' => 28.5833, 'population' => 14000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-IA', 'region' => 'MD-C', 'slug' => 'md-ialoveni', 'sort' => 17,
                'name' => ['ro' => 'Raionul Ialoveni', 'en' => 'Ialoveni District'],
                'cities' => [
                    ['slug' => 'md-ialoveni', 'name' => ['ro' => 'Ialoveni', 'en' => 'Ialoveni'], 'lat' => 46.8833, 'lng' => 28.7833, 'population' => 14000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-CA', 'region' => 'MD-C', 'slug' => 'md-calarasi-md', 'sort' => 18,
                'name' => ['ro' => 'Raionul Călărași', 'en' => 'Calarasi District (MD)'],
                'cities' => [
                    ['slug' => 'md-calarasi-md', 'name' => ['ro' => 'Călărași', 'en' => 'Calarasi (MD)'], 'lat' => 47.2500, 'lng' => 28.3167, 'population' => 17000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-NI', 'region' => 'MD-C', 'slug' => 'md-nisporeni', 'sort' => 19,
                'name' => ['ro' => 'Raionul Nisporeni', 'en' => 'Nisporeni District'],
                'cities' => [
                    ['slug' => 'md-nisporeni', 'name' => ['ro' => 'Nisporeni', 'en' => 'Nisporeni'], 'lat' => 47.0833, 'lng' => 28.1833, 'population' => 13000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-TE', 'region' => 'MD-C', 'slug' => 'md-telenesti', 'sort' => 20,
                'name' => ['ro' => 'Raionul Telenești', 'en' => 'Telenesti District'],
                'cities' => [
                    ['slug' => 'md-telenesti', 'name' => ['ro' => 'Telenești', 'en' => 'Telenesti'], 'lat' => 47.5000, 'lng' => 28.3667, 'population' => 10000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-RE', 'region' => 'MD-C', 'slug' => 'md-rezina-district', 'sort' => 21,
                'name' => ['ro' => 'Raionul Rezina', 'en' => 'Rezina District'],
                'cities' => [
                    ['slug' => 'md-rezina-city', 'name' => ['ro' => 'Rezina', 'en' => 'Rezina'], 'lat' => 47.7500, 'lng' => 28.9667, 'population' => 13000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-CR', 'region' => 'MD-C', 'slug' => 'md-criuleni', 'sort' => 22,
                'name' => ['ro' => 'Raionul Criuleni', 'en' => 'Criuleni District'],
                'cities' => [
                    ['slug' => 'md-criuleni', 'name' => ['ro' => 'Criuleni', 'en' => 'Criuleni'], 'lat' => 47.2167, 'lng' => 29.1500, 'population' => 13000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-DU', 'region' => 'MD-C', 'slug' => 'md-dubasari', 'sort' => 23,
                'name' => ['ro' => 'Raionul Dubăsari', 'en' => 'Dubasari District'],
                'cities' => [
                    ['slug' => 'md-dubasari', 'name' => ['ro' => 'Dubăsari', 'en' => 'Dubasari'], 'lat' => 47.2667, 'lng' => 29.1667, 'population' => 22000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-HI', 'region' => 'MD-C', 'slug' => 'md-hincesti', 'sort' => 24,
                'name' => ['ro' => 'Raionul Hâncești', 'en' => 'Hincesti District'],
                'cities' => [
                    ['slug' => 'md-hincesti', 'name' => ['ro' => 'Hâncești', 'en' => 'Hincesti'], 'lat' => 46.8167, 'lng' => 28.5833, 'population' => 18000, 'capital' => true],
                ],
            ],
            // ---- SUD ----
            [
                'code' => 'MD-CH-D', 'region' => 'MD-S', 'slug' => 'md-cahul', 'sort' => 25,
                'name' => ['ro' => 'Raionul Cahul', 'en' => 'Cahul District'],
                'cities' => [
                    ['slug' => 'md-cahul', 'name' => ['ro' => 'Cahul', 'en' => 'Cahul'], 'lat' => 45.9000, 'lng' => 28.2000, 'population' => 36000, 'capital' => true, 'featured' => true],
                ],
            ],
            [
                'code' => 'MD-CE', 'region' => 'MD-S', 'slug' => 'md-causeni', 'sort' => 26,
                'name' => ['ro' => 'Raionul Căușeni', 'en' => 'Causeni District'],
                'cities' => [
                    ['slug' => 'md-causeni', 'name' => ['ro' => 'Căușeni', 'en' => 'Causeni'], 'lat' => 46.6333, 'lng' => 29.4167, 'population' => 21000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-AN', 'region' => 'MD-S', 'slug' => 'md-anenii-noi', 'sort' => 27,
                'name' => ['ro' => 'Raionul Anenii Noi', 'en' => 'Anenii Noi District'],
                'cities' => [
                    ['slug' => 'md-anenii-noi', 'name' => ['ro' => 'Anenii Noi', 'en' => 'Anenii Noi'], 'lat' => 46.8833, 'lng' => 29.1667, 'population' => 12000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-SV', 'region' => 'MD-S', 'slug' => 'md-stefan-voda', 'sort' => 28,
                'name' => ['ro' => 'Raionul Ștefan Vodă', 'en' => 'Stefan Voda District'],
                'cities' => [
                    ['slug' => 'md-stefan-voda', 'name' => ['ro' => 'Ștefan Vodă', 'en' => 'Stefan Voda'], 'lat' => 46.5167, 'lng' => 29.6667, 'population' => 11000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-CT', 'region' => 'MD-S', 'slug' => 'md-cantemir', 'sort' => 29,
                'name' => ['ro' => 'Raionul Cantemir', 'en' => 'Cantemir District'],
                'cities' => [
                    ['slug' => 'md-cantemir', 'name' => ['ro' => 'Cantemir', 'en' => 'Cantemir'], 'lat' => 46.2667, 'lng' => 28.2000, 'population' => 8000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-LE', 'region' => 'MD-S', 'slug' => 'md-leova', 'sort' => 30,
                'name' => ['ro' => 'Raionul Leova', 'en' => 'Leova District'],
                'cities' => [
                    ['slug' => 'md-leova', 'name' => ['ro' => 'Leova', 'en' => 'Leova'], 'lat' => 46.4833, 'lng' => 28.2500, 'population' => 12000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-CI', 'region' => 'MD-S', 'slug' => 'md-cimislia', 'sort' => 31,
                'name' => ['ro' => 'Raionul Cimișlia', 'en' => 'Cimislia District'],
                'cities' => [
                    ['slug' => 'md-cimislia', 'name' => ['ro' => 'Cimișlia', 'en' => 'Cimislia'], 'lat' => 46.5167, 'lng' => 28.7667, 'population' => 13000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-BA-D', 'region' => 'MD-S', 'slug' => 'md-basarabeasca', 'sort' => 32,
                'name' => ['ro' => 'Raionul Basarabeasca', 'en' => 'Basarabeasca District'],
                'cities' => [
                    ['slug' => 'md-basarabeasca', 'name' => ['ro' => 'Basarabeasca', 'en' => 'Basarabeasca'], 'lat' => 46.3167, 'lng' => 28.9667, 'population' => 12000, 'capital' => true],
                ],
            ],
            [
                'code' => 'MD-TA', 'region' => 'MD-S', 'slug' => 'md-taraclia', 'sort' => 33,
                'name' => ['ro' => 'Raionul Taraclia', 'en' => 'Taraclia District'],
                'cities' => [
                    ['slug' => 'md-taraclia', 'name' => ['ro' => 'Taraclia', 'en' => 'Taraclia'], 'lat' => 45.9000, 'lng' => 28.6667, 'population' => 11000, 'capital' => true],
                ],
            ],
            // UTA Gagauzia
            [
                'code' => 'MD-GA', 'region' => 'MD-S', 'slug' => 'md-gagauzia', 'sort' => 34,
                'name' => ['ro' => 'UTA Găgăuzia', 'en' => 'Gagauzia'],
                'cities' => [
                    ['slug' => 'md-comrat',     'name' => ['ro' => 'Comrat',     'en' => 'Comrat'],     'lat' => 46.3000, 'lng' => 28.6500, 'population' => 22000, 'capital' => true],
                    ['slug' => 'md-ceadir-lunga','name' => ['ro' => 'Ceadîr-Lunga','en' => 'Ceadir-Lunga'],'lat' => 46.0500, 'lng' => 28.8500, 'population' => 22000],
                    ['slug' => 'md-vulcanesti', 'name' => ['ro' => 'Vulcănești',  'en' => 'Vulcanesti'],  'lat' => 45.6833, 'lng' => 28.4000, 'population' => 15000],
                ],
            ],
            // Tiraspol (Transnistria — de facto territory)
            [
                'code' => 'MD-TP', 'region' => 'MD-C', 'slug' => 'md-transnistria', 'sort' => 35,
                'name' => ['ro' => 'Transnistria', 'en' => 'Transnistria'],
                'cities' => [
                    ['slug' => 'md-tiraspol',    'name' => ['ro' => 'Tiraspol',    'en' => 'Tiraspol'],    'lat' => 46.8403, 'lng' => 29.6433, 'population' => 130000, 'capital' => true, 'featured' => true],
                    ['slug' => 'md-tighina',     'name' => ['ro' => 'Tighina',     'en' => 'Bender'],      'lat' => 46.8333, 'lng' => 29.4667, 'population' => 93000],
                    ['slug' => 'md-ribnita',     'name' => ['ro' => 'Rîbnița',     'en' => 'Ribnita'],     'lat' => 47.7667, 'lng' => 29.0000, 'population' => 54000],
                ],
            ],
        ];
    }

    /* =========================================================================
     * HUNGARY (Magyarország)
     * ===================================================================== */

    private function getHuRegions(): array
    {
        return [
            ['slug' => 'hu-kozep-magyarorszag', 'name' => ['ro' => 'Ungaria Centrală',      'en' => 'Central Hungary'],          'code' => 'HU-KM', 'featured' => true],
            ['slug' => 'hu-eszak-magyarorszag', 'name' => ['ro' => 'Ungaria de Nord',        'en' => 'Northern Hungary'],         'code' => 'HU-EM', 'featured' => false],
            ['slug' => 'hu-eszak-alfold',       'name' => ['ro' => 'Câmpia de Nord',         'en' => 'Northern Great Plain'],     'code' => 'HU-EA', 'featured' => false],
            ['slug' => 'hu-del-alfold',         'name' => ['ro' => 'Câmpia de Sud',          'en' => 'Southern Great Plain'],     'code' => 'HU-DA', 'featured' => false],
            ['slug' => 'hu-nyugat-dunantul',    'name' => ['ro' => 'Transdanubia de Vest',   'en' => 'Western Transdanubia'],     'code' => 'HU-ND', 'featured' => false],
            ['slug' => 'hu-kozep-dunantul',     'name' => ['ro' => 'Transdanubia Centrală',  'en' => 'Central Transdanubia'],     'code' => 'HU-KD', 'featured' => false],
            ['slug' => 'hu-del-dunantul',       'name' => ['ro' => 'Transdanubia de Sud',    'en' => 'Southern Transdanubia'],    'code' => 'HU-DD', 'featured' => false],
        ];
    }

    private function getHuCounties(): array
    {
        return [
            // ---- KÖZÉP-MAGYARORSZÁG (Central Hungary) ----
            [
                'code' => 'HU-BP', 'region' => 'HU-KM', 'slug' => 'hu-budapest', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Budapesta', 'en' => 'Budapest'],
                'cities' => [
                    ['slug' => 'hu-budapest', 'name' => ['ro' => 'Budapesta', 'en' => 'Budapest'], 'lat' => 47.4979, 'lng' => 19.0402, 'population' => 1756000, 'featured' => true, 'capital' => true],
                    ['slug' => 'hu-erd',      'name' => ['ro' => 'Érd',       'en' => 'Erd'],      'lat' => 47.3911, 'lng' => 18.9100, 'population' => 65000],
                    ['slug' => 'hu-budaors',  'name' => ['ro' => 'Budaörs',   'en' => 'Budaors'],  'lat' => 47.4611, 'lng' => 18.9583, 'population' => 30000],
                ],
            ],
            [
                'code' => 'HU-PE', 'region' => 'HU-KM', 'slug' => 'hu-pest', 'sort' => 2,
                'name' => ['ro' => 'Comitatul Pest', 'en' => 'Pest County'],
                'cities' => [
                    ['slug' => 'hu-godollo',    'name' => ['ro' => 'Gödöllő',    'en' => 'Godollo'],    'lat' => 47.5964, 'lng' => 19.3594, 'population' => 36000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-vac',         'name' => ['ro' => 'Vác',        'en' => 'Vac'],        'lat' => 47.7833, 'lng' => 19.1333, 'population' => 34000],
                    ['slug' => 'hu-szentendre',  'name' => ['ro' => 'Szentendre', 'en' => 'Szentendre'], 'lat' => 47.6667, 'lng' => 19.0833, 'population' => 25000, 'featured' => true],
                    ['slug' => 'hu-dunakeszi',   'name' => ['ro' => 'Dunakeszi',  'en' => 'Dunakeszi'],  'lat' => 47.6314, 'lng' => 19.1386, 'population' => 43000],
                    ['slug' => 'hu-pomaz',       'name' => ['ro' => 'Pomáz',      'en' => 'Pomaz'],      'lat' => 47.6483, 'lng' => 19.0228, 'population' => 16000],
                    ['slug' => 'hu-esztergom',   'name' => ['ro' => 'Esztergom',  'en' => 'Esztergom'],  'lat' => 47.7906, 'lng' => 18.7397, 'population' => 30000, 'featured' => true],
                ],
            ],
            // ---- ÉSZAK-MAGYARORSZÁG (Northern Hungary) ----
            [
                'code' => 'HU-BAZ', 'region' => 'HU-EM', 'slug' => 'hu-borsod-abauj-zemplen', 'sort' => 3,
                'name' => ['ro' => 'Borsod-Abaúj-Zemplén', 'en' => 'Borsod-Abauj-Zemplen'],
                'cities' => [
                    ['slug' => 'hu-miskolc',      'name' => ['ro' => 'Miscolț',   'en' => 'Miskolc'],      'lat' => 48.1035, 'lng' => 20.7784, 'population' => 160000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-ozd',          'name' => ['ro' => 'Ózd',       'en' => 'Ozd'],          'lat' => 48.2167, 'lng' => 20.2833, 'population' => 35000],
                    ['slug' => 'hu-kazincbarcika','name' => ['ro' => 'Kazincbarcika','en' => 'Kazincbarcika'],'lat' => 48.2500, 'lng' => 20.6333, 'population' => 28000],
                    ['slug' => 'hu-tokaj',        'name' => ['ro' => 'Tokaj',     'en' => 'Tokaj'],        'lat' => 48.1167, 'lng' => 21.4000, 'population' => 4400,  'featured' => true],
                ],
            ],
            [
                'code' => 'HU-HE', 'region' => 'HU-EM', 'slug' => 'hu-heves', 'sort' => 4,
                'name' => ['ro' => 'Comitatul Heves', 'en' => 'Heves County'],
                'cities' => [
                    ['slug' => 'hu-eger',      'name' => ['ro' => 'Eger',      'en' => 'Eger'],      'lat' => 47.9025, 'lng' => 20.3772, 'population' => 55000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-gyongyos', 'name' => ['ro' => 'Gyöngyös',  'en' => 'Gyongyos'],  'lat' => 47.7825, 'lng' => 19.9328, 'population' => 32000],
                    ['slug' => 'hu-hatvan',   'name' => ['ro' => 'Hatvan',    'en' => 'Hatvan'],    'lat' => 47.6667, 'lng' => 19.6833, 'population' => 21000],
                ],
            ],
            [
                'code' => 'HU-NO', 'region' => 'HU-EM', 'slug' => 'hu-nograd', 'sort' => 5,
                'name' => ['ro' => 'Comitatul Nógrád', 'en' => 'Nograd County'],
                'cities' => [
                    ['slug' => 'hu-salgotarjan', 'name' => ['ro' => 'Salgótarján', 'en' => 'Salgotarjan'], 'lat' => 48.1000, 'lng' => 19.8167, 'population' => 38000, 'capital' => true],
                    ['slug' => 'hu-balassagyarmat', 'name' => ['ro' => 'Balassagyarmat', 'en' => 'Balassagyarmat'], 'lat' => 48.0667, 'lng' => 19.2833, 'population' => 16000],
                ],
            ],
            // ---- ÉSZAK-ALFÖLD (Northern Great Plain) ----
            [
                'code' => 'HU-HB', 'region' => 'HU-EA', 'slug' => 'hu-hajdu-bihar', 'sort' => 6,
                'name' => ['ro' => 'Hajdú-Bihar', 'en' => 'Hajdu-Bihar'],
                'cities' => [
                    ['slug' => 'hu-debrecen',   'name' => ['ro' => 'Debrețin',  'en' => 'Debrecen'],   'lat' => 47.5316, 'lng' => 21.6273, 'population' => 200000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-hajduboszormeny', 'name' => ['ro' => 'Hajdúböszörmény', 'en' => 'Hajduboszormeny'], 'lat' => 47.6667, 'lng' => 21.5167, 'population' => 30000],
                    ['slug' => 'hu-hajduszoboszlo',  'name' => ['ro' => 'Hajdúszoboszló',  'en' => 'Hajduszoboszlo'],  'lat' => 47.4500, 'lng' => 21.3833, 'population' => 23000, 'featured' => true],
                ],
            ],
            [
                'code' => 'HU-JNS', 'region' => 'HU-EA', 'slug' => 'hu-jasz-nagykun-szolnok', 'sort' => 7,
                'name' => ['ro' => 'Jász-Nagykun-Szolnok', 'en' => 'Jasz-Nagykun-Szolnok'],
                'cities' => [
                    ['slug' => 'hu-szolnok',     'name' => ['ro' => 'Szolnok',     'en' => 'Szolnok'],     'lat' => 47.1778, 'lng' => 20.1956, 'population' => 70000, 'capital' => true],
                    ['slug' => 'hu-karcag',      'name' => ['ro' => 'Karcag',      'en' => 'Karcag'],      'lat' => 47.3167, 'lng' => 20.9333, 'population' => 20000],
                    ['slug' => 'hu-jaszbereny',  'name' => ['ro' => 'Jászberény',  'en' => 'Jaszbereny'],  'lat' => 47.5000, 'lng' => 19.9167, 'population' => 26000],
                ],
            ],
            [
                'code' => 'HU-SSB', 'region' => 'HU-EA', 'slug' => 'hu-szabolcs-szatmar-bereg', 'sort' => 8,
                'name' => ['ro' => 'Szabolcs-Szatmár-Bereg', 'en' => 'Szabolcs-Szatmar-Bereg'],
                'cities' => [
                    ['slug' => 'hu-nyiregyhaza', 'name' => ['ro' => 'Nyíregyháza', 'en' => 'Nyiregyhaza'], 'lat' => 47.9495, 'lng' => 21.7444, 'population' => 120000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-kisvarda',    'name' => ['ro' => 'Kisvárda',    'en' => 'Kisvarda'],    'lat' => 48.2167, 'lng' => 22.0833, 'population' => 17000],
                ],
            ],
            // ---- DÉL-ALFÖLD (Southern Great Plain) ----
            [
                'code' => 'HU-BK', 'region' => 'HU-DA', 'slug' => 'hu-bacs-kiskun', 'sort' => 9,
                'name' => ['ro' => 'Bács-Kiskun', 'en' => 'Bacs-Kiskun'],
                'cities' => [
                    ['slug' => 'hu-kecskemet', 'name' => ['ro' => 'Kecskemét', 'en' => 'Kecskemet'], 'lat' => 46.9061, 'lng' => 19.6914, 'population' => 110000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-baja',      'name' => ['ro' => 'Baja',      'en' => 'Baja'],      'lat' => 46.1833, 'lng' => 18.9500, 'population' => 37000],
                    ['slug' => 'hu-kiskunfelegyhaza', 'name' => ['ro' => 'Kiskunfélegyháza', 'en' => 'Kiskunfelegyhaza'], 'lat' => 46.7167, 'lng' => 19.8667, 'population' => 31000],
                ],
            ],
            [
                'code' => 'HU-BE', 'region' => 'HU-DA', 'slug' => 'hu-bekes', 'sort' => 10,
                'name' => ['ro' => 'Comitatul Békés', 'en' => 'Bekes County'],
                'cities' => [
                    ['slug' => 'hu-bekescsaba', 'name' => ['ro' => 'Békéscsaba', 'en' => 'Bekescsaba'], 'lat' => 46.6833, 'lng' => 21.1000, 'population' => 60000, 'capital' => true],
                    ['slug' => 'hu-gyula-hu',   'name' => ['ro' => 'Gyula',      'en' => 'Gyula'],      'lat' => 46.6500, 'lng' => 21.2833, 'population' => 30000, 'featured' => true],
                    ['slug' => 'hu-oroshaza',   'name' => ['ro' => 'Orosháza',   'en' => 'Oroshaza'],   'lat' => 46.5667, 'lng' => 20.6667, 'population' => 30000],
                ],
            ],
            [
                'code' => 'HU-CS', 'region' => 'HU-DA', 'slug' => 'hu-csongrad-csanad', 'sort' => 11,
                'name' => ['ro' => 'Csongrád-Csanád', 'en' => 'Csongrad-Csanad'],
                'cities' => [
                    ['slug' => 'hu-szeged',           'name' => ['ro' => 'Seghedin',          'en' => 'Szeged'],          'lat' => 46.2530, 'lng' => 20.1414, 'population' => 160000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-hodmezovasarhely', 'name' => ['ro' => 'Hódmezővásárhely',  'en' => 'Hodmezovasarhely'],'lat' => 46.4167, 'lng' => 20.3167, 'population' => 44000],
                    ['slug' => 'hu-makó',             'name' => ['ro' => 'Makó',              'en' => 'Mako'],            'lat' => 46.2167, 'lng' => 20.4833, 'population' => 24000],
                ],
            ],
            // ---- NYUGAT-DUNÁNTÚL (Western Transdanubia) ----
            [
                'code' => 'HU-GS', 'region' => 'HU-ND', 'slug' => 'hu-gyor-moson-sopron', 'sort' => 12,
                'name' => ['ro' => 'Győr-Moson-Sopron', 'en' => 'Gyor-Moson-Sopron'],
                'cities' => [
                    ['slug' => 'hu-gyor',                'name' => ['ro' => 'Ger',               'en' => 'Gyor'],                'lat' => 47.6875, 'lng' => 17.6504, 'population' => 130000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-sopron',              'name' => ['ro' => 'Sopron',             'en' => 'Sopron'],              'lat' => 47.6850, 'lng' => 16.5943, 'population' => 63000, 'featured' => true],
                    ['slug' => 'hu-mosonmagyarovar',     'name' => ['ro' => 'Mosonmagyaróvár',    'en' => 'Mosonmagyarovar'],     'lat' => 47.8667, 'lng' => 17.2667, 'population' => 33000],
                    ['slug' => 'hu-fertod',              'name' => ['ro' => 'Fertőd',             'en' => 'Fertod'],              'lat' => 47.6167, 'lng' => 16.8500, 'population' => 3000, 'featured' => true],
                ],
            ],
            [
                'code' => 'HU-VA', 'region' => 'HU-ND', 'slug' => 'hu-vas', 'sort' => 13,
                'name' => ['ro' => 'Comitatul Vas', 'en' => 'Vas County'],
                'cities' => [
                    ['slug' => 'hu-szombathely', 'name' => ['ro' => 'Szombathely', 'en' => 'Szombathely'], 'lat' => 47.2307, 'lng' => 16.6218, 'population' => 78000, 'capital' => true],
                    ['slug' => 'hu-sarvar',      'name' => ['ro' => 'Sárvár',      'en' => 'Sarvar'],      'lat' => 47.2500, 'lng' => 16.9333, 'population' => 15000, 'featured' => true],
                    ['slug' => 'hu-koszeg',      'name' => ['ro' => 'Kőszeg',      'en' => 'Koszeg'],      'lat' => 47.3833, 'lng' => 16.5333, 'population' => 11000, 'featured' => true],
                ],
            ],
            [
                'code' => 'HU-ZA', 'region' => 'HU-ND', 'slug' => 'hu-zala', 'sort' => 14,
                'name' => ['ro' => 'Comitatul Zala', 'en' => 'Zala County'],
                'cities' => [
                    ['slug' => 'hu-zalaegerszeg', 'name' => ['ro' => 'Zalaegerszeg', 'en' => 'Zalaegerszeg'], 'lat' => 46.8417, 'lng' => 16.8441, 'population' => 60000, 'capital' => true],
                    ['slug' => 'hu-nagykanizsa',  'name' => ['ro' => 'Nagykanizsa',  'en' => 'Nagykanizsa'],  'lat' => 46.4500, 'lng' => 16.9833, 'population' => 50000],
                    ['slug' => 'hu-keszthely',    'name' => ['ro' => 'Keszthely',    'en' => 'Keszthely'],    'lat' => 46.7667, 'lng' => 17.2500, 'population' => 20000, 'featured' => true],
                    ['slug' => 'hu-heviz',        'name' => ['ro' => 'Hévíz',        'en' => 'Heviz'],        'lat' => 46.7897, 'lng' => 17.1892, 'population' => 4500, 'featured' => true],
                ],
            ],
            // ---- KÖZÉP-DUNÁNTÚL (Central Transdanubia) ----
            [
                'code' => 'HU-FE', 'region' => 'HU-KD', 'slug' => 'hu-fejer', 'sort' => 15,
                'name' => ['ro' => 'Comitatul Fejér', 'en' => 'Fejer County'],
                'cities' => [
                    ['slug' => 'hu-szekesfehervar', 'name' => ['ro' => 'Székesfehérvár', 'en' => 'Szekesfehervar'], 'lat' => 47.1860, 'lng' => 18.4221, 'population' => 100000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-dunaujvaros',    'name' => ['ro' => 'Dunaújváros',    'en' => 'Dunaujvaros'],    'lat' => 46.9756, 'lng' => 18.9353, 'population' => 47000],
                ],
            ],
            [
                'code' => 'HU-KE', 'region' => 'HU-KD', 'slug' => 'hu-komarom-esztergom', 'sort' => 16,
                'name' => ['ro' => 'Komárom-Esztergom', 'en' => 'Komarom-Esztergom'],
                'cities' => [
                    ['slug' => 'hu-tatabanya',  'name' => ['ro' => 'Tatabánya',  'en' => 'Tatabanya'],  'lat' => 47.5694, 'lng' => 18.3944, 'population' => 66000, 'capital' => true],
                    ['slug' => 'hu-komarom',    'name' => ['ro' => 'Komárom',    'en' => 'Komarom'],    'lat' => 47.7333, 'lng' => 18.1167, 'population' => 20000],
                    ['slug' => 'hu-dorог',      'name' => ['ro' => 'Dorog',      'en' => 'Dorog'],      'lat' => 47.7167, 'lng' => 18.7167, 'population' => 13000],
                ],
            ],
            [
                'code' => 'HU-VE', 'region' => 'HU-KD', 'slug' => 'hu-veszprem', 'sort' => 17,
                'name' => ['ro' => 'Comitatul Veszprém', 'en' => 'Veszprem County'],
                'cities' => [
                    ['slug' => 'hu-veszprem',    'name' => ['ro' => 'Veszprém',   'en' => 'Veszprem'],   'lat' => 47.0928, 'lng' => 17.9086, 'population' => 55000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-ajka',        'name' => ['ro' => 'Ajka',       'en' => 'Ajka'],       'lat' => 47.1000, 'lng' => 17.5667, 'population' => 28000],
                    ['slug' => 'hu-siofok',      'name' => ['ro' => 'Siófok',     'en' => 'Siofok'],     'lat' => 46.9033, 'lng' => 18.0536, 'population' => 25000, 'featured' => true],
                    ['slug' => 'hu-balatonfured','name' => ['ro' => 'Balatonfüred','en' => 'Balatonfured'],'lat' => 46.9500, 'lng' => 17.8833, 'population' => 13000, 'featured' => true],
                ],
            ],
            // ---- DÉL-DUNÁNTÚL (Southern Transdanubia) ----
            [
                'code' => 'HU-BA', 'region' => 'HU-DD', 'slug' => 'hu-baranya', 'sort' => 18,
                'name' => ['ro' => 'Comitatul Baranya', 'en' => 'Baranya County'],
                'cities' => [
                    ['slug' => 'hu-pecs',     'name' => ['ro' => 'Peci',    'en' => 'Pecs'],     'lat' => 46.0727, 'lng' => 18.2323, 'population' => 145000, 'capital' => true, 'featured' => true],
                    ['slug' => 'hu-mohacs',   'name' => ['ro' => 'Mohács',  'en' => 'Mohacs'],   'lat' => 45.9903, 'lng' => 18.6756, 'population' => 19000],
                    ['slug' => 'hu-komlo',    'name' => ['ro' => 'Komló',   'en' => 'Komlo'],    'lat' => 46.2000, 'lng' => 18.2667, 'population' => 23000],
                    ['slug' => 'hu-villany',  'name' => ['ro' => 'Villány', 'en' => 'Villany'],  'lat' => 45.8667, 'lng' => 18.4500, 'population' => 2600, 'featured' => true],
                ],
            ],
            [
                'code' => 'HU-SO', 'region' => 'HU-DD', 'slug' => 'hu-somogy', 'sort' => 19,
                'name' => ['ro' => 'Comitatul Somogy', 'en' => 'Somogy County'],
                'cities' => [
                    ['slug' => 'hu-kaposvar',  'name' => ['ro' => 'Kaposvár',  'en' => 'Kaposvar'],  'lat' => 46.3597, 'lng' => 17.7958, 'population' => 64000, 'capital' => true],
                    ['slug' => 'hu-fonyod',    'name' => ['ro' => 'Fonyód',    'en' => 'Fonyod'],    'lat' => 46.7500, 'lng' => 17.5667, 'population' => 5000, 'featured' => true],
                    ['slug' => 'hu-balatonboglar', 'name' => ['ro' => 'Balatonboglár', 'en' => 'Balatonboglar'], 'lat' => 46.7692, 'lng' => 17.6342, 'population' => 6000, 'featured' => true],
                ],
            ],
            [
                'code' => 'HU-TO', 'region' => 'HU-DD', 'slug' => 'hu-tolna', 'sort' => 20,
                'name' => ['ro' => 'Comitatul Tolna', 'en' => 'Tolna County'],
                'cities' => [
                    ['slug' => 'hu-szekszard',  'name' => ['ro' => 'Szekszárd',  'en' => 'Szekszard'],  'lat' => 46.3478, 'lng' => 18.7061, 'population' => 34000, 'capital' => true],
                    ['slug' => 'hu-bonyhad',    'name' => ['ro' => 'Bonyhád',    'en' => 'Bonyhad'],    'lat' => 46.3000, 'lng' => 18.5167, 'population' => 12000],
                    ['slug' => 'hu-paks',       'name' => ['ro' => 'Paks',       'en' => 'Paks'],       'lat' => 46.6222, 'lng' => 18.8583, 'population' => 19000],
                ],
            ],
        ];
    }

    /* =========================================================================
     * BULGARIA (България)
     * ===================================================================== */

    private function getBgRegions(): array
    {
        return [
            ['slug' => 'bg-yugozapaden',       'name' => ['ro' => 'Sud-Vest',          'en' => 'Southwest'],          'code' => 'BG-SW', 'featured' => true],
            ['slug' => 'bg-yuzhen-tsentralen', 'name' => ['ro' => 'Sud-Central',       'en' => 'South Central'],      'code' => 'BG-SC', 'featured' => true],
            ['slug' => 'bg-severoiztochen',    'name' => ['ro' => 'Nord-Est',          'en' => 'Northeast'],          'code' => 'BG-NE', 'featured' => true],
            ['slug' => 'bg-yugoiztochen',      'name' => ['ro' => 'Sud-Est',           'en' => 'Southeast'],          'code' => 'BG-SE', 'featured' => true],
            ['slug' => 'bg-severen-tsentralen','name' => ['ro' => 'Nord-Central',      'en' => 'North Central'],      'code' => 'BG-NC', 'featured' => false],
            ['slug' => 'bg-severozapaden',     'name' => ['ro' => 'Nord-Vest',         'en' => 'Northwest'],          'code' => 'BG-NW', 'featured' => false],
        ];
    }

    private function getBgCounties(): array
    {
        return [
            // ---- SOUTHWEST (Sofia, Blagoevgrad, Kyustendil, Pernik) ----
            [
                'code' => 'BG-SFG', 'region' => 'BG-SW', 'slug' => 'bg-sofia-grad', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Sofia (grad)', 'en' => 'Sofia (city)'],
                'cities' => [
                    ['slug' => 'bg-sofia', 'name' => ['ro' => 'Sofia', 'en' => 'Sofia'], 'lat' => 42.6977, 'lng' => 23.3219, 'population' => 1307000, 'featured' => true, 'capital' => true],
                    ['slug' => 'bg-bankya','name' => ['ro' => 'Bankya', 'en' => 'Bankya'],'lat' => 42.7000, 'lng' => 23.1333, 'population' => 9000, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-SFO', 'region' => 'BG-SW', 'slug' => 'bg-sofia-oblast', 'sort' => 2,
                'name' => ['ro' => 'Provincia Sofia', 'en' => 'Sofia Province'],
                'cities' => [
                    ['slug' => 'bg-pernik-sfo',  'name' => ['ro' => 'Botevgrad',  'en' => 'Botevgrad'],  'lat' => 42.9000, 'lng' => 23.7833, 'population' => 20000, 'capital' => true],
                    ['slug' => 'bg-samokov',     'name' => ['ro' => 'Samokov',    'en' => 'Samokov'],    'lat' => 42.3333, 'lng' => 23.5500, 'population' => 25000],
                    ['slug' => 'bg-borovets',    'name' => ['ro' => 'Borovets',   'en' => 'Borovets'],   'lat' => 42.2667, 'lng' => 23.5833, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-BL', 'region' => 'BG-SW', 'slug' => 'bg-blagoevgrad', 'sort' => 3,
                'name' => ['ro' => 'Blagoevgrad', 'en' => 'Blagoevgrad'],
                'cities' => [
                    ['slug' => 'bg-blagoevgrad', 'name' => ['ro' => 'Blagoevgrad', 'en' => 'Blagoevgrad'], 'lat' => 42.0139, 'lng' => 23.0967, 'population' => 65000, 'capital' => true],
                    ['slug' => 'bg-bansko',      'name' => ['ro' => 'Bansko',      'en' => 'Bansko'],      'lat' => 41.8333, 'lng' => 23.4833, 'population' => 10000, 'featured' => true],
                    ['slug' => 'bg-sandanski',   'name' => ['ro' => 'Sandanski',   'en' => 'Sandanski'],   'lat' => 41.5667, 'lng' => 23.2833, 'population' => 24000],
                    ['slug' => 'bg-petrich',     'name' => ['ro' => 'Petrich',     'en' => 'Petrich'],     'lat' => 41.3944, 'lng' => 23.2097, 'population' => 25000],
                    ['slug' => 'bg-melnik',      'name' => ['ro' => 'Melnik',      'en' => 'Melnik'],      'lat' => 41.5167, 'lng' => 23.3833, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-KN', 'region' => 'BG-SW', 'slug' => 'bg-kyustendil', 'sort' => 4,
                'name' => ['ro' => 'Kyustendil', 'en' => 'Kyustendil'],
                'cities' => [
                    ['slug' => 'bg-kyustendil', 'name' => ['ro' => 'Kyustendil', 'en' => 'Kyustendil'], 'lat' => 42.2833, 'lng' => 22.6833, 'population' => 42000, 'capital' => true],
                    ['slug' => 'bg-dupnitsa',   'name' => ['ro' => 'Dupnitsa',   'en' => 'Dupnitsa'],   'lat' => 42.2667, 'lng' => 23.1167, 'population' => 35000],
                ],
            ],
            [
                'code' => 'BG-PK', 'region' => 'BG-SW', 'slug' => 'bg-pernik', 'sort' => 5,
                'name' => ['ro' => 'Pernik', 'en' => 'Pernik'],
                'cities' => [
                    ['slug' => 'bg-pernik',     'name' => ['ro' => 'Pernik',     'en' => 'Pernik'],     'lat' => 42.6000, 'lng' => 23.0333, 'population' => 73000, 'capital' => true],
                    ['slug' => 'bg-radomir',    'name' => ['ro' => 'Radomir',    'en' => 'Radomir'],    'lat' => 42.5500, 'lng' => 22.9667, 'population' => 16000],
                ],
            ],
            // ---- SOUTH CENTRAL (Plovdiv, Pazardzhik, Haskovo, Kardzhali, Smolyan) ----
            [
                'code' => 'BG-PD', 'region' => 'BG-SC', 'slug' => 'bg-plovdiv', 'featured' => true, 'sort' => 6,
                'name' => ['ro' => 'Plovdiv', 'en' => 'Plovdiv'],
                'cities' => [
                    ['slug' => 'bg-plovdiv',    'name' => ['ro' => 'Plovdiv',    'en' => 'Plovdiv'],    'lat' => 42.1500, 'lng' => 24.7500, 'population' => 342000, 'featured' => true, 'capital' => true],
                    ['slug' => 'bg-asenovgrad', 'name' => ['ro' => 'Asenovgrad', 'en' => 'Asenovgrad'], 'lat' => 42.0167, 'lng' => 24.8833, 'population' => 50000],
                    ['slug' => 'bg-karlovo',    'name' => ['ro' => 'Karlovo',    'en' => 'Karlovo'],    'lat' => 42.6333, 'lng' => 24.8000, 'population' => 20000, 'featured' => true],
                    ['slug' => 'bg-sopot',      'name' => ['ro' => 'Sopot',      'en' => 'Sopot'],      'lat' => 42.6667, 'lng' => 24.7333, 'population' => 12000],
                ],
            ],
            [
                'code' => 'BG-PAZ', 'region' => 'BG-SC', 'slug' => 'bg-pazardzhik', 'sort' => 7,
                'name' => ['ro' => 'Pazardzhik', 'en' => 'Pazardzhik'],
                'cities' => [
                    ['slug' => 'bg-pazardzhik', 'name' => ['ro' => 'Pazardzhik', 'en' => 'Pazardzhik'], 'lat' => 42.2000, 'lng' => 24.3333, 'population' => 67000, 'capital' => true],
                    ['slug' => 'bg-velingrad',  'name' => ['ro' => 'Velingrad',  'en' => 'Velingrad'],  'lat' => 42.0333, 'lng' => 23.9833, 'population' => 22000, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-HS', 'region' => 'BG-SC', 'slug' => 'bg-haskovo', 'sort' => 8,
                'name' => ['ro' => 'Haskovo', 'en' => 'Haskovo'],
                'cities' => [
                    ['slug' => 'bg-haskovo',    'name' => ['ro' => 'Haskovo',    'en' => 'Haskovo'],    'lat' => 41.9333, 'lng' => 25.5500, 'population' => 72000, 'capital' => true],
                    ['slug' => 'bg-harmanli',   'name' => ['ro' => 'Harmanli',   'en' => 'Harmanli'],   'lat' => 41.9333, 'lng' => 25.9000, 'population' => 18000],
                    ['slug' => 'bg-svilengrad', 'name' => ['ro' => 'Svilengrad', 'en' => 'Svilengrad'], 'lat' => 41.7667, 'lng' => 26.2000, 'population' => 17000],
                ],
            ],
            [
                'code' => 'BG-KR', 'region' => 'BG-SC', 'slug' => 'bg-kardzhali', 'sort' => 9,
                'name' => ['ro' => 'Kardzhali', 'en' => 'Kardzhali'],
                'cities' => [
                    ['slug' => 'bg-kardzhali', 'name' => ['ro' => 'Kardzhali', 'en' => 'Kardzhali'], 'lat' => 41.6333, 'lng' => 25.3667, 'population' => 49000, 'capital' => true],
                ],
            ],
            [
                'code' => 'BG-SM', 'region' => 'BG-SC', 'slug' => 'bg-smolyan', 'sort' => 10,
                'name' => ['ro' => 'Smolyan', 'en' => 'Smolyan'],
                'cities' => [
                    ['slug' => 'bg-smolyan',  'name' => ['ro' => 'Smolyan',  'en' => 'Smolyan'],  'lat' => 41.5750, 'lng' => 24.7000, 'population' => 27000, 'capital' => true],
                    ['slug' => 'bg-pamporovo','name' => ['ro' => 'Pamporovo','en' => 'Pamporovo'],'lat' => 41.6167, 'lng' => 24.7167, 'featured' => true],
                    ['slug' => 'bg-devin',    'name' => ['ro' => 'Devin',    'en' => 'Devin'],    'lat' => 41.7333, 'lng' => 24.4000, 'population' => 7000, 'featured' => true],
                ],
            ],
            // ---- NORTHEAST (Varna, Dobrich, Razgrad, Shumen, Silistra, Targovishte) ----
            [
                'code' => 'BG-VN', 'region' => 'BG-NE', 'slug' => 'bg-varna', 'featured' => true, 'sort' => 11,
                'name' => ['ro' => 'Varna', 'en' => 'Varna'],
                'cities' => [
                    ['slug' => 'bg-varna',         'name' => ['ro' => 'Varna',         'en' => 'Varna'],         'lat' => 43.2141, 'lng' => 27.9147, 'population' => 335000, 'featured' => true, 'capital' => true],
                    ['slug' => 'bg-golden-sands',  'name' => ['ro' => 'Nisipurile de Aur','en' => 'Golden Sands'], 'lat' => 43.2833, 'lng' => 28.0333, 'featured' => true],
                    ['slug' => 'bg-albena',        'name' => ['ro' => 'Albena',         'en' => 'Albena'],         'lat' => 43.3667, 'lng' => 28.0833, 'featured' => true],
                    ['slug' => 'bg-provadia',      'name' => ['ro' => 'Provadia',        'en' => 'Provadia'],       'lat' => 43.1833, 'lng' => 27.4500, 'population' => 13000],
                ],
            ],
            [
                'code' => 'BG-DO', 'region' => 'BG-NE', 'slug' => 'bg-dobrich', 'sort' => 12,
                'name' => ['ro' => 'Dobrich', 'en' => 'Dobrich'],
                'cities' => [
                    ['slug' => 'bg-dobrich',   'name' => ['ro' => 'Dobrich',   'en' => 'Dobrich'],   'lat' => 43.5667, 'lng' => 27.8167, 'population' => 84000, 'capital' => true],
                    ['slug' => 'bg-balchik',   'name' => ['ro' => 'Balchik',   'en' => 'Balchik'],   'lat' => 43.4167, 'lng' => 28.1667, 'population' => 12000, 'featured' => true],
                    ['slug' => 'bg-kavarna',   'name' => ['ro' => 'Kavarna',   'en' => 'Kavarna'],   'lat' => 43.4333, 'lng' => 28.3500, 'population' => 12000, 'featured' => true],
                    ['slug' => 'bg-general-toshevo', 'name' => ['ro' => 'General Toshevo', 'en' => 'General Toshevo'], 'lat' => 43.7000, 'lng' => 28.0333, 'population' => 10000],
                ],
            ],
            [
                'code' => 'BG-SH', 'region' => 'BG-NE', 'slug' => 'bg-shumen', 'sort' => 13,
                'name' => ['ro' => 'Shumen', 'en' => 'Shumen'],
                'cities' => [
                    ['slug' => 'bg-shumen',  'name' => ['ro' => 'Shumen',  'en' => 'Shumen'],  'lat' => 43.2708, 'lng' => 26.9228, 'population' => 80000, 'capital' => true],
                    ['slug' => 'bg-novi-pazar-bg', 'name' => ['ro' => 'Novi Pazar', 'en' => 'Novi Pazar (BG)'], 'lat' => 43.3500, 'lng' => 27.2000, 'population' => 14000],
                ],
            ],
            [
                'code' => 'BG-RG', 'region' => 'BG-NE', 'slug' => 'bg-razgrad', 'sort' => 14,
                'name' => ['ro' => 'Razgrad', 'en' => 'Razgrad'],
                'cities' => [
                    ['slug' => 'bg-razgrad',  'name' => ['ro' => 'Razgrad',  'en' => 'Razgrad'],  'lat' => 43.5333, 'lng' => 26.5167, 'population' => 35000, 'capital' => true],
                    ['slug' => 'bg-isperih',  'name' => ['ro' => 'Isperih',  'en' => 'Isperih'],  'lat' => 43.7167, 'lng' => 26.8333, 'population' => 10000],
                ],
            ],
            [
                'code' => 'BG-SS', 'region' => 'BG-NE', 'slug' => 'bg-silistra', 'sort' => 15,
                'name' => ['ro' => 'Silistra', 'en' => 'Silistra'],
                'cities' => [
                    ['slug' => 'bg-silistra', 'name' => ['ro' => 'Silistra', 'en' => 'Silistra'], 'lat' => 44.1167, 'lng' => 27.2667, 'population' => 35000, 'capital' => true],
                    ['slug' => 'bg-tutrakan', 'name' => ['ro' => 'Tutrakan', 'en' => 'Tutrakan'], 'lat' => 44.0500, 'lng' => 26.6167, 'population' => 11000],
                ],
            ],
            [
                'code' => 'BG-TG', 'region' => 'BG-NE', 'slug' => 'bg-targovishte', 'sort' => 16,
                'name' => ['ro' => 'Targovishte', 'en' => 'Targovishte'],
                'cities' => [
                    ['slug' => 'bg-targovishte', 'name' => ['ro' => 'Targovishte', 'en' => 'Targovishte'], 'lat' => 43.2500, 'lng' => 26.5667, 'population' => 35000, 'capital' => true],
                ],
            ],
            // ---- SOUTHEAST (Burgas, Sliven, Stara Zagora, Yambol) ----
            [
                'code' => 'BG-BS', 'region' => 'BG-SE', 'slug' => 'bg-burgas', 'featured' => true, 'sort' => 17,
                'name' => ['ro' => 'Burgas', 'en' => 'Burgas'],
                'cities' => [
                    ['slug' => 'bg-burgas',        'name' => ['ro' => 'Burgas',           'en' => 'Burgas'],           'lat' => 42.5048, 'lng' => 27.4626, 'population' => 200000, 'featured' => true, 'capital' => true],
                    ['slug' => 'bg-sunny-beach',   'name' => ['ro' => 'Sunny Beach',      'en' => 'Sunny Beach'],      'lat' => 42.6833, 'lng' => 27.7167, 'featured' => true],
                    ['slug' => 'bg-nessebar',      'name' => ['ro' => 'Nessebar',         'en' => 'Nessebar'],         'lat' => 42.6583, 'lng' => 27.7194, 'population' => 11000, 'featured' => true],
                    ['slug' => 'bg-sozopol',       'name' => ['ro' => 'Sozopol',          'en' => 'Sozopol'],          'lat' => 42.4167, 'lng' => 27.7000, 'featured' => true],
                    ['slug' => 'bg-pomorie',       'name' => ['ro' => 'Pomorie',          'en' => 'Pomorie'],          'lat' => 42.5500, 'lng' => 27.6333, 'population' => 13000, 'featured' => true],
                    ['slug' => 'bg-primorsko',     'name' => ['ro' => 'Primorsko',        'en' => 'Primorsko'],        'lat' => 42.2667, 'lng' => 27.7500, 'featured' => true],
                    ['slug' => 'bg-ahtopol',       'name' => ['ro' => 'Ahtopol',          'en' => 'Ahtopol'],          'lat' => 42.1000, 'lng' => 27.9500, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-SL', 'region' => 'BG-SE', 'slug' => 'bg-sliven', 'sort' => 18,
                'name' => ['ro' => 'Sliven', 'en' => 'Sliven'],
                'cities' => [
                    ['slug' => 'bg-sliven',  'name' => ['ro' => 'Sliven',  'en' => 'Sliven'],  'lat' => 42.6833, 'lng' => 26.3333, 'population' => 87000, 'capital' => true],
                    ['slug' => 'bg-nova-zagora', 'name' => ['ro' => 'Nova Zagora', 'en' => 'Nova Zagora'], 'lat' => 42.4833, 'lng' => 26.0167, 'population' => 22000],
                    ['slug' => 'bg-kotel',   'name' => ['ro' => 'Kotel',   'en' => 'Kotel'],   'lat' => 42.8833, 'lng' => 26.4333, 'population' => 5600, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-SZ', 'region' => 'BG-SE', 'slug' => 'bg-stara-zagora', 'sort' => 19,
                'name' => ['ro' => 'Stara Zagora', 'en' => 'Stara Zagora'],
                'cities' => [
                    ['slug' => 'bg-stara-zagora',   'name' => ['ro' => 'Stara Zagora',   'en' => 'Stara Zagora'],   'lat' => 42.4258, 'lng' => 25.6350, 'population' => 138000, 'capital' => true, 'featured' => true],
                    ['slug' => 'bg-kazanlak',       'name' => ['ro' => 'Kazanlak',       'en' => 'Kazanlak'],       'lat' => 42.6167, 'lng' => 25.4000, 'population' => 49000, 'featured' => true],
                    ['slug' => 'bg-chirpan',        'name' => ['ro' => 'Chirpan',        'en' => 'Chirpan'],        'lat' => 42.2000, 'lng' => 25.3333, 'population' => 15000],
                    ['slug' => 'bg-shipka',         'name' => ['ro' => 'Shipka',         'en' => 'Shipka'],         'lat' => 42.7167, 'lng' => 25.3333, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-YM', 'region' => 'BG-SE', 'slug' => 'bg-yambol', 'sort' => 20,
                'name' => ['ro' => 'Yambol', 'en' => 'Yambol'],
                'cities' => [
                    ['slug' => 'bg-yambol',  'name' => ['ro' => 'Yambol',  'en' => 'Yambol'],  'lat' => 42.4833, 'lng' => 26.5000, 'population' => 70000, 'capital' => true],
                    ['slug' => 'bg-elhovo',  'name' => ['ro' => 'Elhovo',  'en' => 'Elhovo'],  'lat' => 42.1667, 'lng' => 26.5667, 'population' => 10000],
                ],
            ],
            // ---- NORTH CENTRAL (Ruse, Pleven, Lovech, Gabrovo, Veliko Tarnovo) ----
            [
                'code' => 'BG-RU', 'region' => 'BG-NC', 'slug' => 'bg-ruse', 'sort' => 21,
                'name' => ['ro' => 'Ruse', 'en' => 'Ruse'],
                'cities' => [
                    ['slug' => 'bg-ruse',      'name' => ['ro' => 'Ruse',      'en' => 'Ruse'],      'lat' => 43.8483, 'lng' => 25.9539, 'population' => 140000, 'featured' => true, 'capital' => true],
                    ['slug' => 'bg-byala-ru',  'name' => ['ro' => 'Byala (Ruse)', 'en' => 'Byala (Ruse)'], 'lat' => 43.4667, 'lng' => 25.7333, 'population' => 13000],
                ],
            ],
            [
                'code' => 'BG-PL', 'region' => 'BG-NC', 'slug' => 'bg-pleven', 'sort' => 22,
                'name' => ['ro' => 'Pleven', 'en' => 'Pleven'],
                'cities' => [
                    ['slug' => 'bg-pleven',  'name' => ['ro' => 'Pleven',  'en' => 'Pleven'],  'lat' => 43.4167, 'lng' => 24.6167, 'population' => 100000, 'capital' => true],
                    ['slug' => 'bg-lukovit', 'name' => ['ro' => 'Lukovit', 'en' => 'Lukovit'], 'lat' => 43.2000, 'lng' => 24.1500, 'population' => 12000],
                ],
            ],
            [
                'code' => 'BG-LO', 'region' => 'BG-NC', 'slug' => 'bg-lovech', 'sort' => 23,
                'name' => ['ro' => 'Lovech', 'en' => 'Lovech'],
                'cities' => [
                    ['slug' => 'bg-lovech',    'name' => ['ro' => 'Lovech',    'en' => 'Lovech'],    'lat' => 43.1333, 'lng' => 24.7167, 'population' => 36000, 'capital' => true],
                    ['slug' => 'bg-troyan',    'name' => ['ro' => 'Troyan',    'en' => 'Troyan'],    'lat' => 42.8833, 'lng' => 24.7000, 'population' => 23000],
                    ['slug' => 'bg-teteven',   'name' => ['ro' => 'Teteven',   'en' => 'Teteven'],   'lat' => 42.9167, 'lng' => 24.2667, 'population' => 8000, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-GB', 'region' => 'BG-NC', 'slug' => 'bg-gabrovo', 'sort' => 24,
                'name' => ['ro' => 'Gabrovo', 'en' => 'Gabrovo'],
                'cities' => [
                    ['slug' => 'bg-gabrovo',   'name' => ['ro' => 'Gabrovo',   'en' => 'Gabrovo'],   'lat' => 42.8667, 'lng' => 25.3167, 'population' => 55000, 'capital' => true, 'featured' => true],
                    ['slug' => 'bg-sevlievo',  'name' => ['ro' => 'Sevlievo',  'en' => 'Sevlievo'],  'lat' => 43.0333, 'lng' => 25.1167, 'population' => 20000],
                    ['slug' => 'bg-tryavna',   'name' => ['ro' => 'Tryavna',   'en' => 'Tryavna'],   'lat' => 42.8667, 'lng' => 25.4833, 'population' => 9000, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-VT', 'region' => 'BG-NC', 'slug' => 'bg-veliko-tarnovo', 'sort' => 25,
                'name' => ['ro' => 'Veliko Tarnovo', 'en' => 'Veliko Tarnovo'],
                'cities' => [
                    ['slug' => 'bg-veliko-tarnovo', 'name' => ['ro' => 'Veliko Tarnovo', 'en' => 'Veliko Tarnovo'], 'lat' => 43.0817, 'lng' => 25.6519, 'population' => 68000, 'capital' => true, 'featured' => true],
                    ['slug' => 'bg-gorna-oryahovitsa', 'name' => ['ro' => 'Gorna Oryahovitsa', 'en' => 'Gorna Oryahovitsa'], 'lat' => 43.1333, 'lng' => 25.6833, 'population' => 34000],
                    ['slug' => 'bg-svishtov',       'name' => ['ro' => 'Svishtov',       'en' => 'Svishtov'],       'lat' => 43.6167, 'lng' => 25.3500, 'population' => 30000],
                    ['slug' => 'bg-arbanasi',       'name' => ['ro' => 'Arbanasi',       'en' => 'Arbanasi'],       'lat' => 43.1000, 'lng' => 25.6500, 'featured' => true],
                    ['slug' => 'bg-dryanovo',       'name' => ['ro' => 'Dryanovo',       'en' => 'Dryanovo'],       'lat' => 42.9667, 'lng' => 25.4667, 'population' => 8000],
                ],
            ],
            // ---- NORTHWEST (Vidin, Montana, Vratsa) ----
            [
                'code' => 'BG-VI', 'region' => 'BG-NW', 'slug' => 'bg-vidin', 'sort' => 26,
                'name' => ['ro' => 'Vidin', 'en' => 'Vidin'],
                'cities' => [
                    ['slug' => 'bg-vidin',   'name' => ['ro' => 'Vidin',   'en' => 'Vidin'],   'lat' => 43.9917, 'lng' => 22.8783, 'population' => 42000, 'capital' => true],
                    ['slug' => 'bg-belogradchik', 'name' => ['ro' => 'Belogradchik', 'en' => 'Belogradchik'], 'lat' => 43.6250, 'lng' => 22.6833, 'population' => 6000, 'featured' => true],
                ],
            ],
            [
                'code' => 'BG-MO', 'region' => 'BG-NW', 'slug' => 'bg-montana', 'sort' => 27,
                'name' => ['ro' => 'Montana', 'en' => 'Montana'],
                'cities' => [
                    ['slug' => 'bg-montana',  'name' => ['ro' => 'Montana',  'en' => 'Montana'],  'lat' => 43.4083, 'lng' => 23.2250, 'population' => 43000, 'capital' => true],
                    ['slug' => 'bg-berkovitsa','name' => ['ro' => 'Berkovitsa','en' => 'Berkovitsa'],'lat' => 43.2333, 'lng' => 23.1167, 'population' => 15000],
                ],
            ],
            [
                'code' => 'BG-VR', 'region' => 'BG-NW', 'slug' => 'bg-vratsa', 'sort' => 28,
                'name' => ['ro' => 'Vratsa', 'en' => 'Vratsa'],
                'cities' => [
                    ['slug' => 'bg-vratsa',     'name' => ['ro' => 'Vratsa',     'en' => 'Vratsa'],     'lat' => 43.2000, 'lng' => 23.5500, 'population' => 58000, 'capital' => true],
                    ['slug' => 'bg-mezdra',     'name' => ['ro' => 'Mezdra',     'en' => 'Mezdra'],     'lat' => 43.1500, 'lng' => 23.7000, 'population' => 10000],
                    ['slug' => 'bg-vratsa-ledenika', 'name' => ['ro' => 'Ledenika', 'en' => 'Ledenika'], 'lat' => 43.1333, 'lng' => 23.4500, 'featured' => true],
                ],
            ],
        ];
    }
}
