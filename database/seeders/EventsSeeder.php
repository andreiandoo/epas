<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventPilotDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first() ?? Tenant::create([
            'name'     => 'Teatrul Odeon',
            'domain'   => 'odeon.local',
            'status'   => 'active',
            'settings' => [],
        ]);

        $artist = Artist::first() ?? Artist::create([
            'name'    => 'Cabral Ibacka',
            'slug'    => Str::slug('Cabral Ibacka'),
            'country' => 'RO',
            'genres'  => ['talk', 'host'],
            'socials' => ['instagram' => 'https://instagram.com/...'],
            'status'  => 'active',
        ]);

        $event = Event::first() ?? Event::create([
            'tenant_id'    => $tenant->id,
            'title'        => 'Gala Odeon',
            'slug'         => Str::slug('Gala Odeon'),
            'venue'        => 'Sala Mare',
            'city'         => 'București',
            'country'      => 'RO',
            'starts_at'    => now()->addDays(10)->setTime(19, 0),
            'ends_at'      => now()->addDays(10)->setTime(21, 0),
            'is_recurring' => false,
            'status'       => 'published',
            'poster_url'   => null,
            'locale'       => 'ro',
            'seo'          => ['title' => 'Gala Odeon', 'desc' => 'Un eveniment special.'],
            'meta'         => [],
        ]);

        if (! $event->artists()->where('artists.id', $artist->id)->exists()) {
            $event->artists()->attach($artist->id);
        }
    }
}
