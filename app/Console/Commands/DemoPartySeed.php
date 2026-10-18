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
use App\Models\TenantArtist;
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
 * Tenantul demo pentru PETRECERE RECURENTĂ (template-ul party, demo-petrecere.tics.ro):
 * „Casetofonul”, petreceri cu muzica anilor ’90 și 2000 în mai multe orașe. Ediții
 * tematice cu valuri de preț (early bird epuizat, valul 2 aproape gata, valul 3 încă
 * închis), mese VIP vândute pe hartă (locuri la masă), pachet pentru grupuri, o ediție
 * sold out, una la un festival cu biletele la organizator, DJ-ii rezidenți și galerii foto
 * la petrecerile trecute. RO + EN.
 *
 *   php artisan demo:seed-party
 *
 * Idempotent după slug / email / domeniu, pur aditiv, nu atinge alți tenanți.
 */
class DemoPartySeed extends Command
{
    protected $signature = 'demo:seed-party
        {--domain=demo-petrecere.tics.ro : Hostname-ul demo-ului}
        {--owner-email=demo-petrecere@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului}';

    protected $description = 'Seed demo complet pentru o petrecere recurentă (Casetofonul) + demo-ul live demo-petrecere.tics.ro';

    private const NAME = 'Casetofonul';
    private const TENANT_SLUG = 'demo-petrecere';
    private const IMG = 'https://images.unsplash.com/photo-';

    /** Fotografiile de la petreceri (galerii, afișe). */
    private const PHOTOS = [
        '1514525253161-7a46d19cd819', '1492684223066-81342ee5ff30', '1496337589254-7e19d01cec44', '1516450137517-162bfbeb8dba',
        '1517457373958-b7bdd4587205', '1519671482749-fd09be7ccebf', '1533174072545-7a4b6ad7a6c3', '1541532713592-79a0317b6b77',
        '1545128485-c400e7702796', '1566737236500-c8ac43014a67', '1574391884720-bbc3740c59d1', '1501281668745-f7f57925c3b4',
        '1429962714451-bb934ecdc4ec', '1459749411175-04bf5292ceea',
    ];

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
        $djs = [];
        $this->guarded('DJ', function () use ($tenant, &$djs) { $djs = $this->seedDjs($tenant); });
        $venues = [];
        $this->guarded('locații', function () use ($tenant, &$venues) { $venues = $this->seedVenues($tenant); });
        $layout = null;
        $this->guarded('clubul cu mese', function () use ($tenant, $venues, &$layout) { $layout = $this->seedClub($tenant, $venues['bucuresti']); });

        $events = [];
        foreach ($this->parties() as $i => $c) {
            $this->guarded('petrecere ' . $c['slug'], function () use ($tenant, $c, $i, $venues, $layout, $seating, $djs, &$events) {
                $ev = $this->seedParty($tenant, $c, $i, $venues[$c['venue']], $djs);
                if (! empty($c['external']) || ! empty($c['past'])) {
                    // fără bilete aici: trecută sau la organizatorul festivalului
                } else {
                    $this->seedTiers($ev, $c);
                    if (! empty($c['tables']) && $layout) {
                        $this->seedTables($tenant, $ev, $layout, $seating);
                    }
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
                    'template' => 'party', 'tenant' => $tenant->id, 'slug' => $m[1],
                    '--title' => 'Petrecere recurentă',
                    '--description' => 'Ediții tematice în mai multe orașe: valuri de preț, mese VIP pe hartă, pachete de grup, DJ rezidenți, galerii foto de la petrecerile trecute.',
                    '--sort' => 19,
                ]);
            }
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant:  #' . $tenant->id . ' ' . $tenant->slug . ' · owner ' . $ownerEmail . ' / ' . $pass);
        $this->line('  Site:    https://' . $domain . '/  · client demo@' . $domain . ' / demo1234 · cod RETRO10');
        foreach ($events as $ev) {
            $this->line('  Ediție:  #' . $ev->id . ' ' . $ev->slug . ' (' . ($ev->event_date?->toDateString()) . ')');
        }
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- date */

    /** DJ-ii rezidenți: [cheie, nume, genuri, oraș, foto, frază ro, frază en, bio ro, bio en] */
    private function djDefs(): array
    {
        return [
            ['dj-caseta', 'DJ Casetă', ['eurodance', 'pop ’90'], 'București', '1496337589254-7e19d01cec44', 'Omul care știe pe de rost fiecare refren din 1996.', 'The man who knows every chorus from 1996 by heart.',
                'Rezident Casetofonul de la prima ediție. A cântat la peste 300 de petreceri și nu a repetat niciodată același set.', 'A Casetofonul resident since the first party. He has played more than 300 nights and never repeated a set.'],
            ['dj-vinil', 'Vinil Vasile', ['pop 2000', 'r&b'], 'Cluj-Napoca', '1576525865260-9f0e7cfb02b3', 'Colecționar de discuri și de momente „ce piesă!”.', 'Collector of records and “what a song!” moments.',
                'Are peste 4.000 de discuri și știe exact ce piesă îi face pe toți să cânte în cor.', 'He owns more than 4,000 records and knows exactly which song gets everyone singing along.'],
            ['dj-raluca', 'Raluca Retro', ['boy bands', 'girl power'], 'Iași', '1470225620780-dba8ba36b745', 'Seturi pline de refrene pe care le știi fără să vrei.', 'Sets full of choruses you know without trying.',
                'Prima femeie DJ rezidentă Casetofonul. Specialistă în boy bands și hituri de pe casete trase de la radio.', 'The first female Casetofonul resident. A specialist in boy bands and hits taped off the radio.'],
        ];
    }

    /**
     * Edițiile: zile față de azi, oraș / locație, temă, DJ, preț (early bird), capacitate,
     * vânzări pe valuri, mese pe hartă, sold out, festival extern, trecută (galerie).
     */
    private function parties(): array
    {
        return [
            ['slug' => 'casetofonul-bucuresti-vara-99-demo', 'theme' => ['Vara lui ’99', 'Summer of ’99'], 'days' => -96, 'venue' => 'bucuresti', 'djs' => ['dj-caseta'], 'past' => true],
            ['slug' => 'casetofonul-cluj-boy-bands-demo', 'theme' => ['Boy Bands & Girl Power', 'Boy Bands & Girl Power'], 'days' => -61, 'venue' => 'cluj', 'djs' => ['dj-raluca', 'dj-vinil'], 'past' => true],
            ['slug' => 'casetofonul-iasi-eurodance-demo', 'theme' => ['Eurodance', 'Eurodance'], 'days' => -33, 'venue' => 'iasi', 'djs' => ['dj-caseta'], 'past' => true],
            ['slug' => 'casetofonul-timisoara-pop-2000-demo', 'theme' => ['Pop 2000', 'Pop 2000'], 'days' => -12, 'venue' => 'timisoara', 'djs' => ['dj-vinil'], 'past' => true],
            ['slug' => 'casetofonul-bucuresti-eurodance-demo', 'theme' => ['Eurodance', 'Eurodance'], 'days' => 6, 'venue' => 'bucuresti', 'djs' => ['dj-caseta', 'dj-vinil'], 'price' => 59, 'cap' => 900, 'sold' => [1, 0.97, 0], 'tables' => true],
            ['slug' => 'casetofonul-cluj-pop-2000-demo', 'theme' => ['Pop 2000', 'Pop 2000'], 'days' => 13, 'venue' => 'cluj', 'djs' => ['dj-vinil'], 'price' => 55, 'cap' => 700, 'sold' => [1, 0.55, 0]],
            ['slug' => 'casetofonul-iasi-boy-bands-demo', 'theme' => ['Boy Bands & Girl Power', 'Boy Bands & Girl Power'], 'days' => 20, 'venue' => 'iasi', 'djs' => ['dj-raluca'], 'price' => 49, 'cap' => 600, 'sold' => [0.7, 0, 0]],
            ['slug' => 'casetofonul-bucuresti-halloween-demo', 'theme' => ['Halloween Retro', 'Retro Halloween'], 'days' => 27, 'venue' => 'bucuresti', 'djs' => ['dj-caseta', 'dj-raluca'], 'price' => 69, 'cap' => 900, 'sold' => [1, 1, 1], 'soldout' => true],
            ['slug' => 'casetofonul-timisoara-rock-90-demo', 'theme' => ['Rock românesc ’90', 'Romanian ’90s Rock'], 'days' => 34, 'venue' => 'timisoara', 'djs' => ['dj-vinil'], 'price' => 55, 'cap' => 650, 'sold' => [0.9, 0.1, 0]],
            ['slug' => 'casetofonul-brasov-eurodance-demo', 'theme' => ['Eurodance', 'Eurodance'], 'days' => 41, 'venue' => 'brasov', 'djs' => ['dj-caseta'], 'price' => 49, 'cap' => 500, 'sold' => [0.5, 0, 0]],
            ['slug' => 'casetofonul-festival-val-demo', 'theme' => ['la Festivalul Val', 'at Val Festival'], 'days' => 48, 'venue' => 'festival', 'djs' => ['dj-caseta', 'dj-vinil', 'dj-raluca'],
                'external' => ['https://example.com/festival-val/bilete', 'Bilete la Festivalul Val']],
            ['slug' => 'casetofonul-revelion-retro-demo', 'theme' => ['Revelion Retro', 'Retro New Year’s Eve'], 'days' => 70, 'venue' => 'bucuresti', 'djs' => ['dj-caseta', 'dj-vinil', 'dj-raluca'], 'price' => 129, 'cap' => 900, 'sold' => [0.6, 0, 0], 'tables' => true, 'late' => true],
        ];
    }

    /** Locațiile: [slug, nume ro, nume en, oraș, regiune, adresă, țară, lat, lng] */
    private function venueDefs(): array
    {
        return [
            'bucuresti' => ['club-zenit-bucuresti-demo-petrecere', 'Club Zenit', 'Zenit Club', 'București', 'București', 'Str. Gabroveni 20, București', 'RO', 44.4310, 26.1010],
            'cluj' => ['club-arcade-cluj-demo-petrecere', 'Club Arcade', 'Arcade Club', 'Cluj-Napoca', 'Cluj', 'Str. Piezișă 6, Cluj-Napoca', 'RO', 46.7650, 23.5800],
            'iasi' => ['hala-tramvaielor-iasi-demo-petrecere', 'Hala Tramvaielor', 'Tram Hall', 'Iași', 'Iași', 'Str. Bucium 2, Iași', 'RO', 47.1520, 27.6000],
            'timisoara' => ['club-neon-timisoara-demo-petrecere', 'Club Neon', 'Neon Club', 'Timișoara', 'Timiș', 'Str. Mărășești 9, Timișoara', 'RO', 45.7560, 21.2290],
            'brasov' => ['ballroom-coroana-brasov-demo-petrecere', 'Ballroom Coroana', 'Coroana Ballroom', 'Brașov', 'Brașov', 'Str. Republicii 62, Brașov', 'RO', 45.6430, 25.5900],
            'festival' => ['festivalul-val-casetofonul-demo-petrecere', 'Festivalul Val · Cortul Retro', 'Val Festival · Retro Tent', 'Vama Veche', 'Constanța', 'Plaja Vama Veche', 'RO', 43.7520, 28.5790],
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
        $data = ['name' => self::NAME, 'public_name' => self::NAME, 'company_name' => 'Casetofonul Events SRL', 'owner_id' => $user->id,
            'slug' => self::TENANT_SLUG, 'status' => 'active', 'tenant_type' => TenantType::Festival,
            'plan' => '2percent', 'work_method' => 'mixed', 'commission_rate' => 2.00, 'commission_mode' => 'included',
            'locale' => 'ro', 'currency' => 'RON', 'country' => 'RO', 'city' => 'București', 'contact_email' => $email,
            'has_own_website' => false, 'use_core_smtp' => true, 'onboarding_completed' => true, 'billing_cycle_days' => 30];
        if (! $taken) { $data['domain'] = $domain; }
        $settings = ['site_title' => self::NAME, 'site_language' => 'ro', 'storefront' => ['order_emails' => true, 'brand_dark' => '#0A0A0D', 'brand_color' => '#FF2E7E']];
        if (! $t) {
            $data += ['ticket_series_prefix' => 'CSTF', 'settings' => $settings + ['panel' => ['simple_menu' => true]],
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

    /** @return array<string, TenantArtist> */
    private function seedDjs(Tenant $tenant): array
    {
        $out = [];
        foreach ($this->djDefs() as [$key, $name, $genres, $city, $photo, $tRo, $tEn, $bRo, $bEn]) {
            $a = TenantArtist::firstOrNew(['tenant_id' => $tenant->id, 'slug' => $key]);
            $a->fill([
                'name' => $name, 'role' => 'dj', 'status' => 'active', 'is_resident' => true,
                'bio' => ['ro' => '<p>' . $bRo . '</p>', 'en' => '<p>' . $bEn . '</p>'],
                'photo_url' => self::IMG . $photo . '?auto=format&fit=crop&w=900&h=1100&q=80',
                'meta' => ['kind' => 'dj', 'genres' => $genres, 'city' => $city, 'country' => 'RO', 'tagline' => $tRo, 'bookable' => false, 'featured' => true,
                    'i18n' => ['en' => ['tagline' => $tEn]]],
            ]);
            $a->save();
            $out[$key] = $a;
        }
        return $out;
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

    /* ---------------------------------------------------------------- clubul cu mese (Club Zenit) */

    /** Mesele: [secțiune, cod, preț pe loc, culoare, x, y, lățime, înălțime, mese, locuri la masă, rază masă] */
    private function clubPlan(): array
    {
        return [
            ['Mese VIP', 'VIP', 249, '#FF2E7E', 60, 420, 1080, 200, 6, 6, 34],
            ['Mese Gold', 'GOLD', 349, '#C9CED6', 300, 660, 600, 170, 3, 8, 40],
        ];
    }

    private function seedClub(Tenant $tenant, Venue $venue): SeatingLayout
    {
        $name = 'Club Zenit — mese';
        $layout = SeatingLayout::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', $name)->first();
        if ($layout) {
            return $layout;
        }
        return DB::transaction(function () use ($tenant, $venue, $name) {
            $layout = SeatingLayout::create($this->only('seating_layouts', [
                'tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'name' => $name, 'status' => 'published',
                'canvas_w' => 1200, 'canvas_h' => 880, 'version' => 1,
            ]));
            // Zonele fără locuri: cabina DJ și ringul de dans (acolo intră biletele obișnuite)
            foreach ([['Cabina DJ', 'DJ', 450, 30, 300, 70], ['Ring de dans', 'RING', 160, 130, 880, 250]] as $o => [$n, $code, $x, $y, $w, $h]) {
                SeatingSection::create($this->only('seating_sections', [
                    'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => $n, 'section_code' => $code, 'section_type' => 'decorative',
                    'x_position' => $x, 'y_position' => $y, 'width' => $w, 'height' => $h, 'display_order' => $o, 'color_hex' => '#8A8F99',
                ]));
            }
            foreach ($this->clubPlan() as $order => [$secName, $code, $price, $color, $x, $y, $w, $h, $tables, $per, $radius]) {
                $section = SeatingSection::create($this->only('seating_sections', [
                    'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => $secName, 'section_code' => $code, 'section_type' => 'standard',
                    'x_position' => $x, 'y_position' => $y, 'width' => $w, 'height' => $h, 'rotation' => 0, 'display_order' => $order + 2,
                    'color_hex' => $color, 'seat_color' => $color, 'metadata' => ['seat_size' => 16],
                ]));
                $step = $w / $tables;
                for ($t = 1; $t <= $tables; $t++) {
                    $cx = round($step * ($t - 0.5), 1);
                    $cy = round($h / 2, 1);
                    $label = ($code === 'VIP' ? 'Masa ' : 'Masa G') . $t;
                    $row = SeatingRow::create(['section_id' => $section->id, 'label' => $label, 'seat_start_number' => 1, 'y' => $cy, 'rotation' => 0, 'seat_count' => $per,
                        'metadata' => ['is_table' => true, 'table_type' => 'round', 'center_x' => $cx, 'center_y' => $cy, 'radius' => $radius]]);
                    for ($n = 1; $n <= $per; $n++) {
                        // Scaunele în jurul mesei, la distanță egală
                        $a = -M_PI / 2 + ($n - 1) * 2 * M_PI / $per;
                        SeatingSeat::create([
                            'row_id' => $row->id, 'label' => (string) $n, 'display_name' => $label . '-' . $n,
                            'x' => round($cx + cos($a) * ($radius + 16), 1), 'y' => round($cy + sin($a) * ($radius + 16), 1), 'angle' => 0, 'shape' => 'circle', 'status' => 'active',
                            'seat_uid' => $section->generateSeatUid($label, (string) $n),
                        ]);
                    }
                }
            }
            $this->line('  Club Zenit: layout #' . $layout->id);
            return $layout;
        });
    }

    private function seedTables(Tenant $tenant, Event $ev, SeatingLayout $layout, MarketplaceEventSeatingService $svc): void
    {
        if ($this->has('events', 'seating_layout_id')) {
            $ev->forceFill(['seating_layout_id' => $layout->id])->saveQuietly();
        }
        $sections = SeatingSection::withoutGlobalScopes()->where('layout_id', $layout->id)->get()->keyBy('name');
        $txt = [
            'Mese VIP' => ['Loc la masă VIP', 'VIP table seat', 'Loc la una dintre cele 6 mese de lângă ring, cu chelner la masă.', 'A seat at one of the 6 tables by the dance floor, with table service.', ['Chelner la masă', 'Intrare separată'], ['Table service', 'Separate entrance']],
            'Mese Gold' => ['Loc la masă Gold', 'Gold table seat', 'Loc la una dintre cele 3 mese mari din balconul Gold, cu o sticlă de spumant la masă.', 'A seat at one of the 3 large tables on the Gold balcony, with a bottle of bubbly per table.', ['Sticlă de spumant la masă', 'Vedere peste ring', 'Garderobă inclusă'], ['Bottle of bubbly per table', 'View over the floor', 'Cloakroom included']],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($this->clubPlan() as $i => [$secName, $code, $price, $color, , , , , $tables, $per]) {
            PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'CSTF-' . $code)->first()
                ?? PriceTier::create(['tenant_id' => $tenant->id, 'name' => $secName, 'tier_code' => 'CSTF-' . $code, 'currency' => 'RON',
                    'price' => $price, 'color' => $color, 'is_active' => true, 'sort_order' => 10 + $i]);
            [$ro, $en, $dRo, $dEn, $pRo, $pEn] = $txt[$secName];
            $sku = 'CSTF-' . strtoupper(substr(md5($ev->slug), 0, 6)) . '-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $ro; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = $tables * $per;
            foreach ($this->only('ticket_types', ['description' => $dRo, 'perks' => $pRo, 'sort_order' => 10 + $i, 'min_per_order' => 1, 'max_per_order' => 8, 'color' => $color,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en, 'description' => $dEn, 'perks' => $pEn]]])]) as $c => $v) {
                $tt->{$c} = $v;
            }
            $tt->save();
            if (isset($sections[$secName])) {
                $tt->seatingSections()->sync([$sections[$secName]->id]);
            }
        }
        $es = $svc->getOrCreateEventSeatingByEventId($ev->id);
        if (! $es) {
            $this->warn('  harta cu mese nu s-a putut genera pentru ' . $ev->slug);
            return;
        }
        $tiers = PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'like', 'CSTF-%')->get()->keyBy('name');
        foreach ($tiers as $name => $tier) {
            EventSeat::where('event_seating_id', $es->id)->where('section_name', $name)->update(['price_tier_id' => $tier->id]);
        }
        // Câteva mese deja ocupate întregi (grupuri), ca harta să arate vie
        if (! EventSeat::where('event_seating_id', $es->id)->where('status', 'sold')->exists()) {
            EventSeat::where('event_seating_id', $es->id)->whereIn('row_label', ['Masa 2', 'Masa 5', 'Masa G1'])->update(['status' => 'sold']);
        }
        $this->line('  mese pe hartă: ' . $ev->slug . ' (event_seating #' . $es->id . ')');
    }

    /* ---------------------------------------------------------------- ediții */

    private function seedParty(Tenant $tenant, array $c, int $i, Venue $venue, array $djs): Event
    {
        $ev = Event::where('slug', $c['slug'])->first();
        if ($ev && (int) $ev->tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('slug-ul aparține altui tenant');
        }
        $date = now()->addDays($c['days'])->toDateString();
        [, $vRo, $vEn, $city] = $this->venueDefs()[$c['venue']];
        $cityEn = ['București' => 'Bucharest'][$city] ?? $city;
        [$thRo, $thEn] = $c['theme'];
        $start = ! empty($c['late']) ? '21:00' : '22:00';
        $photos = self::PHOTOS;
        $cover = $photos[$i % count($photos)];
        // Galeria: 8 fotografii pentru edițiile trecute (pagina ediției și galeria de pe prima pagină)
        $gallery = ! empty($c['past']) ? array_map(fn ($k) => self::IMG . $photos[($i * 3 + $k) % count($photos)] . '?auto=format&fit=crop&w=1400&q=80', range(0, 7)) : [];
        $names = array_map(fn ($k) => $djs[$k]->name ?? $k, $c['djs']);
        $payload = $this->only('events', [
            'tenant_id' => $tenant->id, 'slug' => $c['slug'], 'venue_id' => $venue->id,
            'title' => ['ro' => self::NAME . ': ' . $thRo . ' · ' . $city, 'en' => self::NAME . ': ' . $thEn . ' · ' . $cityEn],
            'short_description' => [
                'ro' => 'O noapte cu hiturile anilor ’90 și 2000, ediția ' . $thRo . ', la ' . $vRo . '. La pupitru: ' . implode(', ', $names) . '.',
                'en' => 'A night of ’90s and 2000s hits, the ' . $thEn . ' edition, at ' . $vEn . '. On the decks: ' . implode(', ', $names) . '.',
            ],
            'description' => [
                'ro' => '<p>Refrenele pe care le știi de pe casete, de la radio și din clipurile de la TV, toată noaptea. Dress code: orice ai fi purtat în ' . (str_contains($thRo, '2000') ? '2003' : '1997') . '.</p>'
                    . '<p>Accesul e permis de la 18 ani, cu actul de identitate. Biletele sunt electronice, de pe telefon sau tipărite.</p>'
                    . '<p><em>Petrecere demonstrativă: brandul, DJ-ii și datele sunt inventate, iar plata trece printr-un procesator de test.</em></p>',
                'en' => '<p>The choruses you know from tapes, radio and TV music videos, all night long. Dress code: whatever you would have worn in ' . (str_contains($thEn, '2000') ? '2003' : '1997') . '.</p>'
                    . '<p>Entry from 18, with ID. Tickets are electronic, on your phone or printed.</p>'
                    . '<p><em>Demo party: the brand, the DJs and the dates are made up, and payments go through a test processor.</em></p>',
            ],
            'is_published' => true, 'is_cancelled' => false, 'is_sold_out' => ! empty($c['soldout']), 'is_indoor' => empty($c['external']), 'is_kid_friendly' => false,
            'duration_mode' => 'single_day', 'event_date' => $date,
            'door_time' => $start, 'start_time' => $start, 'end_time' => '05:00',
            'poster_url' => self::IMG . $cover . '?auto=format&fit=crop&w=1200&q=80',
            'hero_image_url' => self::IMG . $cover . '?auto=format&fit=crop&w=2000&q=80',
            'gallery' => $gallery,
            'commission_rate' => 2.00, 'commission_mode' => 'included',
            'external_ticket_url' => $c['external'][0] ?? null,
            'external_ticket_label' => $c['external'][1] ?? null,
        ]);
        $ev ? $ev->update($payload) : ($ev = Event::create($payload));
        $sync = [];
        foreach ($c['djs'] as $k => $key) {
            if (isset($djs[$key])) { $sync[$djs[$key]->id] = ['role_in_event' => 'dj', 'sort_order' => $k]; }
        }
        $ev->tenantArtists()->sync($sync);
        return $ev;
    }

    /** Valurile de preț: early bird, valul 2, valul 3 (se deschide mai târziu), pachet pentru 4. */
    private function seedTiers(Event $ev, array $c): void
    {
        $p = (int) $c['price'];
        $cap = (int) $c['cap'];
        [$s1, $s2, $s3] = $c['sold'];
        $soldOut = ! empty($c['soldout']);
        $defs = [
            ['EB', 'Early bird', 'Early bird', $p, (int) round($cap * 0.25), $s1, 'Primele bilete, la cel mai mic preț.', 'The first tickets, at the lowest price.', null],
            ['V2', 'Valul 2', 'Wave 2', $p + 20, (int) round($cap * 0.35), $s2, 'Se deschide după early bird.', 'Opens after early bird.', null],
            ['V3', 'Valul 3', 'Wave 3', $p + 40, (int) round($cap * 0.3), $s3, 'Ultimul val, până se umple clubul.', 'The last wave, until the club is full.', $s2 >= 0.97 || $soldOut ? null : now()->addDays(max(2, (int) ($c['days'] / 2)))],
            ['GRUP', 'Pachet 4 prieteni', '4 friends pack', ($p + 20) * 4 - 40, 40, $soldOut ? 1 : 0.4, 'Patru intrări pe un singur bilet, cu 40 de lei mai ieftin decât patru bilete din valul 2.', 'Four entries on a single ticket, 40 lei cheaper than four wave 2 tickets.', null],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($defs as $i => [$code, $ro, $en, $price, $capacity, $share, $dRo, $dEn, $opens]) {
            $sku = 'CSTF-' . strtoupper(substr(md5($ev->slug), 0, 6)) . '-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $ro; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = max(1, $capacity);
            foreach ($this->only('ticket_types', ['description' => $dRo, 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => $code === 'GRUP' ? 3 : 10,
                'ticket_group' => $code === 'GRUP' ? 'Grupuri' : 'Valuri',
                'sales_start_at' => $opens,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en, 'description' => $dEn, 'group' => $code === 'GRUP' ? 'Groups' : 'Waves']]])]) as $col => $v) {
                $tt->{$col} = $v;
            }
            if ($this->has('ticket_types', 'quota_sold')) {
                $tt->quota_sold = $soldOut ? $tt->capacity : min($tt->capacity, (int) round($tt->capacity * $share));
            }
            $tt->save();
        }
    }

    private function seedCoupon(Tenant $tenant): void
    {
        if (! Schema::hasTable('coupon_codes') || CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'RETRO10')->exists()) {
            return;
        }
        CouponCode::create($this->only('coupon_codes', ['tenant_id' => $tenant->id, 'code' => 'RETRO10', 'code_type' => 'multi_use',
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
