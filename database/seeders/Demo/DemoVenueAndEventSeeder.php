<?php

namespace Database\Seeders\Demo;

use App\Models\Artist;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\TicketType;
use App\Models\Venue;
use Illuminate\Support\Str;

class DemoVenueAndEventSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;

        // ── Venue ──
        $venue = Venue::firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'demo-arena-festival-park'],
            [
                'name' => ['ro' => 'Arena Festival Park', 'en' => 'Arena Festival Park'],
                'description' => ['ro' => 'Locatie festival in aer liber', 'en' => 'Outdoor festival venue'],
                'address' => 'Str. Parcului nr. 1',
                'city' => 'Cluj-Napoca',
                'state' => 'Cluj',
                'country' => 'RO',
                'capacity' => 15000,
                'capacity_total' => 15000,
                'capacity_standing' => 12000,
                'capacity_seated' => 3000,
                'lat' => 46.7712,
                'lng' => 23.6236,
                'website_url' => 'https://arena-festival-park.ro',
                'phone' => '+40 264 111 222',
                'email' => 'contact@arena-festival-park.ro',
                'is_partner' => true,
                'is_featured' => true,
                'facilities' => ['main_stage', 'parking', 'toilets', 'food_court', 'bar', 'standing_area', 'pro_audio', 'led_screens'],
            ]
        );
        $this->parent->refs['venue'] = $venue;

        // ── Event ──
        $event = Event::firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'demo-alpha-fest-2026'],
            [
                'title' => ['ro' => 'Alpha Fest 2026', 'en' => 'Alpha Fest 2026'],
                'subtitle' => ['ro' => 'Editia a 3-a', 'en' => '3rd Edition'],
                'short_description' => ['ro' => 'Cel mai mare festival din Transilvania', 'en' => 'The biggest festival in Transylvania'],
                'description' => ['ro' => '<p>Alpha Fest revine in 2026 cu o editie memorabila! 5 zile de muzica, distractie si experiente unice in inima Clujului.</p>', 'en' => '<p>Alpha Fest returns in 2026 with a memorable edition! 5 days of music, fun and unique experiences in the heart of Cluj.</p>'],
                'venue_id' => $venue->id,
                'duration_mode' => 'range',
                'range_start_date' => '2026-07-15',
                'range_end_date' => '2026-07-19',
                'range_start_time' => '14:00',
                'range_end_time' => '05:00',
                'commission_mode' => 'included',
                'commission_rate' => 5.00,
                'is_published' => true,
                'enable_ticket_groups' => true,
                'enable_ticket_perks' => true,
                'currency' => 'RON',
            ]
        );
        $this->parent->refs['event'] = $event;

        // ── Artists ──
        $artistsData = [
            ['slug' => 'demo-subcarpati', 'name' => 'Subcarpati', 'city' => 'Bucuresti', 'country' => 'Romania', 'headliner' => true, 'co' => false],
            ['slug' => 'demo-the-motans', 'name' => 'The Motans', 'city' => 'Chisinau', 'country' => 'Moldova', 'headliner' => true, 'co' => false],
            ['slug' => 'demo-infected-rain', 'name' => 'Infected Rain', 'city' => 'Chisinau', 'country' => 'Moldova', 'headliner' => false, 'co' => true],
            ['slug' => 'demo-dj-project', 'name' => 'DJ Project', 'city' => 'Bucuresti', 'country' => 'Romania', 'headliner' => false, 'co' => false],
            ['slug' => 'demo-oliver-tree', 'name' => 'Oliver Tree', 'city' => 'Santa Cruz', 'country' => 'USA', 'headliner' => false, 'co' => false],
            ['slug' => 'demo-moderat', 'name' => 'Moderat', 'city' => 'Berlin', 'country' => 'Germany', 'headliner' => false, 'co' => false],
        ];

        $artists = [];
        foreach ($artistsData as $i => $ad) {
            $artist = Artist::firstOrCreate(
                ['slug' => $ad['slug']],
                [
                    'name' => $ad['name'],
                    'letter' => strtoupper(substr($ad['name'], 0, 1)),
                    'city' => $ad['city'],
                    'country' => $ad['country'],
                    'bio_html' => ['ro' => "<p>{$ad['name']} - artist demo.</p>", 'en' => "<p>{$ad['name']} - demo artist.</p>"],
                    'is_active' => true,
                ]
            );
            $artists[] = $artist;

            $event->artists()->syncWithoutDetaching([
                $artist->id => [
                    'sort_order' => $i + 1,
                    'is_headliner' => $ad['headliner'],
                    'is_co_headliner' => $ad['co'],
                ],
            ]);
        }
        $this->parent->refs['artists'] = $artists;

        // ── Taxonomies ──
        $festivalType = EventType::where('name->en', 'Festival')->orWhere('name->ro', 'Festival')->first();
        $concertType = EventType::where('name->en', 'Concert')->orWhere('name->ro', 'Concert')->first();

        if ($festivalType) $event->eventTypes()->syncWithoutDetaching([$festivalType->id]);
        if ($concertType) $event->eventTypes()->syncWithoutDetaching([$concertType->id]);

        $rockGenre = EventGenre::where('name->en', 'Rock')->orWhere('name->ro', 'Rock')->first();
        $electronicGenre = EventGenre::where('name->en', 'Electronic')->orWhere('name->ro', 'Electronic')->first();
        $popGenre = EventGenre::where('name->en', 'Pop')->orWhere('name->ro', 'Pop')->first();

        if ($rockGenre) $event->eventGenres()->syncWithoutDetaching([$rockGenre->id]);
        if ($electronicGenre) $event->eventGenres()->syncWithoutDetaching([$electronicGenre->id]);
        if ($popGenre) $event->eventGenres()->syncWithoutDetaching([$popGenre->id]);

        // ── Ticket Types ──
        // Note: TicketType uses virtual fillable fields with mutators:
        // price_max → price_cents (value in RON, mutator multiplies ×100)
        // capacity → quota_total
        // is_active → status ('active'/'hidden')
        $ticketTypesData = [
            ['name' => 'General Access 4-Day', 'group' => 'Acces', 'price_ron' => 200, 'quota' => 5000, 'sold' => 1847, 'active' => true, 'perks' => ['Acces toate zilele', 'Acces toate scenele']],
            ['name' => 'General Access 1-Day', 'group' => 'Acces', 'price_ron' => 80, 'quota' => 2000, 'sold' => 523, 'active' => true, 'perks' => ['Acces o zi la alegere']],
            ['name' => 'Early Bird 4-Day', 'group' => 'Acces', 'price_ron' => 150, 'quota' => 500, 'sold' => 500, 'active' => false, 'perks' => ['Acces toate zilele', 'Acces toate scenele', 'Pret redus']],
            ['name' => 'VIP 4-Day', 'group' => 'VIP', 'price_ron' => 500, 'quota' => 500, 'sold' => 187, 'active' => true, 'perks' => ['Acces VIP Lounge', 'Zona dedicata', 'Toalete VIP', 'Drink de bun venit']],
            ['name' => 'VIP 1-Day', 'group' => 'VIP', 'price_ron' => 200, 'quota' => 300, 'sold' => 45, 'active' => true, 'perks' => ['Acces VIP Lounge', 'Zona dedicata']],
            ['name' => 'Camping Standard', 'group' => 'Camping', 'price_ron' => 100, 'quota' => 1000, 'sold' => 412, 'active' => true, 'perks' => ['Loc cort zona generala', 'Acces dusuri comune']],
            ['name' => 'Camping Premium', 'group' => 'Camping', 'price_ron' => 200, 'quota' => 200, 'sold' => 89, 'active' => true, 'perks' => ['Loc cort zona premium', 'Priza electrica', 'Acces dusuri private']],
            ['name' => 'Parking', 'group' => 'Parcari', 'price_ron' => 50, 'quota' => 300, 'sold' => 156, 'active' => true, 'perks' => ['1 loc parcare 5 zile']],
        ];

        $ticketTypes = [];
        foreach ($ticketTypesData as $i => $tt) {
            $ticketType = TicketType::firstOrCreate(
                ['event_id' => $event->id, 'sku' => 'DEMO-AF26-' . Str::slug($tt['name'])],
                [
                    'name' => json_encode(['ro' => $tt['name'], 'en' => $tt['name']]),
                    'ticket_group' => $tt['group'],
                    'price_max' => $tt['price_ron'],
                    'capacity' => $tt['quota'],
                    'is_active' => $tt['active'],
                    'currency' => 'RON',
                    'quota_sold' => $tt['sold'],
                    'perks' => $tt['perks'],
                    'sort_order' => $i + 1,
                    'sales_start_at' => '2026-03-01 00:00:00',
                    'sales_end_at' => $tt['name'] === 'Early Bird 4-Day' ? '2026-05-01 00:00:00' : '2026-07-15 12:00:00',
                    'min_per_order' => 1,
                    'max_per_order' => 10,
                    'is_refundable' => true,
                    'is_entry_ticket' => in_array($tt['group'], ['Acces', 'VIP']),
                    'is_declarable' => true,
                ]
            );
            $ticketTypes[$tt['name']] = $ticketType;
        }
        $this->parent->refs['ticketTypes'] = $ticketTypes;
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;

        $event = Event::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();
        if ($event) {
            TicketType::where('event_id', $event->id)->where('sku', 'like', 'DEMO-AF26-%')->delete();
            $event->artists()->detach();
            $event->eventTypes()->detach();
            $event->eventGenres()->detach();
            $event->delete();
        }

        Artist::where('slug', 'like', 'demo-%')->delete();
        Venue::where('tenant_id', $tenantId)->where('slug', 'demo-arena-festival-park')->delete();
    }
}
