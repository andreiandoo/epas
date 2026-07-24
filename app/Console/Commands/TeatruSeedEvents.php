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

    // titlu, categorie, zile în viitor, oră, descriere scurtă
    private array $shows = [
        ['Hamlet', 'drama', 14, '19:00', 'Capodopera shakespeariană într-o viziune regizorală contemporană.'],
        ['O scrisoare pierdută', 'comedie', 21, '19:30', 'Comedia clasică a lui I.L. Caragiale, o satiră politică mereu actuală.'],
        ['Livada de vișini', 'drama', 30, '19:00', 'Cehov, despre trecerea timpului și sfârșitul unei lumi.'],
        ['Lacul lebedelor', 'balet', 38, '18:30', 'Baletul lui Ceaikovski, o poveste despre iubire și transformare.'],
    ];

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

        // 3) Categorii (globale)
        $cats = [];
        foreach ($this->categories as $slug => $name) {
            $cats[$slug] = EventType::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // 4) Evenimente + bilete
        $created = [];
        foreach ($this->shows as [$title, $catSlug, $days, $time, $desc]) {
            $slug = \Illuminate\Support\Str::slug($title) . '-' . $tenantId;
            $event = Event::where('tenant_id', $tenantId)->where('slug', $slug)->first();
            if ($event) { $this->line("  eveniment existent: {$title} (#{$event->id})"); $created[] = $event; continue; }

            $event = Event::create([
                'tenant_id'         => $tenantId,
                'title'             => ['ro' => $title, 'en' => $title],
                'slug'              => $slug,
                'venue_id'          => $venue->id,
                'seating_layout_id' => $layout?->id,
                'duration_mode'     => 'single_day',
                'event_date'        => now()->addDays($days)->toDateString(),
                'start_time'        => $time,
                'end_time'          => '21:30',
                'is_published'      => true,
                'is_cancelled'      => false,
                'short_description' => ['ro' => $desc, 'en' => $desc],
                'description'       => ['ro' => '<p>' . $desc . '</p>', 'en' => '<p>' . $desc . '</p>'],
            ]);

            if (isset($cats[$catSlug])) {
                $event->eventTypes()->syncWithoutDetaching([$cats[$catSlug]->id]);
            }

            // Bilete: Staluri 80 / Balcon 120 (aliniate cu price tiers)
            $event->ticketTypes()->create(['name' => 'Staluri', 'price_max' => 80, 'currency' => 'RON', 'capacity' => 110, 'is_active' => true, 'sort_order' => 1]);
            $event->ticketTypes()->create(['name' => 'Balcon', 'price_max' => 120, 'currency' => 'RON', 'capacity' => 28, 'is_active' => true, 'sort_order' => 2]);

            $this->line("  eveniment creat: {$title} (#{$event->id}) — " . $event->event_date);
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
