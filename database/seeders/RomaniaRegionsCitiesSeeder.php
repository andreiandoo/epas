<?php

namespace Database\Seeders;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RomaniaRegionsCitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Usage: MARKETPLACE_ID=1 php artisan db:seed --class=RomaniaRegionsCitiesSeeder
     */
    public function run(): void
    {
        // Get marketplace_client_id from environment variable or use 1
        $marketplaceClientId = env('MARKETPLACE_ID', 1);

        $this->command->info("Seeding Romanian regions and cities for marketplace_client_id: {$marketplaceClientId}");

        $regions = $this->getRegionsWithCities();

        $sortOrder = 0;
        foreach ($regions as $regionData) {
            $sortOrder++;

            // Create region
            $region = MarketplaceRegion::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => Str::slug($regionData['name']['ro']),
                ],
                [
                    'name' => $regionData['name'],
                    'code' => $regionData['code'],
                    'country' => 'RO',
                    'sort_order' => $sortOrder,
                    'is_visible' => true,
                    'is_featured' => $regionData['featured'] ?? false,
                ]
            );

            $this->command->info("Created region: {$regionData['name']['ro']}");

            // Create cities for this region
            $citySortOrder = 0;
            foreach ($regionData['cities'] as $cityData) {
                $citySortOrder++;

                MarketplaceCity::updateOrCreate(
                    [
                        'marketplace_client_id' => $marketplaceClientId,
                        'slug' => Str::slug($cityData['name']['ro']),
                    ],
                    [
                        'region_id' => $region->id,
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
            $region->update(['city_count' => count($regionData['cities'])]);
        }

        $this->command->info("Seeding complete!");
    }

    /**
     * Get all Romanian regions with their cities
     */
    private function getRegionsWithCities(): array
    {
        return [
            // BUCUREȘTI
            [
                'name' => ['ro' => 'București', 'en' => 'Bucharest'],
                'code' => 'B',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'București', 'en' => 'Bucharest'], 'lat' => 44.4268, 'lng' => 26.1025, 'population' => 1883425, 'featured' => true, 'capital' => true],
                ],
            ],

            // ILFOV
            [
                'name' => ['ro' => 'Ilfov', 'en' => 'Ilfov'],
                'code' => 'IF',
                'cities' => [
                    ['name' => ['ro' => 'Voluntari', 'en' => 'Voluntari'], 'lat' => 44.4900, 'lng' => 26.1833, 'population' => 42944, 'capital' => true],
                    ['name' => ['ro' => 'Popești-Leordeni', 'en' => 'Popesti-Leordeni'], 'lat' => 44.3833, 'lng' => 26.1667, 'population' => 39667],
                    ['name' => ['ro' => 'Bragadiru', 'en' => 'Bragadiru'], 'lat' => 44.3667, 'lng' => 25.9833, 'population' => 22529],
                    ['name' => ['ro' => 'Buftea', 'en' => 'Buftea'], 'lat' => 44.5667, 'lng' => 25.9500, 'population' => 22178],
                    ['name' => ['ro' => 'Pantelimon', 'en' => 'Pantelimon'], 'lat' => 44.4500, 'lng' => 26.2000, 'population' => 25596],
                    ['name' => ['ro' => 'Otopeni', 'en' => 'Otopeni'], 'lat' => 44.5500, 'lng' => 26.0833, 'population' => 17594],
                    ['name' => ['ro' => 'Chitila', 'en' => 'Chitila'], 'lat' => 44.5000, 'lng' => 25.9833, 'population' => 14767],
                    ['name' => ['ro' => 'Măgurele', 'en' => 'Magurele'], 'lat' => 44.3500, 'lng' => 26.0333, 'population' => 11065],
                ],
            ],

            // CLUJ
            [
                'name' => ['ro' => 'Cluj', 'en' => 'Cluj'],
                'code' => 'CJ',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Cluj-Napoca', 'en' => 'Cluj-Napoca'], 'lat' => 46.7712, 'lng' => 23.6236, 'population' => 324576, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Turda', 'en' => 'Turda'], 'lat' => 46.5667, 'lng' => 23.7833, 'population' => 47744],
                    ['name' => ['ro' => 'Dej', 'en' => 'Dej'], 'lat' => 47.1500, 'lng' => 23.8833, 'population' => 33497],
                    ['name' => ['ro' => 'Câmpia Turzii', 'en' => 'Campia Turzii'], 'lat' => 46.5500, 'lng' => 23.8833, 'population' => 23904],
                    ['name' => ['ro' => 'Gherla', 'en' => 'Gherla'], 'lat' => 47.0333, 'lng' => 23.9000, 'population' => 18162],
                    ['name' => ['ro' => 'Huedin', 'en' => 'Huedin'], 'lat' => 46.8667, 'lng' => 23.0333, 'population' => 8907],
                    ['name' => ['ro' => 'Florești', 'en' => 'Floresti'], 'lat' => 46.7500, 'lng' => 23.4833, 'population' => 32000],
                    ['name' => ['ro' => 'Baciu', 'en' => 'Baciu'], 'lat' => 46.7833, 'lng' => 23.5000, 'population' => 12000],
                ],
            ],

            // TIMIȘ
            [
                'name' => ['ro' => 'Timiș', 'en' => 'Timis'],
                'code' => 'TM',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Timișoara', 'en' => 'Timisoara'], 'lat' => 45.7489, 'lng' => 21.2087, 'population' => 319279, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Lugoj', 'en' => 'Lugoj'], 'lat' => 45.6833, 'lng' => 21.9000, 'population' => 40361],
                    ['name' => ['ro' => 'Sânnicolau Mare', 'en' => 'Sannicolau Mare'], 'lat' => 46.0667, 'lng' => 20.6333, 'population' => 11510],
                    ['name' => ['ro' => 'Jimbolia', 'en' => 'Jimbolia'], 'lat' => 45.7833, 'lng' => 20.7167, 'population' => 10376],
                    ['name' => ['ro' => 'Făget', 'en' => 'Faget'], 'lat' => 45.8500, 'lng' => 22.1833, 'population' => 6937],
                    ['name' => ['ro' => 'Buziaș', 'en' => 'Buzias'], 'lat' => 45.6500, 'lng' => 21.6000, 'population' => 7421],
                    ['name' => ['ro' => 'Deta', 'en' => 'Deta'], 'lat' => 45.4000, 'lng' => 21.2167, 'population' => 5622],
                    ['name' => ['ro' => 'Recaș', 'en' => 'Recas'], 'lat' => 45.7833, 'lng' => 21.4833, 'population' => 8756],
                    ['name' => ['ro' => 'Giroc', 'en' => 'Giroc'], 'lat' => 45.7167, 'lng' => 21.2500, 'population' => 12000],
                    ['name' => ['ro' => 'Dumbrăvița', 'en' => 'Dumbravita'], 'lat' => 45.7833, 'lng' => 21.2333, 'population' => 15000],
                ],
            ],

            // CONSTANȚA
            [
                'name' => ['ro' => 'Constanța', 'en' => 'Constanta'],
                'code' => 'CT',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Constanța', 'en' => 'Constanta'], 'lat' => 44.1598, 'lng' => 28.6348, 'population' => 283872, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Mangalia', 'en' => 'Mangalia'], 'lat' => 43.8167, 'lng' => 28.5833, 'population' => 36364],
                    ['name' => ['ro' => 'Medgidia', 'en' => 'Medgidia'], 'lat' => 44.2500, 'lng' => 28.2667, 'population' => 39179],
                    ['name' => ['ro' => 'Năvodari', 'en' => 'Navodari'], 'lat' => 44.3167, 'lng' => 28.6333, 'population' => 34669],
                    ['name' => ['ro' => 'Cernavodă', 'en' => 'Cernavoda'], 'lat' => 44.3333, 'lng' => 28.0333, 'population' => 17124],
                    ['name' => ['ro' => 'Mamaia', 'en' => 'Mamaia'], 'lat' => 44.2500, 'lng' => 28.6167, 'featured' => true],
                    ['name' => ['ro' => 'Eforie Nord', 'en' => 'Eforie Nord'], 'lat' => 44.0667, 'lng' => 28.6333],
                    ['name' => ['ro' => 'Eforie Sud', 'en' => 'Eforie Sud'], 'lat' => 44.0333, 'lng' => 28.6500],
                    ['name' => ['ro' => 'Neptun', 'en' => 'Neptun'], 'lat' => 43.8333, 'lng' => 28.6000],
                    ['name' => ['ro' => 'Costinești', 'en' => 'Costinesti'], 'lat' => 43.9500, 'lng' => 28.6333],
                    ['name' => ['ro' => 'Vama Veche', 'en' => 'Vama Veche'], 'lat' => 43.7500, 'lng' => 28.5833, 'featured' => true],
                ],
            ],

            // BRAȘOV
            [
                'name' => ['ro' => 'Brașov', 'en' => 'Brasov'],
                'code' => 'BV',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Brașov', 'en' => 'Brasov'], 'lat' => 45.6427, 'lng' => 25.5887, 'population' => 253200, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Făgăraș', 'en' => 'Fagaras'], 'lat' => 45.8500, 'lng' => 24.9667, 'population' => 30714],
                    ['name' => ['ro' => 'Săcele', 'en' => 'Sacele'], 'lat' => 45.6167, 'lng' => 25.6833, 'population' => 27033],
                    ['name' => ['ro' => 'Codlea', 'en' => 'Codlea'], 'lat' => 45.7000, 'lng' => 25.4500, 'population' => 22095],
                    ['name' => ['ro' => 'Râșnov', 'en' => 'Rasnov'], 'lat' => 45.5833, 'lng' => 25.4667, 'population' => 15022],
                    ['name' => ['ro' => 'Zărnești', 'en' => 'Zarnesti'], 'lat' => 45.5667, 'lng' => 25.3333, 'population' => 22300],
                    ['name' => ['ro' => 'Predeal', 'en' => 'Predeal'], 'lat' => 45.5000, 'lng' => 25.5833, 'population' => 4594, 'featured' => true],
                    ['name' => ['ro' => 'Poiana Brașov', 'en' => 'Poiana Brasov'], 'lat' => 45.6000, 'lng' => 25.5500, 'featured' => true],
                    ['name' => ['ro' => 'Bran', 'en' => 'Bran'], 'lat' => 45.5167, 'lng' => 25.3667, 'featured' => true],
                ],
            ],

            // IAȘI
            [
                'name' => ['ro' => 'Iași', 'en' => 'Iasi'],
                'code' => 'IS',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Iași', 'en' => 'Iasi'], 'lat' => 47.1585, 'lng' => 27.6014, 'population' => 290422, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Pașcani', 'en' => 'Pascani'], 'lat' => 47.2500, 'lng' => 26.7167, 'population' => 37307],
                    ['name' => ['ro' => 'Târgu Frumos', 'en' => 'Targu Frumos'], 'lat' => 47.2167, 'lng' => 27.0000, 'population' => 12154],
                    ['name' => ['ro' => 'Hârlău', 'en' => 'Harlau'], 'lat' => 47.4333, 'lng' => 26.9000, 'population' => 10175],
                ],
            ],

            // PRAHOVA
            [
                'name' => ['ro' => 'Prahova', 'en' => 'Prahova'],
                'code' => 'PH',
                'cities' => [
                    ['name' => ['ro' => 'Ploiești', 'en' => 'Ploiesti'], 'lat' => 44.9500, 'lng' => 26.0167, 'population' => 209945, 'capital' => true],
                    ['name' => ['ro' => 'Câmpina', 'en' => 'Campina'], 'lat' => 45.1167, 'lng' => 25.7333, 'population' => 33641],
                    ['name' => ['ro' => 'Băicoi', 'en' => 'Baicoi'], 'lat' => 45.0333, 'lng' => 25.8667, 'population' => 17982],
                    ['name' => ['ro' => 'Breaza', 'en' => 'Breaza'], 'lat' => 45.1833, 'lng' => 25.6667, 'population' => 15902],
                    ['name' => ['ro' => 'Sinaia', 'en' => 'Sinaia'], 'lat' => 45.3500, 'lng' => 25.5500, 'population' => 10310, 'featured' => true],
                    ['name' => ['ro' => 'Bușteni', 'en' => 'Busteni'], 'lat' => 45.4167, 'lng' => 25.5500, 'population' => 9154, 'featured' => true],
                    ['name' => ['ro' => 'Azuga', 'en' => 'Azuga'], 'lat' => 45.4500, 'lng' => 25.5833, 'population' => 4626],
                    ['name' => ['ro' => 'Comarnic', 'en' => 'Comarnic'], 'lat' => 45.2500, 'lng' => 25.6333, 'population' => 11857],
                    ['name' => ['ro' => 'Vălenii de Munte', 'en' => 'Valenii de Munte'], 'lat' => 45.1833, 'lng' => 26.0333, 'population' => 11503],
                ],
            ],

            // SIBIU
            [
                'name' => ['ro' => 'Sibiu', 'en' => 'Sibiu'],
                'code' => 'SB',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Sibiu', 'en' => 'Sibiu'], 'lat' => 45.7928, 'lng' => 24.1519, 'population' => 147245, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Mediaș', 'en' => 'Medias'], 'lat' => 46.1667, 'lng' => 24.3500, 'population' => 51135],
                    ['name' => ['ro' => 'Cisnădie', 'en' => 'Cisnadie'], 'lat' => 45.7167, 'lng' => 24.1500, 'population' => 14322],
                    ['name' => ['ro' => 'Avrig', 'en' => 'Avrig'], 'lat' => 45.7167, 'lng' => 24.3667, 'population' => 13420],
                    ['name' => ['ro' => 'Dumbrăveni', 'en' => 'Dumbraveni'], 'lat' => 46.2333, 'lng' => 24.5667, 'population' => 7707],
                    ['name' => ['ro' => 'Tălmaciu', 'en' => 'Talmaciu'], 'lat' => 45.6667, 'lng' => 24.2667, 'population' => 7515],
                    ['name' => ['ro' => 'Păltiniș', 'en' => 'Paltinis'], 'lat' => 45.6667, 'lng' => 23.9333, 'featured' => true],
                ],
            ],

            // MUREȘ
            [
                'name' => ['ro' => 'Mureș', 'en' => 'Mures'],
                'code' => 'MS',
                'cities' => [
                    ['name' => ['ro' => 'Târgu Mureș', 'en' => 'Targu Mures'], 'lat' => 46.5386, 'lng' => 24.5513, 'population' => 134290, 'capital' => true],
                    ['name' => ['ro' => 'Reghin', 'en' => 'Reghin'], 'lat' => 46.7833, 'lng' => 24.7167, 'population' => 33281],
                    ['name' => ['ro' => 'Sighișoara', 'en' => 'Sighisoara'], 'lat' => 46.2167, 'lng' => 24.7833, 'population' => 26370, 'featured' => true],
                    ['name' => ['ro' => 'Târnăveni', 'en' => 'Tarnaveni'], 'lat' => 46.3333, 'lng' => 24.2667, 'population' => 22075],
                    ['name' => ['ro' => 'Luduș', 'en' => 'Ludus'], 'lat' => 46.4833, 'lng' => 24.1000, 'population' => 15294],
                    ['name' => ['ro' => 'Sovata', 'en' => 'Sovata'], 'lat' => 46.6000, 'lng' => 25.0667, 'population' => 10385, 'featured' => true],
                ],
            ],

            // DOLJ
            [
                'name' => ['ro' => 'Dolj', 'en' => 'Dolj'],
                'code' => 'DJ',
                'cities' => [
                    ['name' => ['ro' => 'Craiova', 'en' => 'Craiova'], 'lat' => 44.3167, 'lng' => 23.8000, 'population' => 269506, 'capital' => true],
                    ['name' => ['ro' => 'Băilești', 'en' => 'Bailesti'], 'lat' => 44.0333, 'lng' => 23.3500, 'population' => 17969],
                    ['name' => ['ro' => 'Calafat', 'en' => 'Calafat'], 'lat' => 43.9833, 'lng' => 22.9333, 'population' => 16988],
                    ['name' => ['ro' => 'Filiași', 'en' => 'Filiasi'], 'lat' => 44.5500, 'lng' => 23.5167, 'population' => 16785],
                    ['name' => ['ro' => 'Segarcea', 'en' => 'Segarcea'], 'lat' => 44.0833, 'lng' => 23.7500, 'population' => 7195],
                ],
            ],

            // GALAȚI
            [
                'name' => ['ro' => 'Galați', 'en' => 'Galati'],
                'code' => 'GL',
                'cities' => [
                    ['name' => ['ro' => 'Galați', 'en' => 'Galati'], 'lat' => 45.4353, 'lng' => 28.0497, 'population' => 249432, 'capital' => true],
                    ['name' => ['ro' => 'Tecuci', 'en' => 'Tecuci'], 'lat' => 45.8500, 'lng' => 27.4167, 'population' => 34871],
                    ['name' => ['ro' => 'Târgu Bujor', 'en' => 'Targu Bujor'], 'lat' => 45.8667, 'lng' => 27.9167, 'population' => 6435],
                    ['name' => ['ro' => 'Berești', 'en' => 'Beresti'], 'lat' => 45.9500, 'lng' => 27.9000, 'population' => 2787],
                ],
            ],

            // ARGEȘ
            [
                'name' => ['ro' => 'Argeș', 'en' => 'Arges'],
                'code' => 'AG',
                'cities' => [
                    ['name' => ['ro' => 'Pitești', 'en' => 'Pitesti'], 'lat' => 44.8667, 'lng' => 24.8667, 'population' => 155383, 'capital' => true],
                    ['name' => ['ro' => 'Câmpulung', 'en' => 'Campulung'], 'lat' => 45.2667, 'lng' => 25.0500, 'population' => 34034],
                    ['name' => ['ro' => 'Curtea de Argeș', 'en' => 'Curtea de Arges'], 'lat' => 45.1333, 'lng' => 24.6833, 'population' => 27559],
                    ['name' => ['ro' => 'Mioveni', 'en' => 'Mioveni'], 'lat' => 44.9667, 'lng' => 24.9500, 'population' => 33306],
                    ['name' => ['ro' => 'Costești', 'en' => 'Costesti'], 'lat' => 44.7000, 'lng' => 24.8833, 'population' => 10315],
                    ['name' => ['ro' => 'Topoloveni', 'en' => 'Topoloveni'], 'lat' => 44.8167, 'lng' => 25.0833, 'population' => 8764],
                ],
            ],

            // ARAD
            [
                'name' => ['ro' => 'Arad', 'en' => 'Arad'],
                'code' => 'AR',
                'cities' => [
                    ['name' => ['ro' => 'Arad', 'en' => 'Arad'], 'lat' => 46.1667, 'lng' => 21.3167, 'population' => 159074, 'capital' => true],
                    ['name' => ['ro' => 'Ineu', 'en' => 'Ineu'], 'lat' => 46.4333, 'lng' => 21.8333, 'population' => 9364],
                    ['name' => ['ro' => 'Lipova', 'en' => 'Lipova'], 'lat' => 46.0833, 'lng' => 21.7000, 'population' => 10345],
                    ['name' => ['ro' => 'Pecica', 'en' => 'Pecica'], 'lat' => 46.1667, 'lng' => 21.0667, 'population' => 12987],
                    ['name' => ['ro' => 'Curtici', 'en' => 'Curtici'], 'lat' => 46.3500, 'lng' => 21.3000, 'population' => 8267],
                    ['name' => ['ro' => 'Nădlac', 'en' => 'Nadlac'], 'lat' => 46.1667, 'lng' => 20.7500, 'population' => 7398],
                    ['name' => ['ro' => 'Chișineu-Criș', 'en' => 'Chisineu-Cris'], 'lat' => 46.5333, 'lng' => 21.5167, 'population' => 7930],
                ],
            ],

            // BIHOR
            [
                'name' => ['ro' => 'Bihor', 'en' => 'Bihor'],
                'code' => 'BH',
                'cities' => [
                    ['name' => ['ro' => 'Oradea', 'en' => 'Oradea'], 'lat' => 47.0722, 'lng' => 21.9211, 'population' => 196367, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Salonta', 'en' => 'Salonta'], 'lat' => 46.8000, 'lng' => 21.6500, 'population' => 15610],
                    ['name' => ['ro' => 'Marghita', 'en' => 'Marghita'], 'lat' => 47.3500, 'lng' => 22.3167, 'population' => 14782],
                    ['name' => ['ro' => 'Beiuș', 'en' => 'Beius'], 'lat' => 46.6667, 'lng' => 22.3500, 'population' => 10667],
                    ['name' => ['ro' => 'Aleșd', 'en' => 'Alesd'], 'lat' => 47.0667, 'lng' => 22.4000, 'population' => 9580],
                    ['name' => ['ro' => 'Stei', 'en' => 'Stei'], 'lat' => 46.5333, 'lng' => 22.4500, 'population' => 7108],
                    ['name' => ['ro' => 'Băile Felix', 'en' => 'Baile Felix'], 'lat' => 46.9833, 'lng' => 21.9833, 'featured' => true],
                    ['name' => ['ro' => 'Băile 1 Mai', 'en' => 'Baile 1 Mai'], 'lat' => 47.0000, 'lng' => 21.9500],
                ],
            ],

            // BACĂU
            [
                'name' => ['ro' => 'Bacău', 'en' => 'Bacau'],
                'code' => 'BC',
                'cities' => [
                    ['name' => ['ro' => 'Bacău', 'en' => 'Bacau'], 'lat' => 46.5833, 'lng' => 26.9000, 'population' => 144307, 'capital' => true],
                    ['name' => ['ro' => 'Onești', 'en' => 'Onesti'], 'lat' => 46.2500, 'lng' => 26.7500, 'population' => 40565],
                    ['name' => ['ro' => 'Moinești', 'en' => 'Moinesti'], 'lat' => 46.4500, 'lng' => 26.5000, 'population' => 22034],
                    ['name' => ['ro' => 'Comănești', 'en' => 'Comanesti'], 'lat' => 46.4167, 'lng' => 26.4500, 'population' => 22095],
                    ['name' => ['ro' => 'Buhuși', 'en' => 'Buhusi'], 'lat' => 46.7167, 'lng' => 26.7000, 'population' => 17970],
                    ['name' => ['ro' => 'Dărmănești', 'en' => 'Darmanesti'], 'lat' => 46.3667, 'lng' => 26.4833, 'population' => 12448],
                    ['name' => ['ro' => 'Târgu Ocna', 'en' => 'Targu Ocna'], 'lat' => 46.2833, 'lng' => 26.6167, 'population' => 11654],
                    ['name' => ['ro' => 'Slănic Moldova', 'en' => 'Slanic Moldova'], 'lat' => 46.2000, 'lng' => 26.4333, 'featured' => true],
                ],
            ],

            // HUNEDOARA
            [
                'name' => ['ro' => 'Hunedoara', 'en' => 'Hunedoara'],
                'code' => 'HD',
                'cities' => [
                    ['name' => ['ro' => 'Deva', 'en' => 'Deva'], 'lat' => 45.8833, 'lng' => 22.9000, 'population' => 61123, 'capital' => true],
                    ['name' => ['ro' => 'Hunedoara', 'en' => 'Hunedoara'], 'lat' => 45.7500, 'lng' => 22.9000, 'population' => 60525, 'featured' => true],
                    ['name' => ['ro' => 'Petroșani', 'en' => 'Petrosani'], 'lat' => 45.4167, 'lng' => 23.3667, 'population' => 37160],
                    ['name' => ['ro' => 'Lupeni', 'en' => 'Lupeni'], 'lat' => 45.3667, 'lng' => 23.2333, 'population' => 24623],
                    ['name' => ['ro' => 'Vulcan', 'en' => 'Vulcan'], 'lat' => 45.3833, 'lng' => 23.2667, 'population' => 24160],
                    ['name' => ['ro' => 'Orăștie', 'en' => 'Orastie'], 'lat' => 45.8500, 'lng' => 23.2000, 'population' => 18654],
                    ['name' => ['ro' => 'Brad', 'en' => 'Brad'], 'lat' => 46.1333, 'lng' => 22.7833, 'population' => 14387],
                    ['name' => ['ro' => 'Călan', 'en' => 'Calan'], 'lat' => 45.7333, 'lng' => 23.0167, 'population' => 11436],
                    ['name' => ['ro' => 'Sarmizegetusa', 'en' => 'Sarmizegetusa'], 'lat' => 45.5167, 'lng' => 23.3000, 'featured' => true],
                ],
            ],

            // SUCEAVA
            [
                'name' => ['ro' => 'Suceava', 'en' => 'Suceava'],
                'code' => 'SV',
                'cities' => [
                    ['name' => ['ro' => 'Suceava', 'en' => 'Suceava'], 'lat' => 47.6514, 'lng' => 26.2556, 'population' => 92121, 'capital' => true],
                    ['name' => ['ro' => 'Fălticeni', 'en' => 'Falticeni'], 'lat' => 47.4667, 'lng' => 26.3000, 'population' => 27508],
                    ['name' => ['ro' => 'Rădăuți', 'en' => 'Radauti'], 'lat' => 47.8500, 'lng' => 25.9167, 'population' => 23822],
                    ['name' => ['ro' => 'Câmpulung Moldovenesc', 'en' => 'Campulung Moldovenesc'], 'lat' => 47.5333, 'lng' => 25.5500, 'population' => 17329],
                    ['name' => ['ro' => 'Vatra Dornei', 'en' => 'Vatra Dornei'], 'lat' => 47.3500, 'lng' => 25.3500, 'population' => 14689, 'featured' => true],
                    ['name' => ['ro' => 'Gura Humorului', 'en' => 'Gura Humorului'], 'lat' => 47.5500, 'lng' => 25.8833, 'population' => 13667, 'featured' => true],
                    ['name' => ['ro' => 'Siret', 'en' => 'Siret'], 'lat' => 47.9500, 'lng' => 26.0667, 'population' => 8426],
                ],
            ],

            // ALBA
            [
                'name' => ['ro' => 'Alba', 'en' => 'Alba'],
                'code' => 'AB',
                'cities' => [
                    ['name' => ['ro' => 'Alba Iulia', 'en' => 'Alba Iulia'], 'lat' => 46.0667, 'lng' => 23.5833, 'population' => 63536, 'featured' => true, 'capital' => true],
                    ['name' => ['ro' => 'Aiud', 'en' => 'Aiud'], 'lat' => 46.3167, 'lng' => 23.7167, 'population' => 22876],
                    ['name' => ['ro' => 'Blaj', 'en' => 'Blaj'], 'lat' => 46.1833, 'lng' => 23.9167, 'population' => 17988],
                    ['name' => ['ro' => 'Sebeș', 'en' => 'Sebes'], 'lat' => 45.9500, 'lng' => 23.5667, 'population' => 24850],
                    ['name' => ['ro' => 'Cugir', 'en' => 'Cugir'], 'lat' => 45.8333, 'lng' => 23.3667, 'population' => 22112],
                    ['name' => ['ro' => 'Ocna Mureș', 'en' => 'Ocna Mures'], 'lat' => 46.3833, 'lng' => 23.8500, 'population' => 12991],
                    ['name' => ['ro' => 'Câmpeni', 'en' => 'Campeni'], 'lat' => 46.3667, 'lng' => 23.0500, 'population' => 7393],
                ],
            ],

            // BUZĂU
            [
                'name' => ['ro' => 'Buzău', 'en' => 'Buzau'],
                'code' => 'BZ',
                'cities' => [
                    ['name' => ['ro' => 'Buzău', 'en' => 'Buzau'], 'lat' => 45.1500, 'lng' => 26.8333, 'population' => 115494, 'capital' => true],
                    ['name' => ['ro' => 'Râmnicu Sărat', 'en' => 'Ramnicu Sarat'], 'lat' => 45.3833, 'lng' => 27.0500, 'population' => 33911],
                    ['name' => ['ro' => 'Nehoiu', 'en' => 'Nehoiu'], 'lat' => 45.4333, 'lng' => 26.3000, 'population' => 10578],
                    ['name' => ['ro' => 'Pătârlagele', 'en' => 'Patarlagele'], 'lat' => 45.3167, 'lng' => 26.3667, 'population' => 7101],
                    ['name' => ['ro' => 'Pogoanele', 'en' => 'Pogoanele'], 'lat' => 45.0167, 'lng' => 27.0667, 'population' => 5990],
                ],
            ],

            // BOTOȘANI
            [
                'name' => ['ro' => 'Botoșani', 'en' => 'Botosani'],
                'code' => 'BT',
                'cities' => [
                    ['name' => ['ro' => 'Botoșani', 'en' => 'Botosani'], 'lat' => 47.7500, 'lng' => 26.6667, 'population' => 106847, 'capital' => true],
                    ['name' => ['ro' => 'Dorohoi', 'en' => 'Dorohoi'], 'lat' => 47.9500, 'lng' => 26.4000, 'population' => 27089],
                    ['name' => ['ro' => 'Darabani', 'en' => 'Darabani'], 'lat' => 48.2000, 'lng' => 26.6000, 'population' => 10193],
                    ['name' => ['ro' => 'Săveni', 'en' => 'Saveni'], 'lat' => 47.9667, 'lng' => 26.8500, 'population' => 7448],
                ],
            ],

            // BISTRIȚA-NĂSĂUD
            [
                'name' => ['ro' => 'Bistrița-Năsăud', 'en' => 'Bistrita-Nasaud'],
                'code' => 'BN',
                'cities' => [
                    ['name' => ['ro' => 'Bistrița', 'en' => 'Bistrita'], 'lat' => 47.1333, 'lng' => 24.5000, 'population' => 75076, 'capital' => true],
                    ['name' => ['ro' => 'Năsăud', 'en' => 'Nasaud'], 'lat' => 47.2833, 'lng' => 24.4000, 'population' => 10164],
                    ['name' => ['ro' => 'Beclean', 'en' => 'Beclean'], 'lat' => 47.1833, 'lng' => 24.1833, 'population' => 11209],
                    ['name' => ['ro' => 'Sângeorz-Băi', 'en' => 'Sangeorz-Bai'], 'lat' => 47.3667, 'lng' => 24.6833, 'population' => 9627, 'featured' => true],
                ],
            ],

            // BRĂILA
            [
                'name' => ['ro' => 'Brăila', 'en' => 'Braila'],
                'code' => 'BR',
                'cities' => [
                    ['name' => ['ro' => 'Brăila', 'en' => 'Braila'], 'lat' => 45.2692, 'lng' => 27.9575, 'population' => 180302, 'capital' => true],
                    ['name' => ['ro' => 'Ianca', 'en' => 'Ianca'], 'lat' => 45.1333, 'lng' => 27.4667, 'population' => 10948],
                    ['name' => ['ro' => 'Însurăței', 'en' => 'Insuratei'], 'lat' => 44.9167, 'lng' => 27.6000, 'population' => 6855],
                    ['name' => ['ro' => 'Făurei', 'en' => 'Faurei'], 'lat' => 45.0833, 'lng' => 27.2667, 'population' => 3764],
                ],
            ],

            // CARAȘ-SEVERIN
            [
                'name' => ['ro' => 'Caraș-Severin', 'en' => 'Caras-Severin'],
                'code' => 'CS',
                'cities' => [
                    ['name' => ['ro' => 'Reșița', 'en' => 'Resita'], 'lat' => 45.3000, 'lng' => 21.8833, 'population' => 73282, 'capital' => true],
                    ['name' => ['ro' => 'Caransebeș', 'en' => 'Caransebes'], 'lat' => 45.4167, 'lng' => 22.2167, 'population' => 23775],
                    ['name' => ['ro' => 'Bocșa', 'en' => 'Bocsa'], 'lat' => 45.3833, 'lng' => 21.7000, 'population' => 15893],
                    ['name' => ['ro' => 'Oravița', 'en' => 'Oravita'], 'lat' => 45.0333, 'lng' => 21.6833, 'population' => 12102],
                    ['name' => ['ro' => 'Moldova Nouă', 'en' => 'Moldova Noua'], 'lat' => 44.7333, 'lng' => 21.6667, 'population' => 12096],
                    ['name' => ['ro' => 'Anina', 'en' => 'Anina'], 'lat' => 45.0833, 'lng' => 21.8500, 'population' => 7807],
                    ['name' => ['ro' => 'Băile Herculane', 'en' => 'Baile Herculane'], 'lat' => 44.8833, 'lng' => 22.4167, 'population' => 4979, 'featured' => true],
                ],
            ],

            // CĂLĂRAȘI
            [
                'name' => ['ro' => 'Călărași', 'en' => 'Calarasi'],
                'code' => 'CL',
                'cities' => [
                    ['name' => ['ro' => 'Călărași', 'en' => 'Calarasi'], 'lat' => 44.2000, 'lng' => 27.3333, 'population' => 65181, 'capital' => true],
                    ['name' => ['ro' => 'Oltenița', 'en' => 'Oltenita'], 'lat' => 44.0833, 'lng' => 26.6333, 'population' => 24822],
                    ['name' => ['ro' => 'Budești', 'en' => 'Budesti'], 'lat' => 44.0667, 'lng' => 26.4000, 'population' => 9815],
                    ['name' => ['ro' => 'Fundulea', 'en' => 'Fundulea'], 'lat' => 44.4667, 'lng' => 26.5167, 'population' => 6193],
                    ['name' => ['ro' => 'Lehliu Gară', 'en' => 'Lehliu Gara'], 'lat' => 44.4333, 'lng' => 26.8500, 'population' => 5842],
                ],
            ],

            // COVASNA
            [
                'name' => ['ro' => 'Covasna', 'en' => 'Covasna'],
                'code' => 'CV',
                'cities' => [
                    ['name' => ['ro' => 'Sfântu Gheorghe', 'en' => 'Sfantu Gheorghe'], 'lat' => 45.8667, 'lng' => 25.7833, 'population' => 56006, 'capital' => true],
                    ['name' => ['ro' => 'Târgu Secuiesc', 'en' => 'Targu Secuiesc'], 'lat' => 46.0000, 'lng' => 26.1333, 'population' => 18491],
                    ['name' => ['ro' => 'Covasna', 'en' => 'Covasna'], 'lat' => 45.8500, 'lng' => 26.1833, 'population' => 10464, 'featured' => true],
                    ['name' => ['ro' => 'Baraolt', 'en' => 'Baraolt'], 'lat' => 46.0667, 'lng' => 25.6000, 'population' => 8940],
                    ['name' => ['ro' => 'Întorsura Buzăului', 'en' => 'Intorsura Buzaului'], 'lat' => 45.6667, 'lng' => 26.0333, 'population' => 8314],
                ],
            ],

            // DÂMBOVIȚA
            [
                'name' => ['ro' => 'Dâmbovița', 'en' => 'Dambovita'],
                'code' => 'DB',
                'cities' => [
                    ['name' => ['ro' => 'Târgoviște', 'en' => 'Targoviste'], 'lat' => 44.9333, 'lng' => 25.4500, 'population' => 79610, 'capital' => true],
                    ['name' => ['ro' => 'Moreni', 'en' => 'Moreni'], 'lat' => 44.9833, 'lng' => 25.6500, 'population' => 18214],
                    ['name' => ['ro' => 'Pucioasa', 'en' => 'Pucioasa'], 'lat' => 45.0667, 'lng' => 25.4333, 'population' => 14294],
                    ['name' => ['ro' => 'Găești', 'en' => 'Gaesti'], 'lat' => 44.7167, 'lng' => 25.3167, 'population' => 13785],
                    ['name' => ['ro' => 'Titu', 'en' => 'Titu'], 'lat' => 44.6500, 'lng' => 25.5667, 'population' => 9315],
                    ['name' => ['ro' => 'Fieni', 'en' => 'Fieni'], 'lat' => 45.1333, 'lng' => 25.4167, 'population' => 6951],
                ],
            ],

            // GORJ
            [
                'name' => ['ro' => 'Gorj', 'en' => 'Gorj'],
                'code' => 'GJ',
                'cities' => [
                    ['name' => ['ro' => 'Târgu Jiu', 'en' => 'Targu Jiu'], 'lat' => 45.0333, 'lng' => 23.2833, 'population' => 82504, 'capital' => true],
                    ['name' => ['ro' => 'Motru', 'en' => 'Motru'], 'lat' => 44.8000, 'lng' => 22.9667, 'population' => 20875],
                    ['name' => ['ro' => 'Rovinari', 'en' => 'Rovinari'], 'lat' => 44.9167, 'lng' => 23.1667, 'population' => 11816],
                    ['name' => ['ro' => 'Bumbești-Jiu', 'en' => 'Bumbesti-Jiu'], 'lat' => 45.1833, 'lng' => 23.3833, 'population' => 9397],
                    ['name' => ['ro' => 'Târgu Cărbunești', 'en' => 'Targu Carbunesti'], 'lat' => 44.9667, 'lng' => 23.5000, 'population' => 8078],
                    ['name' => ['ro' => 'Novaci', 'en' => 'Novaci'], 'lat' => 45.1833, 'lng' => 23.6667, 'population' => 5364],
                    ['name' => ['ro' => 'Rânca', 'en' => 'Ranca'], 'lat' => 45.2833, 'lng' => 23.6833, 'featured' => true],
                ],
            ],

            // GIURGIU
            [
                'name' => ['ro' => 'Giurgiu', 'en' => 'Giurgiu'],
                'code' => 'GR',
                'cities' => [
                    ['name' => ['ro' => 'Giurgiu', 'en' => 'Giurgiu'], 'lat' => 43.9000, 'lng' => 25.9667, 'population' => 61353, 'capital' => true],
                    ['name' => ['ro' => 'Bolintin-Vale', 'en' => 'Bolintin-Vale'], 'lat' => 44.4333, 'lng' => 25.7500, 'population' => 11753],
                    ['name' => ['ro' => 'Mihăilești', 'en' => 'Mihailesti'], 'lat' => 44.3167, 'lng' => 25.9333, 'population' => 5695],
                ],
            ],

            // HARGHITA
            [
                'name' => ['ro' => 'Harghita', 'en' => 'Harghita'],
                'code' => 'HR',
                'cities' => [
                    ['name' => ['ro' => 'Miercurea Ciuc', 'en' => 'Miercurea Ciuc'], 'lat' => 46.3500, 'lng' => 25.8000, 'population' => 37980, 'capital' => true],
                    ['name' => ['ro' => 'Odorheiu Secuiesc', 'en' => 'Odorheiu Secuiesc'], 'lat' => 46.3000, 'lng' => 25.3000, 'population' => 34257],
                    ['name' => ['ro' => 'Gheorgheni', 'en' => 'Gheorgheni'], 'lat' => 46.7167, 'lng' => 25.5833, 'population' => 17634],
                    ['name' => ['ro' => 'Toplița', 'en' => 'Toplita'], 'lat' => 46.9333, 'lng' => 25.3500, 'population' => 13423],
                    ['name' => ['ro' => 'Cristuru Secuiesc', 'en' => 'Cristuru Secuiesc'], 'lat' => 46.2833, 'lng' => 25.0333, 'population' => 9549],
                    ['name' => ['ro' => 'Bălan', 'en' => 'Balan'], 'lat' => 46.6333, 'lng' => 25.8000, 'population' => 6497],
                    ['name' => ['ro' => 'Borsec', 'en' => 'Borsec'], 'lat' => 46.9500, 'lng' => 25.5500, 'population' => 2514, 'featured' => true],
                    ['name' => ['ro' => 'Praid', 'en' => 'Praid'], 'lat' => 46.5333, 'lng' => 25.1333, 'featured' => true],
                    ['name' => ['ro' => 'Lacul Roșu', 'en' => 'Lacu Rosu'], 'lat' => 46.7833, 'lng' => 25.8000, 'featured' => true],
                ],
            ],

            // IALOMIȚA
            [
                'name' => ['ro' => 'Ialomița', 'en' => 'Ialomita'],
                'code' => 'IL',
                'cities' => [
                    ['name' => ['ro' => 'Slobozia', 'en' => 'Slobozia'], 'lat' => 44.5667, 'lng' => 27.3667, 'population' => 52693, 'capital' => true],
                    ['name' => ['ro' => 'Fetești', 'en' => 'Fetesti'], 'lat' => 44.3833, 'lng' => 27.8333, 'population' => 30223],
                    ['name' => ['ro' => 'Urziceni', 'en' => 'Urziceni'], 'lat' => 44.7167, 'lng' => 26.6333, 'population' => 17404],
                    ['name' => ['ro' => 'Țăndărei', 'en' => 'Tandarei'], 'lat' => 44.6500, 'lng' => 27.6500, 'population' => 12983],
                    ['name' => ['ro' => 'Amara', 'en' => 'Amara'], 'lat' => 44.6167, 'lng' => 27.3333, 'population' => 7452, 'featured' => true],
                ],
            ],

            // MARAMUREȘ
            [
                'name' => ['ro' => 'Maramureș', 'en' => 'Maramures'],
                'code' => 'MM',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Baia Mare', 'en' => 'Baia Mare'], 'lat' => 47.6567, 'lng' => 23.5850, 'population' => 123738, 'capital' => true],
                    ['name' => ['ro' => 'Sighetu Marmației', 'en' => 'Sighetu Marmatiei'], 'lat' => 47.9333, 'lng' => 23.8833, 'population' => 37640, 'featured' => true],
                    ['name' => ['ro' => 'Borșa', 'en' => 'Borsa'], 'lat' => 47.6500, 'lng' => 24.6667, 'population' => 24852, 'featured' => true],
                    ['name' => ['ro' => 'Baia Sprie', 'en' => 'Baia Sprie'], 'lat' => 47.6667, 'lng' => 23.6833, 'population' => 14600],
                    ['name' => ['ro' => 'Cavnic', 'en' => 'Cavnic'], 'lat' => 47.6667, 'lng' => 23.8833, 'population' => 4895],
                    ['name' => ['ro' => 'Vișeu de Sus', 'en' => 'Viseu de Sus'], 'lat' => 47.7167, 'lng' => 24.4167, 'population' => 15370],
                    ['name' => ['ro' => 'Seini', 'en' => 'Seini'], 'lat' => 47.7500, 'lng' => 23.2833, 'population' => 9316],
                    ['name' => ['ro' => 'Tăuții-Măgherăuș', 'en' => 'Tautii-Magheraus'], 'lat' => 47.7167, 'lng' => 23.4500, 'population' => 7715],
                    ['name' => ['ro' => 'Săpânța', 'en' => 'Sapanta'], 'lat' => 47.9667, 'lng' => 23.7000, 'featured' => true],
                ],
            ],

            // MEHEDINȚI
            [
                'name' => ['ro' => 'Mehedinți', 'en' => 'Mehedinti'],
                'code' => 'MH',
                'cities' => [
                    ['name' => ['ro' => 'Drobeta-Turnu Severin', 'en' => 'Drobeta-Turnu Severin'], 'lat' => 44.6333, 'lng' => 22.6667, 'population' => 92617, 'capital' => true],
                    ['name' => ['ro' => 'Orșova', 'en' => 'Orsova'], 'lat' => 44.7167, 'lng' => 22.4000, 'population' => 10441, 'featured' => true],
                    ['name' => ['ro' => 'Strehaia', 'en' => 'Strehaia'], 'lat' => 44.6167, 'lng' => 23.2000, 'population' => 10328],
                    ['name' => ['ro' => 'Vânju Mare', 'en' => 'Vanju Mare'], 'lat' => 44.4333, 'lng' => 22.8833, 'population' => 5227],
                    ['name' => ['ro' => 'Baia de Aramă', 'en' => 'Baia de Arama'], 'lat' => 44.9333, 'lng' => 22.8167, 'population' => 4926],
                ],
            ],

            // NEAMȚ
            [
                'name' => ['ro' => 'Neamț', 'en' => 'Neamt'],
                'code' => 'NT',
                'cities' => [
                    ['name' => ['ro' => 'Piatra Neamț', 'en' => 'Piatra Neamt'], 'lat' => 46.9333, 'lng' => 26.3667, 'population' => 85055, 'capital' => true],
                    ['name' => ['ro' => 'Roman', 'en' => 'Roman'], 'lat' => 46.9167, 'lng' => 26.9167, 'population' => 50713],
                    ['name' => ['ro' => 'Târgu Neamț', 'en' => 'Targu Neamt'], 'lat' => 47.2000, 'lng' => 26.3667, 'population' => 18695],
                    ['name' => ['ro' => 'Bicaz', 'en' => 'Bicaz'], 'lat' => 46.8000, 'lng' => 26.0667, 'population' => 8047, 'featured' => true],
                    ['name' => ['ro' => 'Roznov', 'en' => 'Roznov'], 'lat' => 46.8333, 'lng' => 26.5000, 'population' => 7614],
                    ['name' => ['ro' => 'Durău', 'en' => 'Durau'], 'lat' => 47.0500, 'lng' => 25.9667, 'featured' => true],
                ],
            ],

            // OLT
            [
                'name' => ['ro' => 'Olt', 'en' => 'Olt'],
                'code' => 'OT',
                'cities' => [
                    ['name' => ['ro' => 'Slatina', 'en' => 'Slatina'], 'lat' => 44.4333, 'lng' => 24.3667, 'population' => 70293, 'capital' => true],
                    ['name' => ['ro' => 'Caracal', 'en' => 'Caracal'], 'lat' => 44.1167, 'lng' => 24.3500, 'population' => 30954],
                    ['name' => ['ro' => 'Corabia', 'en' => 'Corabia'], 'lat' => 43.7667, 'lng' => 24.5000, 'population' => 16439],
                    ['name' => ['ro' => 'Balș', 'en' => 'Bals'], 'lat' => 44.3500, 'lng' => 24.0833, 'population' => 18840],
                    ['name' => ['ro' => 'Scornicești', 'en' => 'Scornicesti'], 'lat' => 44.5667, 'lng' => 24.5500, 'population' => 11120],
                    ['name' => ['ro' => 'Drăgănești-Olt', 'en' => 'Draganesti-Olt'], 'lat' => 44.1667, 'lng' => 24.5000, 'population' => 10944],
                ],
            ],

            // SATU MARE
            [
                'name' => ['ro' => 'Satu Mare', 'en' => 'Satu Mare'],
                'code' => 'SM',
                'cities' => [
                    ['name' => ['ro' => 'Satu Mare', 'en' => 'Satu Mare'], 'lat' => 47.7833, 'lng' => 22.8833, 'population' => 102411, 'capital' => true],
                    ['name' => ['ro' => 'Carei', 'en' => 'Carei'], 'lat' => 47.6833, 'lng' => 22.4667, 'population' => 21112],
                    ['name' => ['ro' => 'Negrești-Oaș', 'en' => 'Negresti-Oas'], 'lat' => 47.8667, 'lng' => 23.4333, 'population' => 15407],
                    ['name' => ['ro' => 'Tășnad', 'en' => 'Tasnad'], 'lat' => 47.4667, 'lng' => 22.5833, 'population' => 8628],
                    ['name' => ['ro' => 'Livada', 'en' => 'Livada'], 'lat' => 47.8500, 'lng' => 23.1167, 'population' => 7230],
                    ['name' => ['ro' => 'Ardud', 'en' => 'Ardud'], 'lat' => 47.6333, 'lng' => 22.8833, 'population' => 6684],
                ],
            ],

            // SĂLAJ
            [
                'name' => ['ro' => 'Sălaj', 'en' => 'Salaj'],
                'code' => 'SJ',
                'cities' => [
                    ['name' => ['ro' => 'Zalău', 'en' => 'Zalau'], 'lat' => 47.1833, 'lng' => 23.0500, 'population' => 56202, 'capital' => true],
                    ['name' => ['ro' => 'Șimleu Silvaniei', 'en' => 'Simleu Silvaniei'], 'lat' => 47.2333, 'lng' => 22.8000, 'population' => 14401],
                    ['name' => ['ro' => 'Jibou', 'en' => 'Jibou'], 'lat' => 47.2667, 'lng' => 23.2500, 'population' => 10407],
                    ['name' => ['ro' => 'Cehu Silvaniei', 'en' => 'Cehu Silvaniei'], 'lat' => 47.4167, 'lng' => 23.1833, 'population' => 6951],
                ],
            ],

            // TELEORMAN
            [
                'name' => ['ro' => 'Teleorman', 'en' => 'Teleorman'],
                'code' => 'TR',
                'cities' => [
                    ['name' => ['ro' => 'Alexandria', 'en' => 'Alexandria'], 'lat' => 43.9667, 'lng' => 25.3333, 'population' => 45434, 'capital' => true],
                    ['name' => ['ro' => 'Roșiori de Vede', 'en' => 'Rosiori de Vede'], 'lat' => 44.1000, 'lng' => 24.9833, 'population' => 27416],
                    ['name' => ['ro' => 'Turnu Măgurele', 'en' => 'Turnu Magurele'], 'lat' => 43.7500, 'lng' => 24.8833, 'population' => 26000],
                    ['name' => ['ro' => 'Zimnicea', 'en' => 'Zimnicea'], 'lat' => 43.6500, 'lng' => 25.3667, 'population' => 13800],
                    ['name' => ['ro' => 'Videle', 'en' => 'Videle'], 'lat' => 44.2833, 'lng' => 25.5333, 'population' => 11300],
                ],
            ],

            // TULCEA
            [
                'name' => ['ro' => 'Tulcea', 'en' => 'Tulcea'],
                'code' => 'TL',
                'featured' => true,
                'cities' => [
                    ['name' => ['ro' => 'Tulcea', 'en' => 'Tulcea'], 'lat' => 45.1833, 'lng' => 28.8000, 'population' => 73707, 'capital' => true],
                    ['name' => ['ro' => 'Măcin', 'en' => 'Macin'], 'lat' => 45.2500, 'lng' => 28.1333, 'population' => 10324],
                    ['name' => ['ro' => 'Babadag', 'en' => 'Babadag'], 'lat' => 44.8833, 'lng' => 28.7167, 'population' => 9813],
                    ['name' => ['ro' => 'Isaccea', 'en' => 'Isaccea'], 'lat' => 45.2667, 'lng' => 28.4667, 'population' => 5019],
                    ['name' => ['ro' => 'Sulina', 'en' => 'Sulina'], 'lat' => 45.1500, 'lng' => 29.6667, 'population' => 3663, 'featured' => true],
                    ['name' => ['ro' => 'Sfântu Gheorghe', 'en' => 'Sfantu Gheorghe'], 'lat' => 44.8833, 'lng' => 29.6000, 'featured' => true],
                    ['name' => ['ro' => 'Murighiol', 'en' => 'Murighiol'], 'lat' => 45.0333, 'lng' => 29.1667, 'featured' => true],
                ],
            ],

            // VÂLCEA
            [
                'name' => ['ro' => 'Vâlcea', 'en' => 'Valcea'],
                'code' => 'VL',
                'cities' => [
                    ['name' => ['ro' => 'Râmnicu Vâlcea', 'en' => 'Ramnicu Valcea'], 'lat' => 45.1000, 'lng' => 24.3667, 'population' => 98776, 'capital' => true],
                    ['name' => ['ro' => 'Drăgășani', 'en' => 'Dragasani'], 'lat' => 44.6667, 'lng' => 24.2500, 'population' => 19202],
                    ['name' => ['ro' => 'Băile Govora', 'en' => 'Baile Govora'], 'lat' => 45.0833, 'lng' => 24.1833, 'population' => 2656, 'featured' => true],
                    ['name' => ['ro' => 'Băile Olănești', 'en' => 'Baile Olanesti'], 'lat' => 45.2000, 'lng' => 24.2500, 'population' => 4494, 'featured' => true],
                    ['name' => ['ro' => 'Călimănești', 'en' => 'Calimanesti'], 'lat' => 45.2333, 'lng' => 24.3167, 'population' => 8191, 'featured' => true],
                    ['name' => ['ro' => 'Horezu', 'en' => 'Horezu'], 'lat' => 45.1500, 'lng' => 23.9833, 'population' => 6287, 'featured' => true],
                    ['name' => ['ro' => 'Brezoi', 'en' => 'Brezoi'], 'lat' => 45.3500, 'lng' => 24.2500, 'population' => 5978],
                    ['name' => ['ro' => 'Berbești', 'en' => 'Berbesti'], 'lat' => 45.0500, 'lng' => 23.8333, 'population' => 4822],
                    ['name' => ['ro' => 'Voineasa', 'en' => 'Voineasa'], 'lat' => 45.4167, 'lng' => 23.9667, 'featured' => true],
                ],
            ],

            // VASLUI
            [
                'name' => ['ro' => 'Vaslui', 'en' => 'Vaslui'],
                'code' => 'VS',
                'cities' => [
                    ['name' => ['ro' => 'Vaslui', 'en' => 'Vaslui'], 'lat' => 46.6333, 'lng' => 27.7333, 'population' => 55407, 'capital' => true],
                    ['name' => ['ro' => 'Bârlad', 'en' => 'Barlad'], 'lat' => 46.2333, 'lng' => 27.6667, 'population' => 55837],
                    ['name' => ['ro' => 'Huși', 'en' => 'Husi'], 'lat' => 46.6833, 'lng' => 28.0500, 'population' => 26266],
                    ['name' => ['ro' => 'Negrești', 'en' => 'Negresti'], 'lat' => 46.8333, 'lng' => 27.4667, 'population' => 9108],
                    ['name' => ['ro' => 'Murgeni', 'en' => 'Murgeni'], 'lat' => 46.2167, 'lng' => 28.0167, 'population' => 6652],
                ],
            ],

            // VRANCEA
            [
                'name' => ['ro' => 'Vrancea', 'en' => 'Vrancea'],
                'code' => 'VN',
                'cities' => [
                    ['name' => ['ro' => 'Focșani', 'en' => 'Focsani'], 'lat' => 45.7000, 'lng' => 27.1833, 'population' => 79315, 'capital' => true],
                    ['name' => ['ro' => 'Adjud', 'en' => 'Adjud'], 'lat' => 46.1000, 'lng' => 27.1833, 'population' => 18126],
                    ['name' => ['ro' => 'Mărășești', 'en' => 'Marasesti'], 'lat' => 45.8833, 'lng' => 27.2333, 'population' => 10768],
                    ['name' => ['ro' => 'Panciu', 'en' => 'Panciu'], 'lat' => 45.9167, 'lng' => 27.1000, 'population' => 8091],
                    ['name' => ['ro' => 'Odobești', 'en' => 'Odobesti'], 'lat' => 45.7667, 'lng' => 27.0500, 'population' => 7766],
                ],
            ],
        ];
    }
}
