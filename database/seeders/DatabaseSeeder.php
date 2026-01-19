<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            //EventsSeeder::class,
            //EventTaxonomiesSeeder::class,
            //ArtistTypesAndGenresSeeder::class,
            //EventTypesAndGenresSeeder::class,
            //ArtistDeepDemoSeeder::class,
            VenueSeeder::class,
            DocumentationSeeder::class,
            ContractTemplateSeeder::class,
        ]);
    }
}
