<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Venue;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        // Tenants demo (doar câmpurile care există în schema ta)
        $tenantA = Tenant::firstOrCreate(
            ['slug' => 'alpha-events'],
            [
                'name'    => 'Alpha Events',
                'domain'  => 'alpha.local',   // domain e obligatoriu + unique în schema ta
                'status'  => 'active',
                'plan'    => 'A',
                'settings'=> [],
            ]
        );

        $tenantB = Tenant::firstOrCreate(
            ['slug' => 'beta-live'],
            [
                'name'    => 'Beta Live',
                'domain'  => 'beta.local',
                'status'  => 'active',
                'plan'    => 'B',
                'settings'=> [],
            ]
        );

        // Venues demo (unul fără tenant, două cu tenant)
        $data = [
            [
                'name' => 'Arena Centrală',
                'tenant_id' => $tenantA->id,
                'address' => 'Bd. Unirii 10',
                'city' => 'București',
                'state' => 'București',
                'country' => 'RO',
                'lat' => 44.4268,
                'lng' => 26.1025,
                'capacity_total' => 12000,
                'capacity_standing' => 8000,
                'capacity_seated' => 4000,
                'phone' => '+40 21 123 4567',
                'email' => null,
                'website_url' => 'https://arena-central.example',
                'facebook_url' => null,
                'instagram_url' => null,
                'tiktok_url' => null,
                'image_url' => null,
                'established_at' => '2010-06-01',
                'description' => '<p>Locație premium pentru concerte indoor/outdoor.</p>',
            ],
            [
                'name' => 'Parcul Live Beta',
                'tenant_id' => $tenantB->id,
                'address' => 'Str. Libertății 25',
                'city' => 'Cluj-Napoca',
                'state' => 'Cluj',
                'country' => 'RO',
                'lat' => 46.7712,
                'lng' => 23.6236,
                'capacity_total' => 20000,
                'capacity_standing' => 18000,
                'capacity_seated' => 2000,
                'phone' => '+40 264 765 432',
                'email' => null,
                'website_url' => 'https://parc-beta.example',
                'facebook_url' => null,
                'instagram_url' => null,
                'tiktok_url' => null,
                'image_url' => null,
                'established_at' => '2015-05-10',
                'description' => '<p>Parc urban folosit pentru festivaluri mari.</p>',
            ],
            [
                'name' => 'Sala Municipală',
                'tenant_id' => null, // venue fără owner (cum ai specificat)
                'address' => 'Calea Eroilor 1',
                'city' => 'Brașov',
                'state' => 'Brașov',
                'country' => 'RO',
                'lat' => 45.6579,
                'lng' => 25.6012,
                'capacity_total' => 5000,
                'capacity_standing' => 3500,
                'capacity_seated' => 1500,
                'phone' => '+40 268 123 000',
                'email' => 'rezervari@salamunicipala.ro',
                'website_url' => 'https://sala-municipala.example',
                'facebook_url' => null,
                'instagram_url' => null,
                'tiktok_url' => null,
                'image_url' => null,
                'established_at' => '2002-09-01',
                'description' => '<p>Sală multifuncțională de evenimente.</p>',
            ],
        ];

        foreach ($data as $row) {
            Venue::firstOrCreate(
                ['name' => $row['name'], 'city' => $row['city']],
                $row
            );
        }
    }
}
