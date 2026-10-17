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
 * Tenantul demo pentru TRUPĂ (template-ul band, demo-band.tics.ro): „Satelit 9”, o
 * trupă indie-rock inventată din Iași, în turneul albumului „Orbită joasă”: 10 concerte
 * viitoare (cluburi și săli în picioare, un concert acustic pe locuri numerotate cu hartă,
 * unul sold out, unul cu ultimele bilete, unul la un festival cu biletele la organizator)
 * și 4 trecute. Membrii și discografia sunt în secțiunile site-ului (panou). RO + EN.
 *
 *   php artisan demo:seed-band
 *
 * Idempotent după slug / email / domeniu, pur aditiv, nu atinge alți tenanți.
 * Datele sunt relative la ziua rulării.
 */
class DemoBandSeed extends Command
{
    protected $signature = 'demo:seed-band
        {--domain=demo-band.tics.ro : Hostname-ul demo-ului}
        {--owner-email=demo-band@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului}';

    protected $description = 'Seed demo complet pentru o trupă (Satelit 9, turneul Orbită joasă) + demo-ul live demo-band.tics.ro';

    private const NAME = 'Satelit 9';
    private const TENANT_SLUG = 'demo-band';
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
        $this->guarded('sala cu locuri', function () use ($tenant, $venues, &$layout) { $layout = $this->seedHall($tenant, $venues['cluj']); });

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
                    'template' => 'band', 'tenant' => $tenant->id, 'slug' => $m[1],
                    '--title' => 'Trupă',
                    '--description' => 'Site de trupă construit ca un univers: glob cu orașele turneului, echipajul, discografia ca misiuni, concert acustic pe locuri cu hartă.',
                    '--sort' => 17,
                ]);
            }
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant:  #' . $tenant->id . ' ' . $tenant->slug . ' · owner ' . $ownerEmail . ' / ' . $pass);
        $this->line('  Site:    https://' . $domain . '/  · client demo@' . $domain . ' / demo1234 · cod ORBITA10');
        foreach ($events as $ev) {
            $this->line('  Concert: #' . $ev->id . ' ' . $ev->slug . ' (' . ($ev->event_date?->toDateString()) . ')');
        }
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- date */

    /** Concertele: zile față de azi, oră, locație, foto, preț / capacitate / vândute. */
    private function concerts(): array
    {
        return [
            ['slug' => 'satelit-9-timisoara-trecut-demo', 'city' => 'Timișoara', 'days' => -110, 'time' => '21:00', 'venue' => 'timisoara', 'photo' => '1540039155733-5bb30b53aa14', 'past' => true],
            ['slug' => 'satelit-9-bucuresti-trecut-demo', 'city' => 'București', 'days' => -75, 'time' => '21:00', 'venue' => 'bucuresti', 'photo' => '1501612780327-45045538702b', 'past' => true],
            ['slug' => 'satelit-9-brasov-trecut-demo', 'city' => 'Brașov', 'days' => -40, 'time' => '20:30', 'venue' => 'brasov', 'photo' => '1506157786151-b8491531f063', 'past' => true],
            ['slug' => 'satelit-9-iasi-trecut-demo', 'city' => 'Iași', 'days' => -12, 'time' => '21:00', 'venue' => 'iasi', 'photo' => '1493225457124-a3eb161ffa5f', 'past' => true],
            ['slug' => 'satelit-9-sibiu-demo', 'city' => 'Sibiu', 'days' => 9, 'time' => '21:00', 'venue' => 'sibiu', 'photo' => '1501612780327-45045538702b', 'price' => 89, 'cap' => 500, 'sold' => 490],
            ['slug' => 'satelit-9-cluj-acustic-demo', 'city' => 'Cluj-Napoca', 'days' => 16, 'time' => '20:00', 'venue' => 'cluj', 'photo' => '1471478331149-c72f17e33c73', 'seated' => true, 'acoustic' => true],
            ['slug' => 'satelit-9-oradea-demo', 'city' => 'Oradea', 'days' => 23, 'time' => '21:00', 'venue' => 'oradea', 'photo' => '1540039155733-5bb30b53aa14', 'price' => 79, 'cap' => 450, 'sold' => 160],
            ['slug' => 'satelit-9-bucuresti-demo', 'city' => 'București', 'days' => 30, 'time' => '20:30', 'venue' => 'bucuresti', 'photo' => '1493225457124-a3eb161ffa5f', 'price' => 119, 'cap' => 2500, 'sold' => 1350],
            ['slug' => 'satelit-9-chisinau-demo', 'city' => 'Chișinău', 'days' => 37, 'time' => '20:00', 'venue' => 'chisinau', 'photo' => '1506157786151-b8491531f063', 'price' => 99, 'cap' => 1200, 'sold' => 400],
            ['slug' => 'satelit-9-berlin-demo', 'city' => 'Berlin', 'days' => 44, 'time' => '20:30', 'venue' => 'berlin', 'photo' => '1501612780327-45045538702b', 'price' => 129, 'cap' => 400, 'sold' => 400, 'soldout' => true],
            ['slug' => 'satelit-9-praga-demo', 'city' => 'Praga', 'days' => 47, 'time' => '20:30', 'venue' => 'praga', 'photo' => '1540039155733-5bb30b53aa14', 'price' => 119, 'cap' => 500, 'sold' => 130],
            ['slug' => 'satelit-9-viena-demo', 'city' => 'Viena', 'days' => 50, 'time' => '20:00', 'venue' => 'viena', 'photo' => '1493225457124-a3eb161ffa5f', 'price' => 129, 'cap' => 600, 'sold' => 210],
            ['slug' => 'satelit-9-festival-orizont-demo', 'city' => 'Gura Portiței', 'days' => 58, 'time' => '23:00', 'venue' => 'festival', 'photo' => '1506157786151-b8491531f063',
                'external' => ['https://example.com/festival-orizont/bilete', 'Bilete la Festivalul Orizont']],
            ['slug' => 'satelit-9-iasi-demo', 'city' => 'Iași', 'days' => 72, 'time' => '21:00', 'venue' => 'iasi', 'photo' => '1501612780327-45045538702b', 'price' => 99, 'cap' => 3000, 'sold' => 700, 'open_air' => true],
        ];
    }

    /** Locațiile: [slug, nume ro, nume en, oraș, regiune, adresă, țară, lat, lng] */
    private function venueDefs(): array
    {
        return [
            'timisoara' => ['hala-electrica-timisoara-demo-band', 'Hala Electrica', 'Electrica Hall', 'Timișoara', 'Timiș', 'Str. Uzinei 4, Timișoara', 'RO', 45.7489, 21.2087],
            'bucuresti' => ['arena-depou-bucuresti-demo-band', 'Arena Depou', 'Depou Arena', 'București', 'București', 'Bd. Basarabia 32, București', 'RO', 44.4268, 26.1025],
            'brasov' => ['clubul-tampa-brasov-demo-band', 'Clubul Tâmpa', 'Tâmpa Club', 'Brașov', 'Brașov', 'Str. Lungă 90, Brașov', 'RO', 45.6427, 25.5887],
            'iasi' => ['parcul-expozitiei-iasi-demo-band', 'Parcul Expoziției', 'Expo Park', 'Iași', 'Iași', 'Str. Expoziției 1, Iași', 'RO', 47.1585, 27.6014],
            'sibiu' => ['clubul-vama-sibiu-demo-band', 'Clubul Vama', 'Vama Club', 'Sibiu', 'Sibiu', 'Str. Turnului 5, Sibiu', 'RO', 45.7983, 24.1256],
            'cluj' => ['sala-polaris-cluj-demo-band', 'Sala Polaris', 'Polaris Hall', 'Cluj-Napoca', 'Cluj', 'Str. Observatorului 12, Cluj-Napoca', 'RO', 46.7712, 23.6236],
            'oradea' => ['hala-crisul-oradea-demo-band', 'Hala Crișul', 'Crișul Hall', 'Oradea', 'Bihor', 'Str. Crișului 3, Oradea', 'RO', 47.0465, 21.9189],
            'chisinau' => ['casa-radio-chisinau-demo-band', 'Casa Radio', 'Radio House', 'Chișinău', 'Chișinău', 'Str. Mitropolit Varlaam 64, Chișinău', 'MD', 47.0105, 28.8638],
            'berlin' => ['halle-ost-berlin-demo-band', 'Halle Ost', 'Halle Ost', 'Berlin', 'Berlin', 'Revaler Straße 99, Berlin', 'DE', 52.5200, 13.4050],
            'praga' => ['klub-orbit-praha-demo-band', 'Klub Orbit', 'Klub Orbit', 'Praga', 'Praha', 'Dělnická 12, Praha', 'CZ', 50.0755, 14.4378],
            'viena' => ['arena-west-wien-demo-band', 'Arena West', 'Arena West', 'Viena', 'Wien', 'Westbahnstraße 11, Wien', 'AT', 48.2082, 16.3738],
            'festival' => ['festival-orizont-demo-band', 'Festivalul Orizont · Scena Mare', 'Orizont Festival · Main Stage', 'Gura Portiței', 'Tulcea', 'Plaja Gura Portiței', 'RO', 44.6880, 29.0010],
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
        $data = ['name' => self::NAME, 'public_name' => self::NAME, 'company_name' => 'Satelit Nouă Music SRL', 'owner_id' => $user->id,
            'slug' => self::TENANT_SLUG, 'status' => 'active', 'tenant_type' => TenantType::Artist,
            'plan' => '1percent', 'work_method' => 'exclusive', 'commission_rate' => 1.00, 'commission_mode' => 'included',
            'locale' => 'ro', 'currency' => 'RON', 'country' => 'RO', 'city' => 'Iași', 'contact_email' => $email,
            'has_own_website' => false, 'use_core_smtp' => true, 'onboarding_completed' => true, 'billing_cycle_days' => 30];
        if (! $taken) { $data['domain'] = $domain; }
        $settings = ['site_title' => self::NAME, 'site_language' => 'ro', 'storefront' => ['order_emails' => true, 'brand_dark' => '#070A12', 'brand_color' => '#FF6B2C']];
        if (! $t) {
            $data += ['ticket_series_prefix' => 'SAT9', 'settings' => $settings + ['panel' => ['simple_menu' => true]],
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
        foreach ($this->venueDefs() as $k => [$slug, $ro, $en, $city, $state, $addr, $country, $lat, $lng]) {
            $v = Venue::where('slug', $slug)->first();
            $data = $this->only('venues', ['tenant_id' => $tenant->id, 'name' => ['ro' => $ro, 'en' => $en], 'slug' => $slug, 'city' => $city,
                'state' => $state, 'address' => $addr, 'country' => $country, 'lat' => $lat, 'lng' => $lng]);
            $v ? $v->update($data) : ($v = Venue::create($data));
            $out[$k] = $v;
        }
        return $out;
    }

    /* ---------------------------------------------------------------- sala cu locuri (Sala Polaris, set acustic) */

    /** Secțiunile: [nume, cod, preț, culoare, x, y, lățime, înălțime, rânduri, locuri pe rând, arc] */
    private function hallPlan(): array
    {
        return [
            ['Parter', 'PARTER', 149, '#FF6B2C', 170, 170, 860, 440, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'], 24, 10],
            ['Balcon', 'BALCON', 99, '#3DE0C8', 120, 650, 960, 180, ['1', '2', '3', '4'], 28, 30],
        ];
    }

    private function seedHall(Tenant $tenant, Venue $venue): SeatingLayout
    {
        $name = 'Sala Polaris — set acustic';
        $layout = SeatingLayout::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', $name)->first();
        if ($layout) {
            return $layout;
        }
        return DB::transaction(function () use ($tenant, $venue, $name) {
            $layout = SeatingLayout::create($this->only('seating_layouts', [
                'tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'name' => $name, 'status' => 'published',
                'canvas_w' => 1200, 'canvas_h' => 900, 'version' => 1,
            ]));
            SeatingSection::create($this->only('seating_sections', [
                'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => 'Scena', 'section_code' => 'SCENA', 'section_type' => 'decorative',
                'x_position' => 350, 'y_position' => 40, 'width' => 500, 'height' => 80, 'display_order' => 0, 'color_hex' => '#E8ECF4',
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
                        // Rânduri ușor arcuite, cu capetele spre scenă
                        $t = ($n - 1) / max(1, $per - 1) * 2 - 1;
                        SeatingSeat::create([
                            'row_id' => $row->id, 'label' => (string) $n, 'display_name' => $rowLabel . $n,
                            'x' => round(40 + ($n - 1) * $gap, 1), 'y' => round($rowY - $arc * $t * $t, 1), 'angle' => 0, 'shape' => 'circle', 'status' => 'active',
                            'seat_uid' => $section->generateSeatUid($rowLabel, (string) $n),
                        ]);
                    }
                }
            }
            $this->line('  sala Polaris: layout #' . $layout->id);
            return $layout;
        });
    }

    private function seedSeatedTickets(Tenant $tenant, Event $ev, SeatingLayout $layout, MarketplaceEventSeatingService $svc): void
    {
        if ($this->has('events', 'seating_layout_id')) {
            $ev->forceFill(['seating_layout_id' => $layout->id])->saveQuietly();
        }
        $sections = SeatingSection::withoutGlobalScopes()->where('layout_id', $layout->id)->get()->keyBy('name');
        $en = ['Parter' => 'Stalls', 'Balcon' => 'Balcony'];
        $desc = ['Parter' => ['Rândurile A–L, aproape de scenă.', 'Rows A–L, close to the stage.'], 'Balcon' => ['Balconul, cu vedere de ansamblu.', 'The balcony, with a full view.']];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($this->hallPlan() as $i => [$secName, $code, $price, $color, , , , , $rows, $per]) {
            $tier = PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'SAT9-' . $code)->first()
                ?? PriceTier::create(['tenant_id' => $tenant->id, 'name' => $secName, 'tier_code' => 'SAT9-' . $code, 'currency' => 'RON',
                    'price' => $price, 'color' => $color, 'is_active' => true, 'sort_order' => $i + 1]);
            $sku = 'SAT9-CLJ-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $secName; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = count($rows) * $per;
            foreach ($this->only('ticket_types', ['description' => $desc[$secName][0], 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => 10, 'color' => $color,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en[$secName], 'description' => $desc[$secName][1]]]])]) as $c => $v) {
                $tt->{$c} = $v;
            }
            $tt->save();
            if (isset($sections[$secName])) {
                $tt->seatingSections()->sync([$sections[$secName]->id]);
            }
        }
        $es = $svc->getOrCreateEventSeatingByEventId($ev->id);
        if (! $es) {
            $this->warn('  harta concertului acustic nu s-a putut genera');
            return;
        }
        $tiers = PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'like', 'SAT9-%')->get()->keyBy('name');
        foreach ($tiers as $name => $tier) {
            EventSeat::where('event_seating_id', $es->id)->where('section_name', $name)->update(['price_tier_id' => $tier->id]);
        }
        if (! EventSeat::where('event_seating_id', $es->id)->where('status', 'sold')->exists()) {
            foreach (EventSeat::where('event_seating_id', $es->id)->where('status', 'available')->orderBy('id')->get() as $i => $seat) {
                if ($i % 4 === 0 || ($seat->section_name === 'Parter' && $i % 9 === 1)) {
                    $seat->update(['status' => 'sold', 'version' => $seat->version + 1]);
                }
            }
        }
        $this->line('  harta concertului acustic: event_seating #' . $es->id . ', ' . EventSeat::where('event_seating_id', $es->id)->count() . ' locuri');
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
        $cityEn = ['București' => 'Bucharest', 'Praga' => 'Prague', 'Viena' => 'Vienna'][$c['city']] ?? $c['city'];
        $openAir = ! empty($c['open_air']) || ! empty($c['external']);
        $acoustic = ! empty($c['acoustic']);
        $title = ! empty($c['external'])
            ? ['ro' => self::NAME . ' · Festivalul Orizont', 'en' => self::NAME . ' · Orizont Festival']
            : ['ro' => self::NAME . ' · ' . $c['city'] . ($acoustic ? ' (acustic)' : ''), 'en' => self::NAME . ' · ' . $cityEn . ($acoustic ? ' (acoustic)' : '')];
        $payload = $this->only('events', [
            'tenant_id' => $tenant->id, 'slug' => $c['slug'], 'venue_id' => $venue->id,
            'title' => $title,
            'subtitle' => $acoustic ? ['ro' => 'Orbită joasă · set acustic', 'en' => 'Low Orbit · acoustic set'] : ['ro' => 'Orbită joasă Tour', 'en' => 'Low Orbit Tour'],
            'short_description' => $acoustic
                ? ['ro' => 'O singură seară acustică, pe locuri: piesele de pe „Orbită joasă” rearanjate pentru cvartet de coarde.', 'en' => 'One acoustic night, seated: the songs from Low Orbit rearranged for a string quartet.']
                : ['ro' => 'Satelit 9 aterizează la ' . $vRo . ', ' . $c['city'] . ', cu tot albumul „Orbită joasă” și piesele vechi cerute de public.', 'en' => 'Satelit 9 lands at ' . $vEn . ', ' . $cityEn . ', with the whole Low Orbit album and the old songs the crowd asks for.'],
            'description' => [
                'ro' => '<p>' . ($acoustic ? 'Un set acustic de 90 de minute, cu cvartetul de coarde Polar și povești din spatele pieselor.' : 'Două ore de concert, cu proiecții create pentru turneu. Deschide <strong>Radiofar</strong>, la ' . date('H:i', strtotime($c['time'] . ' -50 minutes')) . '.') . '</p>'
                    . '<p>Accesul se face cu biletul electronic, de pe telefon sau tipărit. ' . ($openAir ? 'Concertul e în aer liber și are loc pe orice vreme.' : 'Garderoba e gratuită.') . '</p>'
                    . '<p><em>Concert demonstrativ: trupa și datele sunt inventate, iar plata trece printr-un procesator de test.</em></p>',
                'en' => '<p>' . ($acoustic ? 'A 90-minute acoustic set with the Polar string quartet and the stories behind the songs.' : 'A two-hour show with visuals made for the tour. Support from <strong>Radiofar</strong> at ' . date('H:i', strtotime($c['time'] . ' -50 minutes')) . '.') . '</p>'
                    . '<p>Entry with your e-ticket, on your phone or printed. ' . ($openAir ? 'The show is open-air and goes ahead in any weather.' : 'The cloakroom is free.') . '</p>'
                    . '<p><em>Demo show: the band and dates are made up, and payments go through a test processor.</em></p>',
            ],
            'is_published' => true, 'is_cancelled' => false, 'is_sold_out' => ! empty($c['soldout']), 'is_indoor' => ! $openAir, 'is_kid_friendly' => true,
            'duration_mode' => 'single_day', 'event_date' => $date,
            'door_time' => date('H:i', strtotime($c['time'] . ' -90 minutes')), 'start_time' => $c['time'],
            'end_time' => date('H:i', strtotime($c['time'] . ($acoustic ? ' +100 minutes' : ' +150 minutes'))),
            'poster_url' => self::IMG . $c['photo'] . '?auto=format&fit=crop&w=1200&q=80',
            'hero_image_url' => self::IMG . $c['photo'] . '?auto=format&fit=crop&w=2000&q=80',
            'commission_rate' => 1.00, 'commission_mode' => 'included',
            'external_ticket_url' => $c['external'][0] ?? null,
            'external_ticket_label' => $c['external'][1] ?? null,
        ]);
        $ev ? $ev->update($payload) : ($ev = Event::create($payload));
        return $ev;
    }

    /** Biletele unui concert în picioare: acces general, pachet cu vinil, early entry. */
    private function seedTickets(Event $ev, array $c): void
    {
        $p = (int) $c['price'];
        $cap = (int) $c['cap'];
        $sold = (int) $c['sold'];
        $defs = [
            ['GA', 'Acces general', 'General admission', $p, $cap - 70, max(0, $sold - 70), 'Acces în sală, în picioare.', 'Standing entry.', [], []],
            ['VINIL', 'Acces general + vinil „Orbită joasă”', 'General admission + Low Orbit vinyl', $p + 110, 50, min($sold, 50),
                'Biletul plus vinilul albumului, ridicat de la standul trupei.', 'Entry plus the album on vinyl, picked up at the merch stand.',
                ['Vinil 180 g', 'Ridicare de la stand, fără coadă'], ['180 g vinyl', 'Pick-up at the stand, no queue']],
            ['EARLY', 'Early entry + soundcheck', 'Early entry + soundcheck', $p + 160, 20, ! empty($c['soldout']) ? 20 : min(20, (int) round($sold / 40)),
                'Intri cu o oră înaintea porților și asiști la soundcheck.', 'Get in an hour before doors and watch the soundcheck.',
                ['Soundcheck live', 'Loc în primul rând', 'Patch „Satelit 9”'], ['Live soundcheck', 'Front-row spot', 'Satelit 9 patch']],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($defs as $i => [$code, $ro, $en, $price, $capacity, $sq, $dRo, $dEn, $perks, $perksEn]) {
            $sku = 'SAT9-' . strtoupper(Str::slug($c['city'], '')) . '-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $ro; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = max(1, $capacity);
            foreach ($this->only('ticket_types', ['description' => $dRo, 'perks' => $perks, 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => $code === 'EARLY' ? 4 : 10,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en, 'description' => $dEn, 'perks' => $perksEn]]])]) as $col => $v) {
                $tt->{$col} = $v;
            }
            if ($this->has('ticket_types', 'quota_sold')) {
                $tt->quota_sold = ! empty($c['soldout']) ? $tt->capacity : min($tt->capacity, max((int) ($tt->quota_sold ?? 0), $sq));
            }
            $tt->save();
        }
    }

    private function seedCoupon(Tenant $tenant): void
    {
        if (! Schema::hasTable('coupon_codes') || CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'ORBITA10')->exists()) {
            return;
        }
        CouponCode::create($this->only('coupon_codes', ['tenant_id' => $tenant->id, 'code' => 'ORBITA10', 'code_type' => 'multi_use',
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
