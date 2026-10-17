<?php

namespace App\Console\Commands;

use App\Enums\TenantType;
use App\Models\Coupon\CouponCode;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Seating\EventSeat;
use App\Models\Seating\PriceTier;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\SeatingRow;
use App\Models\Seating\SeatingSeat;
use App\Models\Seating\SeatingSection;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Seating\MarketplaceEventSeatingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tenantul demo pentru ARTIST SOLO (template-ul artist, demo-artist.tics.ro):
 * „Iris Vântu”, o cântăreață inventată, în turneul „Ora albastră”: 9 concerte viitoare
 * (cluburi cu acces general, o sală cu locuri numerotate pe hartă, unul sold out, unul
 * cu ultimele bilete, unul la un festival cu biletele la organizator) și 3 trecute, RO + EN.
 * Fără abonați demo la newsletter: o campanie de test ar pleca spre adrese inexistente.
 *
 *   php artisan demo:seed-artist
 *
 * Idempotent după slug / email / domeniu, pur aditiv, nu atinge alți tenanți.
 * Datele sunt relative la ziua rulării.
 */
class DemoArtistSeed extends Command
{
    protected $signature = 'demo:seed-artist
        {--domain=demo-artist.tics.ro : Hostname-ul demo-ului}
        {--owner-email=demo-artist@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului}';

    protected $description = 'Seed demo complet pentru un artist solo (Iris Vântu, turneul Ora albastră) + demo-ul live demo-artist.tics.ro';

    private const NAME = 'Iris Vântu';
    private const TENANT_SLUG = 'demo-artist';
    private const IMG = 'https://images.unsplash.com/photo-';

    private array $colCache = [];

    public function handle(MarketplaceEventSeatingService $seating): int
    {
        $domain = strtolower(trim((string) $this->option('domain')));
        $ownerEmail = strtolower(trim((string) $this->option('owner-email')));

        try {
            $existing = Tenant::where('slug', self::TENANT_SLUG)->first();
            $foreign = Domain::where('domain', $domain)->first();
            if ($foreign && (! $existing || (int) $foreign->tenant_id !== (int) $existing->id)) {
                $this->error("Domeniul {$domain} e legat de tenantul #{$foreign->tenant_id}. Oprit.");
                return self::FAILURE;
            }
            [$user, $pass] = $this->seedOwner($ownerEmail, (string) ($this->option('owner-password') ?? ''));
            $tenant = $this->seedTenant($user, $ownerEmail, $domain);
            $this->seedDomain($tenant, $domain);
        } catch (\Throwable $e) {
            $this->error('Eroare: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            return self::FAILURE;
        }

        $this->guarded('client demo', fn () => $this->seedCustomer($tenant, 'demo@' . $domain, 'demo1234'));
        $venues = [];
        $this->guarded('locații', function () use ($tenant, &$venues) { $venues = $this->seedVenues($tenant); });
        $layout = null;
        $this->guarded('sala cu locuri', function () use ($tenant, $venues, &$layout) { $layout = $this->seedHall($tenant, $venues['bucuresti']); });

        $events = [];
        foreach ($this->concerts() as $c) {
            $this->guarded('concert ' . $c['slug'], function () use ($tenant, $c, $venues, $layout, $seating, &$events) {
                $ev = $this->seedConcert($tenant, $c, $venues[$c['venue']]);
                if (! empty($c['external'])) {
                    // fără bilete aici: site-ul trimite la organizatorul festivalului
                } elseif (! empty($c['seated']) && $layout) {
                    $this->seedSeatedTickets($tenant, $ev, $layout, $seating);
                } elseif (empty($c['past'])) {
                    $this->seedTickets($ev, $c);
                }
                $events[] = $ev;
            });
        }
        $this->guarded('cod de reducere', fn () => $this->seedCoupon($tenant));
        $this->guarded('cache', function () use ($tenant, $domain) { Cache::forget("domain_tenant_{$domain}"); Cache::forget("tenant_{$tenant->id}"); });

        $this->guarded('demo live', function () use ($tenant, $domain) {
            $base = (string) config('tenant-sites.demo_base_domain', 'tics.ro');
            if (preg_match('/^demo-([a-z0-9-]+)\.' . preg_quote($base, '/') . '$/', $domain, $m)) {
                $this->call('demo:site', [
                    'template' => 'artist', 'tenant' => $tenant->id, 'slug' => $m[1],
                    '--title' => 'Artist solo',
                    '--description' => 'Site de artist pe era albumului: turneu cu bilete, concert pe locuri numerotate cu hartă, album, video, despre.',
                    '--sort' => 16,
                ]);
            }
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant:  #' . $tenant->id . ' ' . $tenant->slug . ' · owner ' . $ownerEmail . ' / ' . $pass);
        $this->line('  Site:    https://' . $domain . '/  · client demo@' . $domain . ' / demo1234 · cod ORA10');
        foreach ($events as $ev) {
            $this->line('  Concert: #' . $ev->id . ' ' . $ev->slug . ' (' . ($ev->event_date?->toDateString()) . ')');
        }
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- date */

    /**
     * Concertele turneului: zile față de azi, oră, locație, foto, bilete.
     * kind: club (acces general + poster semnat + meet & greet), seated (hartă), festival.
     */
    private function concerts(): array
    {
        return [
            ['slug' => 'iris-vantu-sibiu-demo', 'city' => 'Sibiu', 'days' => -88, 'time' => '20:00', 'venue' => 'sibiu', 'photo' => '1470229722913-7c0e2dbbafd3', 'past' => true],
            ['slug' => 'iris-vantu-constanta-demo', 'city' => 'Constanța', 'days' => -57, 'time' => '21:00', 'venue' => 'constanta', 'photo' => '1459749411175-04bf5292ceea', 'past' => true],
            ['slug' => 'iris-vantu-oradea-demo', 'city' => 'Oradea', 'days' => -26, 'time' => '20:00', 'venue' => 'oradea', 'photo' => '1429962714451-bb934ecdc4ec', 'past' => true],
            ['slug' => 'iris-vantu-cluj-demo', 'city' => 'Cluj-Napoca', 'days' => 12, 'time' => '20:30', 'venue' => 'cluj', 'photo' => '1516450360452-9312f5e86fc7', 'price' => 129, 'cap' => 900, 'sold' => 886],
            ['slug' => 'iris-vantu-timisoara-demo', 'city' => 'Timișoara', 'days' => 19, 'time' => '20:30', 'venue' => 'timisoara', 'photo' => '1501386761578-eac5c94b800a', 'price' => 119, 'cap' => 1200, 'sold' => 640],
            ['slug' => 'iris-vantu-iasi-demo', 'city' => 'Iași', 'days' => 26, 'time' => '20:00', 'venue' => 'iasi', 'photo' => '1506157786151-b8491531f063', 'price' => 119, 'cap' => 800, 'sold' => 310],
            ['slug' => 'iris-vantu-bucuresti-demo', 'city' => 'București', 'days' => 34, 'time' => '20:00', 'venue' => 'bucuresti', 'photo' => '1514525253161-7a46d19cd819', 'seated' => true],
            ['slug' => 'iris-vantu-chisinau-demo', 'city' => 'Chișinău', 'days' => 42, 'time' => '19:30', 'venue' => 'chisinau', 'photo' => '1524368535928-5b5e00ddc76b', 'price' => 139, 'cap' => 1500, 'sold' => 420],
            ['slug' => 'iris-vantu-budapesta-demo', 'city' => 'Budapesta', 'days' => 49, 'time' => '20:00', 'venue' => 'budapesta', 'photo' => '1470229722913-7c0e2dbbafd3', 'price' => 159, 'cap' => 700, 'sold' => 210],
            ['slug' => 'iris-vantu-viena-demo', 'city' => 'Viena', 'days' => 56, 'time' => '20:00', 'venue' => 'viena', 'photo' => '1459749411175-04bf5292ceea', 'price' => 169, 'cap' => 600, 'sold' => 600, 'soldout' => true],
            // La un festival: biletele le vinde organizatorul festivalului (link extern, fără coș)
            ['slug' => 'iris-vantu-festival-lumini-demo', 'city' => 'Alba Iulia', 'days' => 63, 'time' => '22:30', 'venue' => 'albaiulia', 'photo' => '1506157786151-b8491531f063',
                'external' => ['https://example.com/festival-lumini/bilete', 'Bilete la Festivalul Lumini']],
            ['slug' => 'iris-vantu-brasov-demo', 'city' => 'Brașov', 'days' => 70, 'time' => '21:00', 'venue' => 'brasov', 'photo' => '1429962714451-bb934ecdc4ec', 'price' => 99, 'cap' => 3000, 'sold' => 150, 'open_air' => true],
        ];
    }

    /** Locațiile: [slug, nume ro, nume en, oraș, județ / regiune, adresă, țară] */
    private function venueDefs(): array
    {
        return [
            'sibiu' => ['sala-faur-sibiu-demo-artist', 'Sala Faur', 'Faur Hall', 'Sibiu', 'Sibiu', 'Str. Morii 12, Sibiu', 'RO'],
            'constanta' => ['terasa-farul-constanta-demo-artist', 'Terasa Farul', 'Farul Terrace', 'Constanța', 'Constanța', 'Faleza Nord 3, Constanța', 'RO'],
            'oradea' => ['clubul-aurora-oradea-demo-artist', 'Clubul Aurora', 'Aurora Club', 'Oradea', 'Bihor', 'Str. Libertății 8, Oradea', 'RO'],
            'cluj' => ['hala-tranzit-cluj-demo-artist', 'Hala Tranzit', 'Tranzit Hall', 'Cluj-Napoca', 'Cluj', 'Str. Fabricii 22, Cluj-Napoca', 'RO'],
            'timisoara' => ['hala-7-timisoara-demo-artist', 'Hala 7', 'Hall 7', 'Timișoara', 'Timiș', 'Str. Gării 7, Timișoara', 'RO'],
            'iasi' => ['sala-atrium-iasi-demo-artist', 'Sala Atrium', 'Atrium Hall', 'Iași', 'Iași', 'Bd. Copou 40, Iași', 'RO'],
            'bucuresti' => ['sala-orion-bucuresti-demo-artist', 'Sala Orion', 'Orion Hall', 'București', 'București', 'Bd. Unirii 61, București', 'RO'],
            'chisinau' => ['arena-nord-chisinau-demo-artist', 'Arena Nord', 'North Arena', 'Chișinău', 'Chișinău', 'Str. Calea Orheiului 18, Chișinău', 'MD'],
            'budapesta' => ['kikoto-budapest-demo-artist', 'Kikötő Club', 'Kikötő Club', 'Budapesta', 'Budapest', 'Duna sétány 4, Budapest', 'HU'],
            'viena' => ['halle-sud-wien-demo-artist', 'Halle Süd', 'Halle Süd', 'Viena', 'Wien', 'Südbahnhofstraße 9, Wien', 'AT'],
            'albaiulia' => ['scena-bastion-alba-iulia-demo-artist', 'Festivalul Lumini · Scena Bastion', 'Lumini Festival · Bastion Stage', 'Alba Iulia', 'Alba', 'Str. Bastionului 3, Alba Iulia', 'RO'],
            'brasov' => ['gradina-cetatii-brasov-demo-artist', 'Grădina Cetății', 'Citadel Garden', 'Brașov', 'Brașov', 'Str. Cetății 1, Brașov', 'RO'],
        ];
    }

    /* ---------------------------------------------------------------- tenant */

    private function seedOwner(string $email, string $opt): array
    {
        $u = User::where('email', $email)->first();
        if ($u) {
            if ($opt !== '') { $u->password = Hash::make($opt); $u->save(); }
            return [$u, $opt !== '' ? $opt : '(neschimbată)'];
        }
        $pass = $opt !== '' ? $opt : Str::random(14);
        $u = User::create($this->only('users', ['name' => self::NAME, 'email' => $email, 'password' => Hash::make($pass), 'role' => 'tenant']));
        if ($this->has('users', 'email_verified_at')) { $u->forceFill(['email_verified_at' => now()])->saveQuietly(); }
        return [$u, $pass];
    }

    private function seedTenant(User $user, string $email, string $domain): Tenant
    {
        $t = Tenant::where('slug', self::TENANT_SLUG)->first();
        $taken = Tenant::where('domain', $domain)->when($t, fn ($q) => $q->where('id', '!=', $t->id))->exists();
        $data = ['name' => self::NAME, 'public_name' => self::NAME, 'company_name' => 'Ora Albastră Music SRL', 'owner_id' => $user->id,
            'slug' => self::TENANT_SLUG, 'status' => 'active', 'tenant_type' => TenantType::Artist,
            'plan' => '1percent', 'work_method' => 'exclusive', 'commission_rate' => 1.00, 'commission_mode' => 'included',
            'locale' => 'ro', 'currency' => 'RON', 'country' => 'RO', 'city' => 'București', 'contact_email' => $email,
            'has_own_website' => false, 'use_core_smtp' => true, 'onboarding_completed' => true, 'billing_cycle_days' => 30];
        if (! $taken) { $data['domain'] = $domain; }
        $settings = ['site_title' => self::NAME, 'site_language' => 'ro', 'storefront' => ['order_emails' => true, 'brand_dark' => '#0B0D12', 'brand_color' => '#2343FF']];
        if (! $t) {
            $data += ['ticket_series_prefix' => 'IRIS', 'settings' => $settings + ['panel' => ['simple_menu' => true]],
                'onboarding_completed_at' => now(), 'billing_starts_at' => now(), 'next_billing_date' => now()->addDays(30)->toDateString()];
            if ($taken) { $data['domain'] = self::TENANT_SLUG . '.' . $domain; }
            return Tenant::create($this->only('tenants', $data));
        }
        $data['settings'] = array_merge($settings, is_array($t->settings) ? $t->settings : []);
        $t->update($this->only('tenants', $data));
        return $t;
    }

    private function seedDomain(Tenant $tenant, string $domain): void
    {
        $data = $this->only('domains', ['tenant_id' => $tenant->id, 'domain' => $domain, 'is_active' => true, 'is_suspended' => false, 'is_primary' => true]);
        $row = Domain::where('domain', $domain)->first();
        $row ? $row->update($data) : ($row = Domain::create($data));
        if ($this->has('domains', 'activated_at') && empty($row->activated_at)) { $row->forceFill(['activated_at' => now()])->saveQuietly(); }
    }

    private function seedCustomer(Tenant $tenant, string $email, string $password): void
    {
        $c = Customer::withTrashed()->where('tenant_id', $tenant->id)->where('email', $email)->first();
        $data = $this->only('customers', ['tenant_id' => $tenant->id, 'primary_tenant_id' => $tenant->id, 'email' => $email,
            'first_name' => 'Client', 'last_name' => 'Demo', 'phone' => '0700000000', 'password' => Hash::make($password)]);
        if ($c) { if ($c->trashed()) { $c->restore(); } $c->update($data); } else { $c = Customer::create($data); }
        try { $c->tenants()->syncWithoutDetaching([$tenant->id]); } catch (\Throwable $e) {}
    }

    private function seedVenues(Tenant $tenant): array
    {
        $out = [];
        foreach ($this->venueDefs() as $k => [$slug, $ro, $en, $city, $state, $addr, $country]) {
            $v = Venue::where('slug', $slug)->first();
            $data = $this->only('venues', ['tenant_id' => $tenant->id, 'name' => ['ro' => $ro, 'en' => $en], 'slug' => $slug, 'city' => $city, 'state' => $state, 'address' => $addr, 'country' => $country]);
            $v ? $v->update($data) : ($v = Venue::create($data));
            $out[$k] = $v;
        }
        return $out;
    }

    /* ---------------------------------------------------------------- sala cu locuri (Sala Orion) */

    /** Secțiunile sălii: [nume, cod, preț, culoare, x, y, lățime, înălțime, rânduri, locuri pe rând, arc] */
    private function hallPlan(): array
    {
        return [
            ['Parter față', 'PARTER-FATA', 289, '#2343FF', 210, 170, 780, 200, ['A', 'B', 'C', 'D', 'E'], 22, 0],
            ['Parter', 'PARTER', 219, '#5B79FF', 160, 390, 880, 330, ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'], 26, 0],
            ['Balcon', 'BALCON', 149, '#9AA9FF', 110, 750, 980, 210, ['1', '2', '3', '4', '5'], 30, 26],
        ];
    }

    private function seedHall(Tenant $tenant, Venue $venue): SeatingLayout
    {
        $name = 'Sala Orion — plan concert';
        $layout = SeatingLayout::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', $name)->first();
        if ($layout) {
            return $layout;
        }
        return DB::transaction(function () use ($tenant, $venue, $name) {
            $layout = SeatingLayout::create($this->only('seating_layouts', [
                'tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'name' => $name, 'status' => 'published',
                'canvas_w' => 1200, 'canvas_h' => 1000, 'version' => 1,
            ]));
            // Scena: secțiune fără locuri (harta o desenează ca zonă cu nume)
            SeatingSection::create($this->only('seating_sections', [
                'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => 'Scena', 'section_code' => 'SCENA', 'section_type' => 'decorative',
                'x_position' => 330, 'y_position' => 40, 'width' => 540, 'height' => 80, 'display_order' => 0, 'color_hex' => '#0B0D12',
            ]));
            foreach ($this->hallPlan() as $order => [$secName, $code, $price, $color, $x, $y, $w, $h, $rows, $per, $arc]) {
                $section = SeatingSection::create($this->only('seating_sections', [
                    'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => $secName, 'section_code' => $code, 'section_type' => 'standard',
                    'x_position' => $x, 'y_position' => $y, 'width' => $w, 'height' => $h, 'rotation' => 0, 'display_order' => $order + 1,
                    'color_hex' => $color, 'seat_color' => $color, 'metadata' => ['seat_size' => 18],
                ]));
                $gap = ($w - 80) / max(1, $per - 1);
                foreach ($rows as $ri => $rowLabel) {
                    $rowY = 30 + $ri * 34;
                    $row = SeatingRow::create(['section_id' => $section->id, 'label' => $rowLabel, 'seat_start_number' => 1, 'y' => $rowY, 'rotation' => 0, 'seat_count' => $per]);
                    for ($n = 1; $n <= $per; $n++) {
                        // Balconul e arcuit: capetele rândului urcă spre scenă
                        $t = ($n - 1) / max(1, $per - 1) * 2 - 1;
                        SeatingSeat::create([
                            'row_id' => $row->id, 'label' => (string) $n, 'display_name' => $rowLabel . $n,
                            'x' => round(40 + ($n - 1) * $gap, 1), 'y' => round($rowY - $arc * $t * $t, 1), 'angle' => 0, 'shape' => 'circle', 'status' => 'active',
                            'seat_uid' => $section->generateSeatUid($rowLabel, (string) $n),
                        ]);
                    }
                }
            }
            $this->line('  sala Orion: layout #' . $layout->id);
            return $layout;
        });
    }

    /** Concertul din sală: tipuri de bilet legate de secțiuni (has_seating), harta pe eveniment, prețuri pe locuri. */
    private function seedSeatedTickets(Tenant $tenant, Event $ev, SeatingLayout $layout, MarketplaceEventSeatingService $svc): void
    {
        if ($this->has('events', 'seating_layout_id')) {
            $ev->forceFill(['seating_layout_id' => $layout->id])->saveQuietly();
        }
        $sections = SeatingSection::withoutGlobalScopes()->where('layout_id', $layout->id)->get()->keyBy('name');
        $en = ['Parter față' => 'Front stalls', 'Parter' => 'Stalls', 'Balcon' => 'Balcony'];
        $desc = [
            'Parter față' => ['Rândurile A–E, cel mai aproape de scenă.', 'Rows A–E, closest to the stage.'],
            'Parter' => ['Rândurile F–N, vedere frontală spre scenă.', 'Rows F–N, facing the stage.'],
            'Balcon' => ['Balconul arcuit, cu vedere de ansamblu.', 'The curved balcony, with a full view.'],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($this->hallPlan() as $i => [$secName, $code, $price, $color, , , , , $rows, $per]) {
            $tier = PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'IRIS-' . $code)->first()
                ?? PriceTier::create(['tenant_id' => $tenant->id, 'name' => $secName, 'tier_code' => 'IRIS-' . $code, 'currency' => 'RON',
                    'price' => $price, 'color' => $color, 'is_active' => true, 'sort_order' => $i + 1]);
            $sku = 'IRIS-BUC-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $secName; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = count($rows) * $per;
            foreach ($this->only('ticket_types', ['description' => $desc[$secName][0], 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => 10, 'color' => $color,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en[$secName], 'description' => $desc[$secName][1]]]])]) as $c => $v) {
                $tt->{$c} = $v;
            }
            $tt->save();
            // Biletul se cumpără alegând locul din secțiunea lui (has_seating în API)
            if (isset($sections[$secName])) {
                $tt->seatingSections()->sync([$sections[$secName]->id]);
            }
        }

        $es = $svc->getOrCreateEventSeatingByEventId($ev->id);
        if (! $es) {
            $this->warn('  harta concertului din București nu s-a putut genera');
            return;
        }
        $tiers = PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'like', 'IRIS-%')->get()->keyBy('name');
        foreach ($tiers as $name => $tier) {
            EventSeat::where('event_seating_id', $es->id)->where('section_name', $name)->update(['price_tier_id' => $tier->id]);
        }
        // Locuri vândute, ca sala să arate vie (mai multe în față), doar la prima generare
        if (! EventSeat::where('event_seating_id', $es->id)->where('status', 'sold')->exists()) {
            foreach (EventSeat::where('event_seating_id', $es->id)->where('status', 'available')->orderBy('id')->get() as $i => $seat) {
                $front = $seat->section_name === 'Parter față';
                if (($front && $i % 3 === 0) || (! $front && $i % 7 === 0)) {
                    $seat->update(['status' => 'sold', 'version' => $seat->version + 1]);
                }
            }
        }
        $this->line('  harta concertului: event_seating #' . $es->id . ', ' . EventSeat::where('event_seating_id', $es->id)->count() . ' locuri');
    }

    /* ---------------------------------------------------------------- concerte */

    private function seedConcert(Tenant $tenant, array $c, Venue $venue): Event
    {
        $ev = Event::where('slug', $c['slug'])->first();
        if ($ev && (int) $ev->tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('slug-ul aparține altui tenant');
        }
        $date = now()->addDays($c['days'])->toDateString();
        [, $vRo, $vEn] = $this->venueDefs()[$c['venue']];
        $cityEn = ['București' => 'Bucharest', 'Budapesta' => 'Budapest', 'Viena' => 'Vienna'][$c['city']] ?? $c['city'];
        $openAir = ! empty($c['open_air']);
        $payload = $this->only('events', [
            'tenant_id' => $tenant->id, 'slug' => $c['slug'], 'venue_id' => $venue->id,
            'title' => ! empty($c['external'])
                ? ['ro' => self::NAME . ' · Festivalul Lumini', 'en' => self::NAME . ' · Lumini Festival']
                : ['ro' => self::NAME . ' · ' . $c['city'], 'en' => self::NAME . ' · ' . $cityEn],
            'subtitle' => ['ro' => 'Ora albastră Tour', 'en' => 'Blue Hour Tour'],
            'short_description' => [
                'ro' => 'Turneul „Ora albastră” ajunge la ' . $vRo . ', ' . $c['city'] . ': tot albumul nou, live, plus piesele care au adus-o aici.',
                'en' => 'The Blue Hour tour comes to ' . $vEn . ', ' . $cityEn . ': the whole new album live, plus the songs that got her here.',
            ],
            'description' => [
                'ro' => '<p>Două ore de concert cu trupa completă și un set acustic la mijlocul serii. Deschide <strong>Mara Lin</strong>, la ' . date('H:i', strtotime($c['time'] . ' -45 minutes')) . '.</p>'
                    . '<p>Accesul se face cu biletul electronic, de pe telefon sau tipărit. ' . ($openAir ? 'Concertul e în aer liber și are loc pe orice vreme.' : 'Garderoba e gratuită.') . '</p>'
                    . '<p><em>Concert demonstrativ: artista și datele sunt inventate, iar plata trece printr-un procesator de test.</em></p>',
                'en' => '<p>A two-hour show with the full band and an acoustic set halfway through. Support from <strong>Mara Lin</strong> at ' . date('H:i', strtotime($c['time'] . ' -45 minutes')) . '.</p>'
                    . '<p>Entry with your e-ticket, on your phone or printed. ' . ($openAir ? 'The show is open-air and goes ahead in any weather.' : 'The cloakroom is free.') . '</p>'
                    . '<p><em>Demo show: the artist and dates are made up, and payments go through a test processor.</em></p>',
            ],
            'is_published' => true, 'is_cancelled' => false, 'is_sold_out' => ! empty($c['soldout']), 'is_indoor' => ! $openAir, 'is_kid_friendly' => true,
            'duration_mode' => 'single_day', 'event_date' => $date,
            'door_time' => date('H:i', strtotime($c['time'] . ' -90 minutes')), 'start_time' => $c['time'],
            'end_time' => date('H:i', strtotime($c['time'] . ' +2 hours 30 minutes')),
            'poster_url' => self::IMG . $c['photo'] . '?auto=format&fit=crop&w=1200&q=80',
            'hero_image_url' => self::IMG . $c['photo'] . '?auto=format&fit=crop&w=2000&q=80',
            'commission_rate' => 1.00, 'commission_mode' => 'included',
            'external_ticket_url' => $c['external'][0] ?? null,
            'external_ticket_label' => $c['external'][1] ?? null,
        ]);
        $ev ? $ev->update($payload) : ($ev = Event::create($payload));
        return $ev;
    }

    /** Biletele unui concert în club / aer liber: acces general, cu poster semnat, meet & greet. */
    private function seedTickets(Event $ev, array $c): void
    {
        $p = (int) $c['price'];
        $cap = (int) $c['cap'];
        $defs = [
            ['GA', 'Acces general', 'General admission', $p, $cap - 60, (int) $c['sold'] - min((int) $c['sold'], 40),
                'Acces în sală, în picioare.', 'Standing entry.', [], []],
            ['POSTER', 'Acces general + poster semnat', 'General admission + signed poster', $p + 60, 40, min((int) $c['sold'], 40),
                'Biletul de acces plus posterul turneului, semnat de Iris, primit la intrare.', 'Entry plus the tour poster, signed by Iris, handed out at the door.',
                ['Poster A2 semnat', 'Intrare pe culoarul rapid'], ['Signed A2 poster', 'Fast-lane entry']],
            ['MEET', 'Meet & greet', 'Meet & greet', $p + 270, 20, ! empty($c['soldout']) ? 20 : 13,
                'Întâlnire cu Iris după soundcheck, poză și acces la repetiție.', 'Meet Iris after soundcheck, photo and access to the rehearsal.',
                ['Acces la soundcheck', 'Poză cu Iris', 'Tote bag „Ora albastră”', 'Acces general inclus'], ['Soundcheck access', 'Photo with Iris', 'Blue Hour tote bag', 'General admission included']],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($defs as $i => [$code, $ro, $en, $price, $capacity, $sold, $dRo, $dEn, $perks, $perksEn]) {
            $sku = 'IRIS-' . strtoupper(Str::slug($c['city'], '')) . '-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $ro; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = max(1, $capacity);
            foreach ($this->only('ticket_types', ['description' => $dRo, 'perks' => $perks, 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => $code === 'MEET' ? 2 : 10,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en, 'description' => $dEn, 'perks' => $perksEn]]])]) as $col => $v) {
                $tt->{$col} = $v;
            }
            // Vânzările demo: aproape sold out la Cluj, sold out la Viena
            if ($this->has('ticket_types', 'quota_sold')) {
                $tt->quota_sold = ! empty($c['soldout']) ? $tt->capacity : min($tt->capacity, max((int) ($tt->quota_sold ?? 0), $sold));
            }
            $tt->save();
        }
    }

    private function seedCoupon(Tenant $tenant): void
    {
        if (! Schema::hasTable('coupon_codes') || CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'ORA10')->exists()) {
            return;
        }
        CouponCode::create($this->only('coupon_codes', ['tenant_id' => $tenant->id, 'code' => 'ORA10', 'code_type' => 'multi_use',
            'discount_type' => 'percentage', 'discount_value' => 10, 'status' => 'active', 'is_public' => false, 'combinable' => false, 'source' => 'demo']));
    }

    /* ---------------------------------------------------------------- utilitare */

    private function guarded(string $label, callable $fn): void
    {
        try { $fn(); } catch (\Throwable $e) { $this->warn("  [{$label}] sărit: " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()); }
    }

    private function cols(string $t): array
    {
        if (! array_key_exists($t, $this->colCache)) {
            try { $this->colCache[$t] = Schema::getColumnListing($t); } catch (\Throwable $e) { $this->colCache[$t] = []; }
        }
        return $this->colCache[$t];
    }

    private function has(string $t, string $c): bool { return in_array($c, $this->cols($t), true); }

    private function only(string $t, array $d): array
    {
        $c = $this->cols($t);
        return empty($c) ? $d : array_intersect_key($d, array_flip($c));
    }
}
