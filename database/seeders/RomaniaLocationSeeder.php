<?php

namespace Database\Seeders;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RomaniaLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Usage: MARKETPLACE_ID=1 php artisan db:seed --class=RomaniaLocationSeeder
     *
     * Hierarchy: Region → County (Județ) → City (Oraș)
     */
    public function run(): void
    {
        $marketplaceClientId = env('MARKETPLACE_ID', 1);

        $this->command->info("Seeding Romanian locations for marketplace_client_id: {$marketplaceClientId}");

        // Create Regions
        $regions = $this->createRegions($marketplaceClientId);

        // Create Counties with their cities
        $this->createCountiesAndCities($marketplaceClientId, $regions);

        $this->command->info("Seeding complete!");
    }

    private function createRegions(int $marketplaceClientId): array
    {
        $regionsData = [
            ['name' => ['ro' => 'Transilvania', 'en' => 'Transylvania'], 'code' => 'TR', 'featured' => true],
            ['name' => ['ro' => 'Muntenia', 'en' => 'Wallachia'], 'code' => 'MN', 'featured' => true],
            ['name' => ['ro' => 'Moldova', 'en' => 'Moldavia'], 'code' => 'MD', 'featured' => true],
            ['name' => ['ro' => 'Dobrogea', 'en' => 'Dobruja'], 'code' => 'DB', 'featured' => true],
            ['name' => ['ro' => 'Banat', 'en' => 'Banat'], 'code' => 'BN', 'featured' => true],
            ['name' => ['ro' => 'Oltenia', 'en' => 'Oltenia'], 'code' => 'OL', 'featured' => true],
            ['name' => ['ro' => 'Crișana', 'en' => 'Crisana'], 'code' => 'CR', 'featured' => false],
            ['name' => ['ro' => 'Maramureș', 'en' => 'Maramures'], 'code' => 'MM', 'featured' => false],
        ];

        $regions = [];
        $sortOrder = 0;

        foreach ($regionsData as $data) {
            $sortOrder++;
            $region = MarketplaceRegion::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => Str::slug($data['name']['ro']),
                ],
                [
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'country' => 'RO',
                    'sort_order' => $sortOrder,
                    'is_visible' => true,
                    'is_featured' => $data['featured'],
                ]
            );
            $regions[$data['code']] = $region;
            $this->command->info("Created region: {$data['name']['ro']}");
        }

        return $regions;
    }

    private function createCountiesAndCities(int $marketplaceClientId, array $regions): void
    {
        $countiesData = $this->getCountiesWithCities();

        foreach ($countiesData as $countyData) {
            $region = $regions[$countyData['region']] ?? null;

            $county = MarketplaceCounty::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'code' => $countyData['code'],
                ],
                [
                    'region_id' => $region?->id,
                    'name' => $countyData['name'],
                    'slug' => Str::slug($countyData['name']['ro']),
                    'country' => 'RO',
                    'sort_order' => $countyData['sort'] ?? 0,
                    'is_visible' => true,
                    'is_featured' => $countyData['featured'] ?? false,
                ]
            );

            $this->command->info("  Created county: {$countyData['code']} - {$countyData['name']['ro']}");

            // Create cities for this county
            $citySortOrder = 0;
            foreach ($countyData['cities'] as $cityData) {
                $citySortOrder++;

                MarketplaceCity::updateOrCreate(
                    [
                        'marketplace_client_id' => $marketplaceClientId,
                        'slug' => Str::slug($cityData['name']['ro']),
                    ],
                    [
                        'county_id' => $county->id,
                        'region_id' => $region?->id,
                        'name' => $cityData['name'],
                        'country' => 'RO',
                        'latitude' => $cityData['lat'] ?? null,
                        'longitude' => $cityData['lng'] ?? null,
                        'timezone' => 'Europe/Bucharest',
                        'population' => $cityData['population'] ?? null,
                        'sort_order' => $citySortOrder,
                        'is_visible' => true,
                        'is_featured' => $cityData['featured'] ?? false,
                        'is_capital' => $cityData['capital'] ?? false,
                    ]
                );
            }

            // Update city count
            $county->update(['city_count' => count($countyData['cities'])]);
        }
    }

    private function getCountiesWithCities(): array
    {
        return [
            // ===============================
            // MUNTENIA (Wallachia)
            // ===============================
            [
                'code' => 'B', 'region' => 'MN', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'București', 'en' => 'Bucharest'],
                'cities' => [
                    ['name' => ['ro' => 'București', 'en' => 'Bucharest'], 'lat' => 44.4268, 'lng' => 26.1025, 'population' => 1883425, 'featured' => true, 'capital' => true],
                ],
            ],
            [
                'code' => 'IF', 'region' => 'MN', 'sort' => 2,
                'name' => ['ro' => 'Ilfov', 'en' => 'Ilfov'],
                'cities' => [
                    ['name' => ['ro' => 'Voluntari', 'en' => 'Voluntari'], 'lat' => 44.4900, 'lng' => 26.1833, 'population' => 42944, 'capital' => true],
                    ['name' => ['ro' => 'Popești-Leordeni', 'en' => 'Popesti-Leordeni'], 'lat' => 44.3833, 'lng' => 26.1667, 'population' => 39667],
                    ['name' => ['ro' => 'Bragadiru', 'en' => 'Bragadiru'], 'lat' => 44.3667, 'lng' => 25.9833, 'population' => 22529],
                    ['name' => ['ro' => 'Buftea', 'en' => 'Buftea'], 'lat' => 44.5667, 'lng' => 25.9500, 'population' => 22178],
                    ['name' => ['ro' => 'Otopeni', 'en' => 'Otopeni'], 'lat' => 44.5500, 'lng' => 26.0833, 'population' => 17594],
                ],
            ],
            [
                'code' => 'PH', 'region' => 'MN', 'sort' => 3,
                'name' => ['ro' => 'Prahova', 'en' => 'Prahova'],
                'cities' => [
                    ['name' => ['ro' => 'Ploiești', 'en' => 'Ploiesti'], 'lat' => 44.9500, 'lng' => 26.0167, 'population' => 209945, 'capital' => true],
                    ['name' => ['ro' => 'Câmpina', 'en' => 'Campina'], 'lat' => 45.1167, 'lng' => 25.7333, 'population' => 33641],
                    ['name' => ['ro' => 'Sinaia', 'en' => 'Sinaia'], 'lat' => 45.3500, 'lng' => 25.5500, 'population' => 10310, 'featured' => true],
                    ['name' => ['ro' => 'Bușteni', 'en' => 'Busteni'], 'lat' => 45.4167, 'lng' => 25.5500, 'population' => 9154, 'featured' => true],
                    ['name' => ['ro' => 'Azuga', 'en' => 'Azuga'], 'lat' => 45.4500, 'lng' => 25.5833, 'population' => 4626],
                    ['name' => ['ro' => 'Breaza', 'en' => 'Breaza'], 'lat' => 45.1833, 'lng' => 25.6667, 'population' => 15902],
                ],
            ],
            [
                'code' => 'AG', 'region' => 'MN', 'sort' => 4,
                'name' => ['ro' => 'Argeș', 'en' => 'Arges'],
                'cities' => [
                    ['name' => ['ro' => 'Pitești', 'en' => 'Pitesti'], 'lat' => 44.8667, 'lng' => 24.8667, 'population' => 155383, 'capital' => true],
                    ['name' => ['ro' => 'Câmpulung', 'en' => 'Campulung'], 'lat' => 45.2667, 'lng' => 25.0500, 'population' => 34034],
                    ['name' => ['ro' => 'Curtea de Argeș', 'en' => 'Curtea de Arges'], 'lat' => 45.1333, 'lng' => 24.6833, 'population' => 27559],
                    ['name' => ['ro' => 'Mioveni', 'en' => 'Mioveni'], 'lat' => 44.9667, 'lng' => 24.9500, 'population' => 33306],
                ],
            ],
            [
                'code' => 'DB', 'region' => 'MN', 'sort' => 5,
                'name' => ['ro' => 'Dâmbovița', 'en' => 'Dambovita'],
                'cities' => [
                    ['name' => ['ro' => 'Târgoviște', 'en' => 'Targoviste'], 'lat' => 44.9333, 'lng' => 25.4500, 'population' => 79610, 'capital' => true],
                    ['name' => ['ro' => 'Moreni', 'en' => 'Moreni'], 'lat' => 44.9833, 'lng' => 25.6500, 'population' => 18214],
                    ['name' => ['ro' => 'Pucioasa', 'en' => 'Pucioasa'], 'lat' => 45.0667, 'lng' => 25.4333, 'population' => 14294],
                ],
            ],
            [
                'code' => 'BZ', 'region' => 'MN', 'sort' => 6,
                'name' => ['ro' => 'Buzău', 'en' => 'Buzau'],
                'cities' => [
                    ['name' => ['ro' => 'Buzău', 'en' => 'Buzau'], 'lat' => 45.1500, 'lng' => 26.8333, 'population' => 115494, 'capital' => true],
                    ['name' => ['ro' => 'Râmnicu Sărat', 'en' => 'Ramnicu Sarat'], 'lat' => 45.3833, 'lng' => 27.0500, 'population' => 33911],
                ],
            ],
            [
                'code' => 'GR', 'region' => 'MN', 'sort' => 7,
                'name' => ['ro' => 'Giurgiu', 'en' => 'Giurgiu'],
                'cities' => [
                    ['name' => ['ro' => 'Giurgiu', 'en' => 'Giurgiu'], 'lat' => 43.9000, 'lng' => 25.9667, 'population' => 61353, 'capital' => true],
                    ['name' => ['ro' => 'Bolintin-Vale', 'en' => 'Bolintin-Vale'], 'lat' => 44.4333, 'lng' => 25.7500, 'population' => 11753],
                ],
            ],
            [
                'code' => 'CL', 'region' => 'MN', 'sort' => 8,
                'name' => ['ro' => 'Călărași', 'en' => 'Calarasi'],
                'cities' => [
                    ['name' => ['ro' => 'Călărași', 'en' => 'Calarasi'], 'lat' => 44.2000, 'lng' => 27.3333, 'population' => 65181, 'capital' => true],
                    ['name' => ['ro' => 'Oltenița', 'en' => 'Oltenita'], 'lat' => 44.0833, 'lng' => 26.6333, 'population' => 24822],
                ],
            ],
            [
                'code' => 'IL', 'region' => 'MN', 'sort' => 9,
                'name' => ['ro' => 'Ialomița', 'en' => 'Ialomita'],
                'cities' => [
                    ['name' => ['ro' => 'Slobozia', 'en' => 'Slobozia'], 'lat' => 44.5667, 'lng' => 27.3667, 'population' => 52693, 'capital' => true],
                    ['name' => ['ro' => 'Fetești', 'en' => 'Fetesti'], 'lat' => 44.3833, 'lng' => 27.8333, 'population' => 30223],
                    ['name' => ['ro' => 'Urziceni', 'en' => 'Urziceni'], 'lat' => 44.7167, 'lng' => 26.6333, 'population' => 17404],
                ],
            ],
            [
                'code' => 'TR', 'region' => 'MN', 'sort' => 10,
                'name' => ['ro' => 'Teleorman', 'en' => 'Teleorman'],
                'cities' => [
                    ['name' => ['ro' => 'Alexandria', 'en' => 'Alexandria'], 'lat' => 43.9667, 'lng' => 25.3333, 'population' => 45434, 'capital' => true],
                    ['name' => ['ro' => 'Roșiori de Vede', 'en' => 'Rosiori de Vede'], 'lat' => 44.1000, 'lng' => 24.9833, 'population' => 27416],
                    ['name' => ['ro' => 'Turnu Măgurele', 'en' => 'Turnu Magurele'], 'lat' => 43.7500, 'lng' => 24.8833, 'population' => 26000],
                ],
            ],

            // ===============================
            // TRANSILVANIA (Transylvania)
            // ===============================
            [
                'code' => 'CJ', 'region' => 'TR', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Cluj', 'en' => 'Cluj'],
                'cities' => [
                    ['name' => ['ro' => 'Cluj-Napoca', 'en' => 'Cluj-Napoca'], 'lat' => 46.7712, 'lng' => 23.6236, 'population' => 324576, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Turda', 'en' => 'Turda'], 'lat' => 46.5667, 'lng' => 23.7833, 'population' => 47744],
                    ['name' => ['ro' => 'Dej', 'en' => 'Dej'], 'lat' => 47.1500, 'lng' => 23.8833, 'population' => 33497],
                    ['name' => ['ro' => 'Câmpia Turzii', 'en' => 'Campia Turzii'], 'lat' => 46.5500, 'lng' => 23.8833, 'population' => 23904],
                    ['name' => ['ro' => 'Gherla', 'en' => 'Gherla'], 'lat' => 47.0333, 'lng' => 23.9000, 'population' => 18162],
                    ['name' => ['ro' => 'Florești', 'en' => 'Floresti'], 'lat' => 46.7500, 'lng' => 23.4833, 'population' => 32000],
                ],
            ],
            [
                'code' => 'BV', 'region' => 'TR', 'featured' => true, 'sort' => 2,
                'name' => ['ro' => 'Brașov', 'en' => 'Brasov'],
                'cities' => [
                    ['name' => ['ro' => 'Brașov', 'en' => 'Brasov'], 'lat' => 45.6427, 'lng' => 25.5887, 'population' => 253200, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Făgăraș', 'en' => 'Fagaras'], 'lat' => 45.8500, 'lng' => 24.9667, 'population' => 30714],
                    ['name' => ['ro' => 'Săcele', 'en' => 'Sacele'], 'lat' => 45.6167, 'lng' => 25.6833, 'population' => 27033],
                    ['name' => ['ro' => 'Râșnov', 'en' => 'Rasnov'], 'lat' => 45.5833, 'lng' => 25.4667, 'population' => 15022],
                    ['name' => ['ro' => 'Predeal', 'en' => 'Predeal'], 'lat' => 45.5000, 'lng' => 25.5833, 'population' => 4594, 'featured' => true],
                    ['name' => ['ro' => 'Poiana Brașov', 'en' => 'Poiana Brasov'], 'lat' => 45.6000, 'lng' => 25.5500, 'featured' => true],
                    ['name' => ['ro' => 'Bran', 'en' => 'Bran'], 'lat' => 45.5167, 'lng' => 25.3667, 'featured' => true],
                ],
            ],
            [
                'code' => 'SB', 'region' => 'TR', 'featured' => true, 'sort' => 3,
                'name' => ['ro' => 'Sibiu', 'en' => 'Sibiu'],
                'cities' => [
                    ['name' => ['ro' => 'Sibiu', 'en' => 'Sibiu'], 'lat' => 45.7928, 'lng' => 24.1519, 'population' => 147245, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Mediaș', 'en' => 'Medias'], 'lat' => 46.1667, 'lng' => 24.3500, 'population' => 51135],
                    ['name' => ['ro' => 'Cisnădie', 'en' => 'Cisnadie'], 'lat' => 45.7167, 'lng' => 24.1500, 'population' => 14322],
                    ['name' => ['ro' => 'Păltiniș', 'en' => 'Paltinis'], 'lat' => 45.6667, 'lng' => 23.9333, 'featured' => true],
                ],
            ],
            [
                'code' => 'MS', 'region' => 'TR', 'sort' => 4,
                'name' => ['ro' => 'Mureș', 'en' => 'Mures'],
                'cities' => [
                    ['name' => ['ro' => 'Târgu Mureș', 'en' => 'Targu Mures'], 'lat' => 46.5386, 'lng' => 24.5513, 'population' => 134290, 'capital' => true],
                    ['name' => ['ro' => 'Sighișoara', 'en' => 'Sighisoara'], 'lat' => 46.2167, 'lng' => 24.7833, 'population' => 26370, 'featured' => true],
                    ['name' => ['ro' => 'Reghin', 'en' => 'Reghin'], 'lat' => 46.7833, 'lng' => 24.7167, 'population' => 33281],
                    ['name' => ['ro' => 'Sovata', 'en' => 'Sovata'], 'lat' => 46.6000, 'lng' => 25.0667, 'population' => 10385, 'featured' => true],
                ],
            ],
            [
                'code' => 'AB', 'region' => 'TR', 'sort' => 5,
                'name' => ['ro' => 'Alba', 'en' => 'Alba'],
                'cities' => [
                    ['name' => ['ro' => 'Alba Iulia', 'en' => 'Alba Iulia'], 'lat' => 46.0667, 'lng' => 23.5833, 'population' => 63536, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Aiud', 'en' => 'Aiud'], 'lat' => 46.3167, 'lng' => 23.7167, 'population' => 22876],
                    ['name' => ['ro' => 'Blaj', 'en' => 'Blaj'], 'lat' => 46.1833, 'lng' => 23.9167, 'population' => 17988],
                    ['name' => ['ro' => 'Sebeș', 'en' => 'Sebes'], 'lat' => 45.9500, 'lng' => 23.5667, 'population' => 24850],
                ],
            ],
            [
                'code' => 'HD', 'region' => 'TR', 'sort' => 6,
                'name' => ['ro' => 'Hunedoara', 'en' => 'Hunedoara'],
                'cities' => [
                    ['name' => ['ro' => 'Deva', 'en' => 'Deva'], 'lat' => 45.8833, 'lng' => 22.9000, 'population' => 61123, 'capital' => true],
                    ['name' => ['ro' => 'Hunedoara', 'en' => 'Hunedoara'], 'lat' => 45.7500, 'lng' => 22.9000, 'population' => 60525, 'featured' => true],
                    ['name' => ['ro' => 'Petroșani', 'en' => 'Petrosani'], 'lat' => 45.4167, 'lng' => 23.3667, 'population' => 37160],
                    ['name' => ['ro' => 'Orăștie', 'en' => 'Orastie'], 'lat' => 45.8500, 'lng' => 23.2000, 'population' => 18654],
                    ['name' => ['ro' => 'Sarmizegetusa', 'en' => 'Sarmizegetusa'], 'lat' => 45.5167, 'lng' => 23.3000, 'featured' => true],
                ],
            ],
            [
                'code' => 'BN', 'region' => 'TR', 'sort' => 7,
                'name' => ['ro' => 'Bistrița-Năsăud', 'en' => 'Bistrita-Nasaud'],
                'cities' => [
                    ['name' => ['ro' => 'Bistrița', 'en' => 'Bistrita'], 'lat' => 47.1333, 'lng' => 24.5000, 'population' => 75076, 'capital' => true],
                    ['name' => ['ro' => 'Năsăud', 'en' => 'Nasaud'], 'lat' => 47.2833, 'lng' => 24.4000, 'population' => 10164],
                    ['name' => ['ro' => 'Beclean', 'en' => 'Beclean'], 'lat' => 47.1833, 'lng' => 24.1833, 'population' => 11209],
                ],
            ],
            [
                'code' => 'CV', 'region' => 'TR', 'sort' => 8,
                'name' => ['ro' => 'Covasna', 'en' => 'Covasna'],
                'cities' => [
                    ['name' => ['ro' => 'Sfântu Gheorghe', 'en' => 'Sfantu Gheorghe'], 'lat' => 45.8667, 'lng' => 25.7833, 'population' => 56006, 'capital' => true],
                    ['name' => ['ro' => 'Târgu Secuiesc', 'en' => 'Targu Secuiesc'], 'lat' => 46.0000, 'lng' => 26.1333, 'population' => 18491],
                    ['name' => ['ro' => 'Covasna', 'en' => 'Covasna'], 'lat' => 45.8500, 'lng' => 26.1833, 'population' => 10464, 'featured' => true],
                ],
            ],
            [
                'code' => 'HR', 'region' => 'TR', 'sort' => 9,
                'name' => ['ro' => 'Harghita', 'en' => 'Harghita'],
                'cities' => [
                    ['name' => ['ro' => 'Miercurea Ciuc', 'en' => 'Miercurea Ciuc'], 'lat' => 46.3500, 'lng' => 25.8000, 'population' => 37980, 'capital' => true],
                    ['name' => ['ro' => 'Odorheiu Secuiesc', 'en' => 'Odorheiu Secuiesc'], 'lat' => 46.3000, 'lng' => 25.3000, 'population' => 34257],
                    ['name' => ['ro' => 'Gheorgheni', 'en' => 'Gheorgheni'], 'lat' => 46.7167, 'lng' => 25.5833, 'population' => 17634],
                    ['name' => ['ro' => 'Praid', 'en' => 'Praid'], 'lat' => 46.5333, 'lng' => 25.1333, 'featured' => true],
                    ['name' => ['ro' => 'Lacul Roșu', 'en' => 'Lacu Rosu'], 'lat' => 46.7833, 'lng' => 25.8000, 'featured' => true],
                ],
            ],
            [
                'code' => 'SJ', 'region' => 'TR', 'sort' => 10,
                'name' => ['ro' => 'Sălaj', 'en' => 'Salaj'],
                'cities' => [
                    ['name' => ['ro' => 'Zalău', 'en' => 'Zalau'], 'lat' => 47.1833, 'lng' => 23.0500, 'population' => 56202, 'capital' => true],
                    ['name' => ['ro' => 'Șimleu Silvaniei', 'en' => 'Simleu Silvaniei'], 'lat' => 47.2333, 'lng' => 22.8000, 'population' => 14401],
                    ['name' => ['ro' => 'Jibou', 'en' => 'Jibou'], 'lat' => 47.2667, 'lng' => 23.2500, 'population' => 10407],
                ],
            ],

            // ===============================
            // BANAT
            // ===============================
            [
                'code' => 'TM', 'region' => 'BN', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Timiș', 'en' => 'Timis'],
                'cities' => [
                    ['name' => ['ro' => 'Timișoara', 'en' => 'Timisoara'], 'lat' => 45.7489, 'lng' => 21.2087, 'population' => 319279, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Lugoj', 'en' => 'Lugoj'], 'lat' => 45.6833, 'lng' => 21.9000, 'population' => 40361],
                    ['name' => ['ro' => 'Sânnicolau Mare', 'en' => 'Sannicolau Mare'], 'lat' => 46.0667, 'lng' => 20.6333, 'population' => 11510],
                    ['name' => ['ro' => 'Jimbolia', 'en' => 'Jimbolia'], 'lat' => 45.7833, 'lng' => 20.7167, 'population' => 10376],
                    ['name' => ['ro' => 'Dumbrăvița', 'en' => 'Dumbravita'], 'lat' => 45.7833, 'lng' => 21.2333, 'population' => 15000],
                ],
            ],
            [
                'code' => 'CS', 'region' => 'BN', 'sort' => 2,
                'name' => ['ro' => 'Caraș-Severin', 'en' => 'Caras-Severin'],
                'cities' => [
                    ['name' => ['ro' => 'Reșița', 'en' => 'Resita'], 'lat' => 45.3000, 'lng' => 21.8833, 'population' => 73282, 'capital' => true],
                    ['name' => ['ro' => 'Caransebeș', 'en' => 'Caransebes'], 'lat' => 45.4167, 'lng' => 22.2167, 'population' => 23775],
                    ['name' => ['ro' => 'Băile Herculane', 'en' => 'Baile Herculane'], 'lat' => 44.8833, 'lng' => 22.4167, 'population' => 4979, 'featured' => true],
                ],
            ],

            // ===============================
            // CRIȘANA
            // ===============================
            [
                'code' => 'BH', 'region' => 'CR', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Bihor', 'en' => 'Bihor'],
                'cities' => [
                    ['name' => ['ro' => 'Oradea', 'en' => 'Oradea'], 'lat' => 47.0722, 'lng' => 21.9211, 'population' => 196367, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Salonta', 'en' => 'Salonta'], 'lat' => 46.8000, 'lng' => 21.6500, 'population' => 15610],
                    ['name' => ['ro' => 'Marghita', 'en' => 'Marghita'], 'lat' => 47.3500, 'lng' => 22.3167, 'population' => 14782],
                    ['name' => ['ro' => 'Băile Felix', 'en' => 'Baile Felix'], 'lat' => 46.9833, 'lng' => 21.9833, 'featured' => true],
                ],
            ],
            [
                'code' => 'AR', 'region' => 'CR', 'sort' => 2,
                'name' => ['ro' => 'Arad', 'en' => 'Arad'],
                'cities' => [
                    ['name' => ['ro' => 'Arad', 'en' => 'Arad'], 'lat' => 46.1667, 'lng' => 21.3167, 'population' => 159074, 'capital' => true],
                    ['name' => ['ro' => 'Ineu', 'en' => 'Ineu'], 'lat' => 46.4333, 'lng' => 21.8333, 'population' => 9364],
                    ['name' => ['ro' => 'Lipova', 'en' => 'Lipova'], 'lat' => 46.0833, 'lng' => 21.7000, 'population' => 10345],
                ],
            ],

            // ===============================
            // MARAMUREȘ
            // ===============================
            [
                'code' => 'MM', 'region' => 'MM', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Maramureș', 'en' => 'Maramures'],
                'cities' => [
                    ['name' => ['ro' => 'Baia Mare', 'en' => 'Baia Mare'], 'lat' => 47.6567, 'lng' => 23.5850, 'population' => 123738, 'capital' => true],
                    ['name' => ['ro' => 'Sighetu Marmației', 'en' => 'Sighetu Marmatiei'], 'lat' => 47.9333, 'lng' => 23.8833, 'population' => 37640, 'featured' => true],
                    ['name' => ['ro' => 'Borșa', 'en' => 'Borsa'], 'lat' => 47.6500, 'lng' => 24.6667, 'population' => 24852, 'featured' => true],
                    ['name' => ['ro' => 'Vișeu de Sus', 'en' => 'Viseu de Sus'], 'lat' => 47.7167, 'lng' => 24.4167, 'population' => 15370],
                    ['name' => ['ro' => 'Săpânța', 'en' => 'Sapanta'], 'lat' => 47.9667, 'lng' => 23.7000, 'featured' => true],
                ],
            ],
            [
                'code' => 'SM', 'region' => 'MM', 'sort' => 2,
                'name' => ['ro' => 'Satu Mare', 'en' => 'Satu Mare'],
                'cities' => [
                    ['name' => ['ro' => 'Satu Mare', 'en' => 'Satu Mare'], 'lat' => 47.7833, 'lng' => 22.8833, 'population' => 102411, 'capital' => true],
                    ['name' => ['ro' => 'Carei', 'en' => 'Carei'], 'lat' => 47.6833, 'lng' => 22.4667, 'population' => 21112],
                    ['name' => ['ro' => 'Negrești-Oaș', 'en' => 'Negresti-Oas'], 'lat' => 47.8667, 'lng' => 23.4333, 'population' => 15407],
                ],
            ],

            // ===============================
            // MOLDOVA (Moldavia)
            // ===============================
            [
                'code' => 'IS', 'region' => 'MD', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Iași', 'en' => 'Iasi'],
                'cities' => [
                    ['name' => ['ro' => 'Iași', 'en' => 'Iasi'], 'lat' => 47.1585, 'lng' => 27.6014, 'population' => 290422, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Pașcani', 'en' => 'Pascani'], 'lat' => 47.2500, 'lng' => 26.7167, 'population' => 37307],
                    ['name' => ['ro' => 'Târgu Frumos', 'en' => 'Targu Frumos'], 'lat' => 47.2167, 'lng' => 27.0000, 'population' => 12154],
                ],
            ],
            [
                'code' => 'BC', 'region' => 'MD', 'sort' => 2,
                'name' => ['ro' => 'Bacău', 'en' => 'Bacau'],
                'cities' => [
                    ['name' => ['ro' => 'Bacău', 'en' => 'Bacau'], 'lat' => 46.5833, 'lng' => 26.9000, 'population' => 144307, 'capital' => true],
                    ['name' => ['ro' => 'Onești', 'en' => 'Onesti'], 'lat' => 46.2500, 'lng' => 26.7500, 'population' => 40565],
                    ['name' => ['ro' => 'Moinești', 'en' => 'Moinesti'], 'lat' => 46.4500, 'lng' => 26.5000, 'population' => 22034],
                    ['name' => ['ro' => 'Slănic Moldova', 'en' => 'Slanic Moldova'], 'lat' => 46.2000, 'lng' => 26.4333, 'featured' => true],
                ],
            ],
            [
                'code' => 'SV', 'region' => 'MD', 'sort' => 3,
                'name' => ['ro' => 'Suceava', 'en' => 'Suceava'],
                'cities' => [
                    ['name' => ['ro' => 'Suceava', 'en' => 'Suceava'], 'lat' => 47.6514, 'lng' => 26.2556, 'population' => 92121, 'capital' => true],
                    ['name' => ['ro' => 'Fălticeni', 'en' => 'Falticeni'], 'lat' => 47.4667, 'lng' => 26.3000, 'population' => 27508],
                    ['name' => ['ro' => 'Rădăuți', 'en' => 'Radauti'], 'lat' => 47.8500, 'lng' => 25.9167, 'population' => 23822],
                    ['name' => ['ro' => 'Vatra Dornei', 'en' => 'Vatra Dornei'], 'lat' => 47.3500, 'lng' => 25.3500, 'population' => 14689, 'featured' => true],
                    ['name' => ['ro' => 'Gura Humorului', 'en' => 'Gura Humorului'], 'lat' => 47.5500, 'lng' => 25.8833, 'population' => 13667, 'featured' => true],
                ],
            ],
            [
                'code' => 'NT', 'region' => 'MD', 'sort' => 4,
                'name' => ['ro' => 'Neamț', 'en' => 'Neamt'],
                'cities' => [
                    ['name' => ['ro' => 'Piatra Neamț', 'en' => 'Piatra Neamt'], 'lat' => 46.9333, 'lng' => 26.3667, 'population' => 85055, 'capital' => true],
                    ['name' => ['ro' => 'Roman', 'en' => 'Roman'], 'lat' => 46.9167, 'lng' => 26.9167, 'population' => 50713],
                    ['name' => ['ro' => 'Târgu Neamț', 'en' => 'Targu Neamt'], 'lat' => 47.2000, 'lng' => 26.3667, 'population' => 18695],
                    ['name' => ['ro' => 'Bicaz', 'en' => 'Bicaz'], 'lat' => 46.8000, 'lng' => 26.0667, 'population' => 8047, 'featured' => true],
                    ['name' => ['ro' => 'Durău', 'en' => 'Durau'], 'lat' => 47.0500, 'lng' => 25.9667, 'featured' => true],
                ],
            ],
            [
                'code' => 'BT', 'region' => 'MD', 'sort' => 5,
                'name' => ['ro' => 'Botoșani', 'en' => 'Botosani'],
                'cities' => [
                    ['name' => ['ro' => 'Botoșani', 'en' => 'Botosani'], 'lat' => 47.7500, 'lng' => 26.6667, 'population' => 106847, 'capital' => true],
                    ['name' => ['ro' => 'Dorohoi', 'en' => 'Dorohoi'], 'lat' => 47.9500, 'lng' => 26.4000, 'population' => 27089],
                ],
            ],
            [
                'code' => 'GL', 'region' => 'MD', 'sort' => 6,
                'name' => ['ro' => 'Galați', 'en' => 'Galati'],
                'cities' => [
                    ['name' => ['ro' => 'Galați', 'en' => 'Galati'], 'lat' => 45.4353, 'lng' => 28.0497, 'population' => 249432, 'capital' => true],
                    ['name' => ['ro' => 'Tecuci', 'en' => 'Tecuci'], 'lat' => 45.8500, 'lng' => 27.4167, 'population' => 34871],
                ],
            ],
            [
                'code' => 'BR', 'region' => 'MD', 'sort' => 7,
                'name' => ['ro' => 'Brăila', 'en' => 'Braila'],
                'cities' => [
                    ['name' => ['ro' => 'Brăila', 'en' => 'Braila'], 'lat' => 45.2692, 'lng' => 27.9575, 'population' => 180302, 'capital' => true],
                    ['name' => ['ro' => 'Ianca', 'en' => 'Ianca'], 'lat' => 45.1333, 'lng' => 27.4667, 'population' => 10948],
                ],
            ],
            [
                'code' => 'VN', 'region' => 'MD', 'sort' => 8,
                'name' => ['ro' => 'Vrancea', 'en' => 'Vrancea'],
                'cities' => [
                    ['name' => ['ro' => 'Focșani', 'en' => 'Focsani'], 'lat' => 45.7000, 'lng' => 27.1833, 'population' => 79315, 'capital' => true],
                    ['name' => ['ro' => 'Adjud', 'en' => 'Adjud'], 'lat' => 46.1000, 'lng' => 27.1833, 'population' => 18126],
                ],
            ],
            [
                'code' => 'VS', 'region' => 'MD', 'sort' => 9,
                'name' => ['ro' => 'Vaslui', 'en' => 'Vaslui'],
                'cities' => [
                    ['name' => ['ro' => 'Vaslui', 'en' => 'Vaslui'], 'lat' => 46.6333, 'lng' => 27.7333, 'population' => 55407, 'capital' => true],
                    ['name' => ['ro' => 'Bârlad', 'en' => 'Barlad'], 'lat' => 46.2333, 'lng' => 27.6667, 'population' => 55837],
                    ['name' => ['ro' => 'Huși', 'en' => 'Husi'], 'lat' => 46.6833, 'lng' => 28.0500, 'population' => 26266],
                ],
            ],

            // ===============================
            // DOBROGEA (Dobruja)
            // ===============================
            [
                'code' => 'CT', 'region' => 'DB', 'featured' => true, 'sort' => 1,
                'name' => ['ro' => 'Constanța', 'en' => 'Constanta'],
                'cities' => [
                    ['name' => ['ro' => 'Constanța', 'en' => 'Constanta'], 'lat' => 44.1598, 'lng' => 28.6348, 'population' => 283872, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Mangalia', 'en' => 'Mangalia'], 'lat' => 43.8167, 'lng' => 28.5833, 'population' => 36364],
                    ['name' => ['ro' => 'Medgidia', 'en' => 'Medgidia'], 'lat' => 44.2500, 'lng' => 28.2667, 'population' => 39179],
                    ['name' => ['ro' => 'Năvodari', 'en' => 'Navodari'], 'lat' => 44.3167, 'lng' => 28.6333, 'population' => 34669],
                    ['name' => ['ro' => 'Mamaia', 'en' => 'Mamaia'], 'lat' => 44.2500, 'lng' => 28.6167, 'featured' => true],
                    ['name' => ['ro' => 'Eforie Nord', 'en' => 'Eforie Nord'], 'lat' => 44.0667, 'lng' => 28.6333],
                    ['name' => ['ro' => 'Neptun', 'en' => 'Neptun'], 'lat' => 43.8333, 'lng' => 28.6000],
                    ['name' => ['ro' => 'Costinești', 'en' => 'Costinesti'], 'lat' => 43.9500, 'lng' => 28.6333],
                    ['name' => ['ro' => 'Vama Veche', 'en' => 'Vama Veche'], 'lat' => 43.7500, 'lng' => 28.5833, 'featured' => true],
                ],
            ],
            [
                'code' => 'TL', 'region' => 'DB', 'featured' => true, 'sort' => 2,
                'name' => ['ro' => 'Tulcea', 'en' => 'Tulcea'],
                'cities' => [
                    ['name' => ['ro' => 'Tulcea', 'en' => 'Tulcea'], 'lat' => 45.1833, 'lng' => 28.8000, 'population' => 73707, 'capital' => true],
                    ['name' => ['ro' => 'Măcin', 'en' => 'Macin'], 'lat' => 45.2500, 'lng' => 28.1333, 'population' => 10324],
                    ['name' => ['ro' => 'Sulina', 'en' => 'Sulina'], 'lat' => 45.1500, 'lng' => 29.6667, 'population' => 3663, 'featured' => true],
                    ['name' => ['ro' => 'Sfântu Gheorghe', 'en' => 'Sfantu Gheorghe (Delta)'], 'lat' => 44.8833, 'lng' => 29.6000, 'featured' => true],
                    ['name' => ['ro' => 'Murighiol', 'en' => 'Murighiol'], 'lat' => 45.0333, 'lng' => 29.1667, 'featured' => true],
                ],
            ],

            // ===============================
            // OLTENIA
            // ===============================
            [
                'code' => 'DJ', 'region' => 'OL', 'sort' => 1,
                'name' => ['ro' => 'Dolj', 'en' => 'Dolj'],
                'cities' => [
                    ['name' => ['ro' => 'Craiova', 'en' => 'Craiova'], 'lat' => 44.3167, 'lng' => 23.8000, 'population' => 269506, 'capital' => true],
                    ['name' => ['ro' => 'Băilești', 'en' => 'Bailesti'], 'lat' => 44.0333, 'lng' => 23.3500, 'population' => 17969],
                    ['name' => ['ro' => 'Calafat', 'en' => 'Calafat'], 'lat' => 43.9833, 'lng' => 22.9333, 'population' => 16988],
                ],
            ],
            [
                'code' => 'OT', 'region' => 'OL', 'sort' => 2,
                'name' => ['ro' => 'Olt', 'en' => 'Olt'],
                'cities' => [
                    ['name' => ['ro' => 'Slatina', 'en' => 'Slatina'], 'lat' => 44.4333, 'lng' => 24.3667, 'population' => 70293, 'capital' => true],
                    ['name' => ['ro' => 'Caracal', 'en' => 'Caracal'], 'lat' => 44.1167, 'lng' => 24.3500, 'population' => 30954],
                ],
            ],
            [
                'code' => 'VL', 'region' => 'OL', 'sort' => 3,
                'name' => ['ro' => 'Vâlcea', 'en' => 'Valcea'],
                'cities' => [
                    ['name' => ['ro' => 'Râmnicu Vâlcea', 'en' => 'Ramnicu Valcea'], 'lat' => 45.1000, 'lng' => 24.3667, 'population' => 98776, 'capital' => true],
                    ['name' => ['ro' => 'Drăgășani', 'en' => 'Dragasani'], 'lat' => 44.6667, 'lng' => 24.2500, 'population' => 19202],
                    ['name' => ['ro' => 'Băile Olănești', 'en' => 'Baile Olanesti'], 'lat' => 45.2000, 'lng' => 24.2500, 'population' => 4494, 'featured' => true],
                    ['name' => ['ro' => 'Călimănești', 'en' => 'Calimanesti'], 'lat' => 45.2333, 'lng' => 24.3167, 'population' => 8191, 'featured' => true],
                    ['name' => ['ro' => 'Horezu', 'en' => 'Horezu'], 'lat' => 45.1500, 'lng' => 23.9833, 'population' => 6287, 'featured' => true],
                    ['name' => ['ro' => 'Voineasa', 'en' => 'Voineasa'], 'lat' => 45.4167, 'lng' => 23.9667, 'featured' => true],
                ],
            ],
            [
                'code' => 'GJ', 'region' => 'OL', 'sort' => 4,
                'name' => ['ro' => 'Gorj', 'en' => 'Gorj'],
                'cities' => [
                    ['name' => ['ro' => 'Târgu Jiu', 'en' => 'Targu Jiu'], 'lat' => 45.0333, 'lng' => 23.2833, 'population' => 82504, 'capital' => true],
                    ['name' => ['ro' => 'Motru', 'en' => 'Motru'], 'lat' => 44.8000, 'lng' => 22.9667, 'population' => 20875],
                    ['name' => ['ro' => 'Rânca', 'en' => 'Ranca'], 'lat' => 45.2833, 'lng' => 23.6833, 'featured' => true],
                ],
            ],
            [
                'code' => 'MH', 'region' => 'OL', 'sort' => 5,
                'name' => ['ro' => 'Mehedinți', 'en' => 'Mehedinti'],
                'cities' => [
                    ['name' => ['ro' => 'Drobeta-Turnu Severin', 'en' => 'Drobeta-Turnu Severin'], 'lat' => 44.6333, 'lng' => 22.6667, 'population' => 92617, 'capital' => true],
                    ['name' => ['ro' => 'Orșova', 'en' => 'Orsova'], 'lat' => 44.7167, 'lng' => 22.4000, 'population' => 10441, 'featured' => true],
                ],
            ],
        ];
    }
}
