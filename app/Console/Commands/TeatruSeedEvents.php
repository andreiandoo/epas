<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Seating\SeatingLayout;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Populează un tenant de teatru cap-coadă cu date demo:
 * venue + sală (staluri/balcon) + spectacole + bilete + categorii + snapshot-uri.
 *
 *   php artisan teatru:seed-events {tenant=17}
 *
 * Idempotent: refolosește venue-ul/evenimentele după slug.
 */
class TeatruSeedEvents extends Command
{
    protected $signature = 'teatru:seed-events {tenant=17 : ID tenant} {--no-seating : nu crea sala + snapshot}';
    protected $description = 'Seed demo: venue + sală + spectacole + bilete + snapshot pentru un teatru';

    private array $categories = [
        'drama'   => ['ro' => 'Dramă', 'en' => 'Drama'],
        'comedie' => ['ro' => 'Comedie', 'en' => 'Comedy'],
        'balet'   => ['ro' => 'Balet', 'en' => 'Ballet'],
    ];

    // Fotografii free stock (Unsplash), doar pentru demo.
    private function img(string $id, int $w = 1200): string
    {
        return "https://images.unsplash.com/photo-{$id}?w={$w}&q=80&auto=format&fit=crop";
    }

    // Fiecare spectacol: date bogate (imagini, distribuție, echipă creativă, detalii).
    private function shows(): array
    {
        return [
            [
                'title' => 'Hamlet', 'cat' => 'drama', 'days' => 14, 'time' => '19:00',
                'short' => 'Capodopera shakespeariană într-o viziune regizorală contemporană.',
                'body' => '<p>Într-o montare de amploare, tragedia prințului danez este redescoperită printr-o estetică vizuală tulburătoare și interpretări de forță.</p><p>Spectacolul explorează temele răzbunării, ale nebuniei și ale îndoielii, într-un limbaj scenic modern care păstrează întreaga forță a textului original.</p>',
                'director' => 'Alexandru Dabija', 'lead' => 'Marius Manole', 'duration' => '2h 50min',
                'cast' => [
                    ['name' => 'Marius Manole', 'role' => 'Hamlet'],
                    ['name' => 'Maia Morgenstern', 'role' => 'Gertrude'],
                    ['name' => 'Marcel Iureș', 'role' => 'Claudius'],
                    ['name' => 'Oana Pellea', 'role' => 'Ofelia'],
                    ['name' => 'Victor Rebengiuc', 'role' => 'Polonius'],
                ],
                'creative' => [
                    ['role' => 'Scenografie', 'name' => 'Dragoș Buhagiar'],
                    ['role' => 'Costume', 'name' => 'Dragoș Buhagiar'],
                    ['role' => 'Muzica', 'name' => 'Vasile Șirli'],
                ],
                'poster' => '1507924538820-ede94a04019d',
                'gallery' => ['1503095396549-807759245b35', '1516450360452-9312f5e86fc7', '1460881680858-30d872d5b530', '1519677100203-a0e668c92439'],
            ],
            [
                'title' => 'O scrisoare pierdută', 'cat' => 'comedie', 'days' => 21, 'time' => '19:30',
                'short' => 'Comedia clasică a lui I.L. Caragiale, o satiră politică mereu actuală.',
                'body' => '<p>Cea mai jucată comedie a dramaturgiei românești revine pe scenă într-o montare vie și alertă.</p><p>Intrigi electorale, scrisori pierdute și personaje savuroase compun un tablou al moravurilor politice de o actualitate uimitoare.</p>',
                'director' => 'Silviu Purcărete', 'lead' => 'Marcel Iureș', 'duration' => '2h 20min',
                'cast' => [
                    ['name' => 'Marcel Iureș', 'role' => 'Tipătescu'],
                    ['name' => 'Medeea Marinescu', 'role' => 'Zoe'],
                    ['name' => 'Marius Manole', 'role' => 'Cațavencu'],
                    ['name' => 'Victor Rebengiuc', 'role' => 'Trahanache'],
                ],
                'creative' => [
                    ['role' => 'Scenografie', 'name' => 'Dragoș Buhagiar'],
                    ['role' => 'Costume', 'name' => 'Lia Manțoc'],
                ],
                'poster' => '1460881680858-30d872d5b530',
                'gallery' => ['1507924538820-ede94a04019d', '1470229722913-7c0e2dbbafd3', '1516450360452-9312f5e86fc7'],
            ],
            [
                'title' => 'Livada de vișini', 'cat' => 'drama', 'days' => 30, 'time' => '19:00',
                'short' => 'Cehov, despre trecerea timpului și sfârșitul unei lumi.',
                'body' => '<p>Ultima piesă a lui Cehov, o meditație delicată despre schimbare, nostalgie și inevitabila trecere a timpului.</p><p>Într-un ritm muzical, personajele își iau rămas-bun de la o lume care dispare, în timp ce livada de vișini este scoasă la licitație.</p>',
                'director' => 'Silviu Purcărete', 'lead' => 'Oana Pellea', 'duration' => '2h 40min',
                'cast' => [
                    ['name' => 'Oana Pellea', 'role' => 'Liubov Ranevskaia'],
                    ['name' => 'Marcel Iureș', 'role' => 'Gaev'],
                    ['name' => 'Ana Ularu', 'role' => 'Ania'],
                    ['name' => 'Marius Manole', 'role' => 'Lopahin'],
                ],
                'creative' => [
                    ['role' => 'Scenografie', 'name' => 'Dragoș Buhagiar'],
                    ['role' => 'Muzica', 'name' => 'Vasile Șirli'],
                ],
                'poster' => '1470229722913-7c0e2dbbafd3',
                'gallery' => ['1503095396549-807759245b35', '1519677100203-a0e668c92439'],
            ],
            [
                'title' => 'Lacul lebedelor', 'cat' => 'balet', 'days' => 38, 'time' => '18:30',
                'short' => 'Baletul lui Ceaikovski, o poveste despre iubire și transformare.',
                'body' => '<p>Cel mai iubit balet clasic, într-o montare care îmbină virtuozitatea tehnică cu forța emoțională a partiturii lui Ceaikovski.</p><p>Povestea prințesei-lebădă Odette prinde viață printr-o coregrafie de o eleganță rafinată.</p>',
                'director' => 'Alexandru Dabija', 'lead' => 'Ana Ularu', 'duration' => '2h 30min',
                'cast' => [
                    ['name' => 'Ana Ularu', 'role' => 'Odette / Odile'],
                    ['name' => 'Medeea Marinescu', 'role' => 'Regina'],
                ],
                'creative' => [
                    ['role' => 'Coregrafie', 'name' => 'Alexandru Dabija'],
                    ['role' => 'Scenografie', 'name' => 'Dragoș Buhagiar'],
                ],
                'poster' => '1519677100203-a0e668c92439',
                'gallery' => ['1516450360452-9312f5e86fc7', '1460881680858-30d872d5b530', '1507924538820-ede94a04019d'],
            ],
        ];
    }

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant');
        $this->info("Seed demo pentru tenant #{$tenantId}");

        // 1) Venue
        $venue = Venue::where('tenant_id', $tenantId)->where('slug', 'sala-mare-' . $tenantId)->first();
        if (!$venue) {
            $venue = Venue::create([
                'tenant_id'      => $tenantId,
                'name'           => ['ro' => 'Sala Mare', 'en' => 'Main Hall'],
                'slug'           => 'sala-mare-' . $tenantId,
                'city'           => 'București',
                'capacity_total' => 138,
                'description'    => ['ro' => 'Sala principală de spectacole.', 'en' => 'Main performance hall.'],
            ]);
            $this->line("  venue creat: #{$venue->id} Sala Mare");
        } else {
            $this->line("  venue existent: #{$venue->id}");
        }

        // 2) Sală (staluri/balcon) + price tiers
        $layout = null;
        if (!$this->option('no-seating')) {
            Artisan::call('teatru:seed-seating', ['venue' => $venue->id], $this->getOutput());
            $layout = SeatingLayout::where('venue_id', $venue->id)->where('status', 'published')->latest('id')->first();
            if ($layout) { $this->line("  layout sală: #{$layout->id}"); }
        }

        // 2b) Actori (ansamblu) — necesari pentru distribuție
        Artisan::call('teatru:seed-actors', ['tenant' => $tenantId], $this->getOutput());

        // 3) Categorii (globale)
        $cats = [];
        foreach ($this->categories as $slug => $name) {
            $cats[$slug] = EventType::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // 3b) Categorii proprii tenant (TenantEventCategory)
        $tenantCats = [];
        $order = 1;
        $icons = ['drama' => 'fire', 'comedie' => 'face-smile', 'balet' => 'musical-note'];
        foreach ($this->categories as $slug => $name) {
            $tenantCats[$slug] = \App\Models\TenantEventCategory::updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                ['name' => $name, 'icon' => $icons[$slug] ?? 'calendar', 'is_active' => true, 'sort_order' => $order++]
            );
        }

        // 4) Evenimente + bilete (creează sau îmbogățește pe cele existente)
        $created = [];
        foreach ($this->shows() as $s) {
            $slug = \Illuminate\Support\Str::slug($s['title']) . '-' . $tenantId;
            $event = Event::where('tenant_id', $tenantId)->where('slug', $slug)->first();
            $isNew = ! $event;

            $payload = [
                'tenant_id'         => $tenantId,
                'title'             => ['ro' => $s['title'], 'en' => $s['title']],
                'slug'              => $slug,
                'venue_id'          => $venue->id,
                'seating_layout_id' => $layout?->id,
                'duration_mode'     => 'single_day',
                'event_date'        => now()->addDays($s['days'])->toDateString(),
                'start_time'        => $s['time'],
                'end_time'          => '21:30',
                'is_published'      => true,
                'is_cancelled'      => false,
                'short_description' => ['ro' => $s['short'], 'en' => $s['short']],
                'description'       => ['ro' => $s['body'], 'en' => $s['body']],
                // Imagini (free stock, passthrough-safe în API)
                'poster_url'        => $this->img($s['poster'], 800),
                'hero_image_url'    => $this->img($s['poster'], 1920),
                'gallery'           => array_map(fn ($id) => $this->img($id, 1400), $s['gallery']),
                // Detalii spectacol + distribuție + echipă creativă
                'theater_director'  => $s['director'],
                'theater_lead'      => $s['lead'],
                'theater_duration'  => $s['duration'],
                'theater_cast'      => $s['cast'],
                'theater_creative'  => $s['creative'],
            ];

            if ($isNew) {
                $event = Event::create($payload);
            } else {
                $event->update($payload);
            }

            if (isset($cats[$s['cat']])) {
                $event->eventTypes()->syncWithoutDetaching([$cats[$s['cat']]->id]);
            }
            if (isset($tenantCats[$s['cat']])) {
                $event->tenantEventCategories()->syncWithoutDetaching([$tenantCats[$s['cat']]->id]);
            }

            // Bilete: Staluri 80 / Balcon 120 (o singură dată)
            if ($event->ticketTypes()->count() === 0) {
                $event->ticketTypes()->create(['name' => 'Staluri', 'price_max' => 80, 'currency' => 'RON', 'capacity' => 110, 'is_active' => true, 'sort_order' => 1]);
                $event->ticketTypes()->create(['name' => 'Balcon', 'price_max' => 120, 'currency' => 'RON', 'capacity' => 28, 'is_active' => true, 'sort_order' => 2]);
            }

            $this->line(($isNew ? '  eveniment creat: ' : '  eveniment îmbogățit: ') . "{$s['title']} (#{$event->id}) — " . $event->event_date);
            $created[] = $event;
        }

        // 5) Snapshot seating per eveniment
        if ($layout && !$this->option('no-seating')) {
            foreach ($created as $event) {
                Artisan::call('teatru:snapshot', ['event' => $event->id, '--sold' => 8], $this->getOutput());
            }
        }

        $this->newLine();
        $this->info('Gata. Verifică:');
        $this->line('  https://core.tixello.com/api/tenant-client/events?tenant=' . $tenantId);
        $this->line('  https://teatru.tixello.ro/');
        foreach ($created as $e) {
            $this->line('  https://teatru.tixello.ro/spectacol/' . $e->slug . '   (id ' . $e->id . ')');
        }

        return self::SUCCESS;
    }
}
