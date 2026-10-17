<?php

namespace App\Console\Commands;

use App\Enums\TenantType;
use App\Models\Artist;
use App\Models\ArtistGenre;
use App\Models\ArtistType;
use App\Models\Coupon\CouponCode;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Event;
use App\Models\FestivalDay;
use App\Models\FestivalLineupSlot;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tenantul demo pentru verticala FESTIVAL (template-ul festival, demo-festival.tics.ro):
 * „Echo Valley Festival”, trei zile, trei scene, 33 de apariții, abonamente cu faze
 * de preț, bilete de o zi, camping și parcare. Totul inventat, în română și engleză.
 *
 *   php artisan demo:seed-festival
 *
 * Folosește modulul festival existent (stages, festival_days, festival_lineup_slots);
 * biletele sunt tipuri de bilet obișnuite (coș, plată, scanare comune). Idempotent
 * după slug / email / domeniu, pur aditiv, nu atinge alți tenanți. Artiștii demo sunt
 * inactivi și fără marketplace, deci nu apar în listele publice ale marketplace-urilor.
 * Datele sunt relative la ziua rulării (festivalul începe într-o vineri, peste ~10 săptămâni).
 */
class DemoFestivalSeed extends Command
{
    protected $signature = 'demo:seed-festival
        {--domain=demo-festival.tics.ro : Hostname-ul demo-ului}
        {--owner-email=demo-festival@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului (goală = generată la creare / păstrată la re-rulare)}';

    protected $description = 'Seed demo complet pentru verticala festival (Echo Valley Festival) + demo-ul live demo-festival.tics.ro';

    private const NAME = 'Echo Valley Festival';
    private const TENANT_SLUG = 'demo-festival';
    private const EVENT_SLUG = 'echo-valley-festival-demo';
    private const IMG = 'https://images.unsplash.com/photo-';

    private array $colCache = [];

    public function handle(): int
    {
        $domain = strtolower(trim((string) $this->option('domain')));
        $ownerEmail = strtolower(trim((string) $this->option('owner-email')));

        $this->info('Seed demo festival — ' . self::NAME);

        try {
            $existingTenant = Tenant::where('slug', self::TENANT_SLUG)->first();
            $foreign = Domain::where('domain', $domain)->first();
            if ($foreign && (! $existingTenant || (int) $foreign->tenant_id !== (int) $existingTenant->id)) {
                $this->error("Domeniul {$domain} e legat de tenantul #{$foreign->tenant_id}. Oprit, nu am modificat nimic.");
                return self::FAILURE;
            }
            [$user, $passwordShown] = $this->seedOwner($ownerEmail, (string) ($this->option('owner-password') ?? ''));
            $tenant = $this->seedTenant($user, $ownerEmail, $domain);
            $this->seedDomain($tenant, $domain);
        } catch (\Throwable $e) {
            $this->error('Eroare la owner/tenant/domeniu: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        $this->guarded('client demo', fn () => $this->seedCustomer($tenant, 'demo@' . $domain, 'demo1234'));

        $venue = null;
        $this->guarded('locație', function () use ($tenant, &$venue) { $venue = $this->seedVenue($tenant); });

        $start = now()->startOfDay()->next('Friday')->addWeeks(9);
        $event = null;
        $this->guarded('eveniment', function () use ($tenant, $venue, $start, &$event) { $event = $this->seedEvent($tenant, $venue, $start); });
        if (! $event) {
            $this->error('Evenimentul nu a putut fi creat; opresc.');
            return self::FAILURE;
        }

        $artists = [];
        $this->guarded('artiști', function () use (&$artists) { $artists = $this->seedArtists(); });
        $this->guarded('artiști pe eveniment', fn () => $this->attachArtists($event, $artists));

        $stages = [];
        $this->guarded('scene', function () use ($tenant, $venue, &$stages) { $stages = $this->seedStages($tenant, $venue); });
        $days = [];
        $this->guarded('zile', function () use ($tenant, $event, $start, &$days) { $days = $this->seedDays($tenant, $event, $start); });
        $this->guarded('lineup', fn () => $this->seedLineup($tenant, $days, $stages, $artists));
        $this->guarded('bilete', fn () => $this->seedTickets($event, $start));
        $this->guarded('cod de reducere', fn () => $this->seedCoupon($tenant));

        $this->guarded('cache', function () use ($tenant, $domain) {
            Cache::forget("domain_tenant_{$domain}");
            Cache::forget("tenant_{$tenant->id}");
        });

        $this->guarded('demo live', function () use ($tenant, $domain) {
            $base = (string) config('tenant-sites.demo_base_domain', 'tics.ro');
            if (preg_match('/^demo-([a-z0-9-]+)\.' . preg_quote($base, '/') . '$/', $domain, $m)) {
                $this->call('demo:site', [
                    'template' => 'festival', 'tenant' => $tenant->id, 'slug' => $m[1],
                    '--title' => 'Festival de muzică',
                    '--description' => 'Mai multe zile și scene: lineup pe zile, program orar, abonamente cu faze de preț, camping.',
                    '--sort' => 5,
                ]);
            }
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant ID:    ' . $tenant->id . ' (slug ' . $tenant->slug . ')');
        $this->line('  Owner:        ' . $ownerEmail . ' / ' . $passwordShown);
        $this->line('  Site:         https://' . $domain . '/');
        $this->line('  Client demo:  demo@' . $domain . ' / demo1234');
        $this->line('  Eveniment:    #' . $event->id . ' ' . $event->slug . ' (' . $start->toDateString() . ' → ' . $start->copy()->addDays(2)->toDateString() . ')');
        $this->line('  Lineup API:   https://core.tixello.com/api/tenant-client/events/' . $event->slug . '/lineup?tenant=' . $tenant->id);
        $this->line('  Cod reducere: ECHO10');

        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- owner, tenant */

    private function seedOwner(string $email, string $passwordOpt): array
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            if ($passwordOpt !== '') {
                $user->password = Hash::make($passwordOpt);
                $user->save();
            }
            return [$user, $passwordOpt !== '' ? $passwordOpt : '(neschimbată)'];
        }
        $password = $passwordOpt !== '' ? $passwordOpt : Str::random(14);
        $user = User::create($this->only('users', ['name' => self::NAME, 'email' => $email, 'password' => Hash::make($password), 'role' => 'tenant']));
        if ($this->has('users', 'email_verified_at')) {
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        }
        return [$user, $password];
    }

    private function seedTenant(User $user, string $ownerEmail, string $domain): Tenant
    {
        $tenant = Tenant::where('slug', self::TENANT_SLUG)->first();
        $domainTaken = Tenant::where('domain', $domain)->when($tenant, fn ($q) => $q->where('id', '!=', $tenant->id))->exists();
        $data = [
            'name' => self::NAME, 'public_name' => self::NAME, 'company_name' => self::NAME,
            'owner_id' => $user->id, 'slug' => self::TENANT_SLUG, 'status' => 'active',
            'tenant_type' => TenantType::Festival,
            'plan' => '2percent', 'work_method' => 'mixed', 'commission_rate' => 2.00, 'commission_mode' => 'included',
            'locale' => 'ro', 'currency' => 'RON', 'country' => 'RO', 'city' => 'Râșnov',
            'contact_email' => $ownerEmail, 'has_own_website' => false, 'use_core_smtp' => true,
            'onboarding_completed' => true, 'billing_cycle_days' => 30,
        ];
        if (! $domainTaken) {
            $data['domain'] = $domain;
        }
        $settings = ['site_title' => self::NAME, 'site_language' => 'ro', 'storefront' => ['order_emails' => true, 'brand_dark' => '#0B0A1A', 'brand_color' => '#FF5A36']];
        if (! $tenant) {
            $data['ticket_series_prefix'] = 'ECHO';
            $data['settings'] = $settings + ['panel' => ['simple_menu' => true]];
            $data['onboarding_completed_at'] = now();
            $data['billing_starts_at'] = now();
            $data['next_billing_date'] = now()->addDays(30)->toDateString();
            if ($domainTaken) {
                $data['domain'] = self::TENANT_SLUG . '.' . $domain;
            }
            $tenant = Tenant::create($this->only('tenants', $data));
            $this->line("  tenant creat: #{$tenant->id}");
            return $tenant;
        }
        $data['settings'] = array_merge($settings, is_array($tenant->settings) ? $tenant->settings : []);
        $tenant->update($this->only('tenants', $data));
        $this->line("  tenant existent: #{$tenant->id}");
        return $tenant;
    }

    private function seedDomain(Tenant $tenant, string $domain): void
    {
        $data = $this->only('domains', ['tenant_id' => $tenant->id, 'domain' => $domain, 'is_active' => true, 'is_suspended' => false, 'is_primary' => true]);
        $row = Domain::where('domain', $domain)->first();
        $row ? $row->update($data) : ($row = Domain::create($data));
        if ($this->has('domains', 'activated_at') && empty($row->activated_at)) {
            $row->forceFill(['activated_at' => now()])->saveQuietly();
        }
    }

    private function seedCustomer(Tenant $tenant, string $email, string $password): void
    {
        $c = Customer::withTrashed()->where('tenant_id', $tenant->id)->where('email', $email)->first();
        $data = $this->only('customers', ['tenant_id' => $tenant->id, 'primary_tenant_id' => $tenant->id, 'email' => $email,
            'first_name' => 'Client', 'last_name' => 'Demo', 'phone' => '0700000000', 'password' => Hash::make($password)]);
        if ($c) {
            if ($c->trashed()) {
                $c->restore();
            }
            $c->update($data);
        } else {
            $c = Customer::create($data);
        }
        try {
            $c->tenants()->syncWithoutDetaching([$tenant->id]);
        } catch (\Throwable $e) {
        }
    }

    private function seedVenue(Tenant $tenant): Venue
    {
        $slug = 'domeniul-valea-ecourilor-demo-festival';
        $venue = Venue::where('slug', $slug)->first();
        $data = $this->only('venues', [
            'tenant_id' => $tenant->id,
            'name' => ['ro' => 'Domeniul Valea Ecourilor', 'en' => 'Echo Valley Estate'],
            'slug' => $slug, 'city' => 'Râșnov', 'state' => 'Brașov', 'address' => 'DN73A, Râșnov, Brașov',
            'latitude' => 45.5926, 'longitude' => 25.4632,
        ]);
        $venue ? $venue->update($data) : ($venue = Venue::create($data));
        return $venue;
    }

    /* ---------------------------------------------------------------- eveniment */

    private function seedEvent(Tenant $tenant, ?Venue $venue, \Carbon\Carbon $start): Event
    {
        $event = Event::where('slug', self::EVENT_SLUG)->first();
        if ($event && (int) $event->tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('slug-ul evenimentului aparține altui tenant');
        }
        $year = $start->year;
        $ro = '<p>Trei zile și trei nopți într-o vale dintre munți: muzică live pe Scena Lunii, set-uri electronice până dimineața în Hangar și concerte intime în Pădure.</p>'
            . '<p>Campingul e la cinci minute de scene, iar navetele pleacă din Brașov la fiecare oră. Biletele se cumpără online și ajung pe email, cu cod QR.</p>'
            . '<p><em>Festival demonstrativ: artiștii, scenele și programul sunt inventate, iar plata trece printr-un procesator de test.</em></p>';
        $en = '<p>Three days and nights in a valley between mountains: live music on the Moon Stage, electronic sets until sunrise in the Hangar and intimate shows in the Forest.</p>'
            . '<p>The campsite is five minutes from the stages and shuttles leave Brașov every hour. Tickets are sold online and arrive by email with a QR code.</p>'
            . '<p><em>Demo festival: the artists, stages and schedule are made up, and payments go through a test processor.</em></p>';
        $gallery = array_map(fn ($id) => self::IMG . $id . '?auto=format&fit=crop&w=1600&q=80', [
            '1470229722913-7c0e2dbbafd3', '1506157786151-b8491531f063', '1533174072545-7a4b6ad7a6c3', '1492684223066-81342ee5ff30',
            '1514525253161-7a46d19cd819', '1501386761578-eac5c94b800a', '1540039155733-5bb30b53aa14', '1505236858219-8359eb29e329',
        ]);
        $payload = $this->only('events', [
            'tenant_id' => $tenant->id,
            'title' => ['ro' => self::NAME . ' ' . $year, 'en' => self::NAME . ' ' . $year],
            'subtitle' => ['ro' => 'Trei zile, trei scene, o vale', 'en' => 'Three days, three stages, one valley'],
            'slug' => self::EVENT_SLUG,
            'venue_id' => $venue?->id,
            'is_published' => true, 'is_cancelled' => false, 'is_sold_out' => false,
            'short_description' => ['ro' => 'Trei zile de muzică live și electronică într-o vale de lângă Brașov, cu camping la cinci minute de scene.',
                'en' => 'Three days of live and electronic music in a valley near Brașov, with camping five minutes from the stages.'],
            'description' => ['ro' => $ro, 'en' => $en],
            'duration_mode' => 'range',
            'range_start_date' => $start->toDateString(),
            'range_end_date' => $start->copy()->addDays(2)->toDateString(),
            'range_start_time' => '16:00', 'range_end_time' => '04:00',
            'is_indoor' => false, 'is_kid_friendly' => false,
            'commission_rate' => 2.00, 'commission_mode' => 'included',
            'poster_url' => self::IMG . '1470229722913-7c0e2dbbafd3?auto=format&fit=crop&w=1200&q=80',
            'hero_image_url' => self::IMG . '1506157786151-b8491531f063?auto=format&fit=crop&w=2000&q=80',
            'gallery' => $gallery,
        ]);
        $event ? $event->update($payload) : ($event = Event::create($payload));
        $this->line("  eveniment: #{$event->id} " . self::EVENT_SLUG);
        return $event;
    }

    /* ---------------------------------------------------------------- artiști */

    /** [slug-suffix, nume, gen (slug-uri existente), țară, imagine, bio ro, bio en] */
    private function artistDefs(): array
    {
        return [
            // Headlineri (Scena Lunii)
            ['nova-saint', 'Nova Saint', ['pop', 'electronic'], 'UK', '1493225457124-a3eb161ffa5f', 'Pop electronic cu refrene care se cântă în cor de zeci de mii de oameni.', 'Electronic pop with choruses sung back by tens of thousands.'],
            ['velvet-harbour', 'Velvet Harbour', ['rock', 'indie'], 'IE', '1598387993441-a364f854c3e1', 'Rock de stadion cu chitare late și o secție ritmică impecabilă.', 'Stadium rock with wide guitars and a flawless rhythm section.'],
            ['kairo-lux', 'Kairo Lux', ['hip-hop'], 'FR', '1501281668745-f7f57925c3b4', 'Hip-hop cinematic, cu producție orchestrală și un show de lumini memorabil.', 'Cinematic hip-hop with orchestral production and a memorable light show.'],
            // Scena Lunii, restul
            ['ana-mirea', 'Ana Mirea', ['pop'], 'RO', '1524650359799-842906ca1c06', 'Cantautoare care trece natural de la folk la pop, cu o voce pe care o recunoști din prima.', 'A singer-songwriter moving naturally from folk to pop, with a voice you know at once.'],
            ['midnight-atlas', 'Midnight Atlas', ['indie', 'rock'], 'SE', '1499364615650-ec38552f4f34', 'Indie rock nordic, melancolic și luminos în același timp.', 'Nordic indie rock, melancholic and bright at the same time.'],
            ['polar-tides', 'Polar Tides', ['electronic'], 'NO', '1516450360452-9312f5e86fc7', 'Live electronic cu sintetizatoare analogice și tobe acustice.', 'Live electronic act with analogue synths and acoustic drums.'],
            ['lupii-de-campie', 'Lupii de Câmpie', ['rock'], 'RO', '1464375117522-1311d6a5b81f', 'Rock românesc cu energie de concert de club, pe o scenă mare.', 'Romanian rock with club-show energy on a big stage.'],
            ['mira-and-the-wolves', 'Mira & The Wolves', ['indie'], 'PL', '1415201364774-f6f0bb35f28f', 'Indie cu suflători, ritmuri de soul și multă bună dispoziție.', 'Indie with horns, soul grooves and plenty of good mood.'],
            ['electric-orchard', 'Electric Orchard', ['electronic', 'pop'], 'NL', '1492684223066-81342ee5ff30', 'Synth-pop luminos, făcut pentru apus.', 'Bright synth-pop made for sunset.'],
            ['static-bloom', 'Static Bloom', ['rock'], 'US', '1459749411175-04bf5292ceea', 'Shoegaze zgomotos și visător, cu pereți de chitare.', 'Loud, dreamy shoegaze with walls of guitars.'],
            ['sora-blue', 'Sora Blue', ['pop'], 'JP', '1514525253161-7a46d19cd819', 'Pop japonez cu influențe de city pop și R&B.', 'Japanese pop with city pop and R&B influences.'],
            ['twin-fjords', 'Twin Fjords', ['indie'], 'DK', '1520523839897-bd0b52f945a0', 'Duo cu pian și voci în armonie, pentru serile liniștite.', 'A piano-and-vocals duo for quiet evenings.'],
            // Hangar (electronic)
            ['deep-atlas', 'Deep Atlas', ['electronic'], 'DE', '1545128485-c400e7702796', 'Techno hipnotic, set-uri lungi, fără pauză.', 'Hypnotic techno, long sets, no breaks.'],
            ['neon-monks', 'Neon Monks', ['electronic'], 'BE', '1574391884720-bbc3740c59d1', 'House cu vocale soul și linii de bas groase.', 'House with soul vocals and thick basslines.'],
            ['lena-kova', 'Lena Kova', ['electronic'], 'CZ', '1540039155733-5bb30b53aa14', 'Melodic techno, de la primele ore ale nopții până la răsărit.', 'Melodic techno from the first hours of the night to sunrise.'],
            ['tape-ghosts', 'Tape Ghosts', ['electronic'], 'UK', '1468164016595-6108e4c60c8b', 'Breakbeat și jungle mixat pe viniluri.', 'Breakbeat and jungle mixed on vinyl.'],
            ['viza-nordica', 'Viza Nordică', ['electronic'], 'RO', '1533174072545-7a4b6ad7a6c3', 'Minimal românesc, cu groove-uri lungi și rafinate.', 'Romanian minimal, with long and refined grooves.'],
            ['okra-collective', 'Okra Collective', ['electronic'], 'PT', '1505236858219-8359eb29e329', 'Afro house cu percuție live.', 'Afro house with live percussion.'],
            ['ruby-rails', 'Ruby Rails', ['electronic'], 'AU', '1514320291840-2e0a9bf2a9ae', 'Disco și edits, pentru orele de dinaintea dimineții.', 'Disco and edits for the hours before morning.'],
            ['sundial', 'Sundial', ['electronic'], 'ES', '1429962714451-bb934ecdc4ec', 'Progressive house cu o mare dragoste pentru melodie.', 'Progressive house with a big love for melody.'],
            ['arctic-bloom', 'Arctic Bloom', ['electronic'], 'FI', '1524368535928-5b5e00ddc76b', 'Trance modern, emoțional, cu momente de euforie.', 'Modern, emotional trance with moments of euphoria.'],
            ['dara-vale', 'Dara Vale', ['electronic'], 'IT', '1478147427282-58a87a120781', 'Deep house cald, perfect pentru începutul nopții.', 'Warm deep house, perfect for the start of the night.'],
            // Pădurea (acustic, indie)
            ['hollow-pines', 'Hollow Pines', ['folk', 'indie'], 'CA', '1485579149621-3123dd979885', 'Folk cu armonii vocale și chitări acustice.', 'Folk with vocal harmonies and acoustic guitars.'],
            ['ion-vantu', 'Ion Vântu', ['folk'], 'RO', '1511671782779-c97d3d27a1d4', 'Folk românesc cu povești de drum și o chitară veche.', 'Romanian folk with road stories and an old guitar.'],
            ['kites-over-delta', 'Kites over Delta', ['indie'], 'RO', '1508700115892-45ecd05ae2ad', 'Indie pop din Tulcea, cu refrene calde și ironice.', 'Indie pop from Tulcea, with warm and witty choruses.'],
            ['glass-meadow', 'Glass Meadow', ['folk'], 'NZ', '1487180144351-b8472da7d491', 'Bedroom folk, liniștit și foarte personal.', 'Quiet, very personal bedroom folk.'],
            ['marta-solano', 'Marta Solano', ['jazz'], 'ES', '1501386761578-eac5c94b800a', 'Jazz vocal cu influențe de bossa nova.', 'Vocal jazz with bossa nova influences.'],
            ['the-quiet-fires', 'The Quiet Fires', ['indie', 'folk'], 'US', '1470229722913-7c0e2dbbafd3', 'Americana cu vioară și slide guitar.', 'Americana with fiddle and slide guitar.'],
            ['luna-park-trio', 'Luna Park Trio', ['jazz'], 'RO', '1506157786151-b8491531f063', 'Trio de jazz modern: pian, contrabas, tobe.', 'Modern jazz trio: piano, double bass, drums.'],
        ];
    }

    /** @return array<string, Artist> */
    private function seedArtists(): array
    {
        $genres = ArtistGenre::pluck('id', 'slug');
        $typeBand = ArtistType::where('slug', 'band')->value('id');
        $typeDj = ArtistType::where('slug', 'dj')->value('id');
        $typeSolo = ArtistType::where('slug', 'solo-artist')->value('id');
        $out = [];
        foreach ($this->artistDefs() as $i => [$key, $name, $g, $country, $img, $ro, $en]) {
            $slug = 'demo-echo-' . $key;
            $artist = Artist::where('slug', $slug)->first() ?? new Artist(['slug' => $slug]);
            $artist->fill($this->only('artists', [
                'name' => $name,
                'country' => $country,
                'bio_html' => ['ro' => '<p>' . $ro . '</p>', 'en' => '<p>' . $en . '</p>'],
                'main_image_url' => self::IMG . $img . '?auto=format&fit=crop&w=1000&h=1000&q=80',
                'portrait_url' => self::IMG . $img . '?auto=format&fit=crop&w=800&h=1100&q=80',
                // Demo: inactiv și fără marketplace, ca să nu apară în listele publice
                'is_active' => false,
                'marketplace_client_id' => null,
            ]));
            $artist->save();
            try {
                $ids = collect($g)->map(fn ($s) => $genres[$s] ?? null)->filter()->values()->all();
                if ($ids) {
                    $artist->artistGenres()->syncWithoutDetaching($ids);
                }
                $type = $i >= 12 && $i < 22 ? $typeDj : (in_array($key, ['ana-mirea', 'sora-blue', 'ion-vantu', 'marta-solano', 'kairo-lux', 'nova-saint'], true) ? $typeSolo : $typeBand);
                if ($type) {
                    $artist->artistTypes()->syncWithoutDetaching([$type]);
                }
            } catch (\Throwable $e) {
            }
            $out[$key] = $artist;
        }
        $this->line('  artiști: ' . count($out));
        return $out;
    }

    private function attachArtists(Event $event, array $artists): void
    {
        $sync = [];
        $pos = 0;
        foreach ($artists as $key => $a) {
            $sync[$a->id] = $this->onlyPivot(['sort_order' => $pos, 'is_headliner' => $pos < 3, 'is_co_headliner' => $pos >= 3 && $pos < 6]);
            $pos++;
        }
        $event->artists()->sync($sync);
    }

    private function onlyPivot(array $data): array
    {
        return array_intersect_key($data, array_flip($this->cols('event_artist')));
    }

    /* ---------------------------------------------------------------- scene, zile, lineup */

    /** @return array<string, Stage> */
    private function seedStages(Tenant $tenant, ?Venue $venue): array
    {
        $defs = [
            'luna' => ['Scena Lunii', 'Moon Stage', 'main', 25000, '#FF5A36', 'Scena principală, deschisă spre vale. Aici cântă headlinerii.', 'The main stage, open to the valley. Home of the headliners.'],
            'hangar' => ['Hangar', 'Hangar', 'dj', 4000, '#7B5CFF', 'Fost hangar de avioane, acum club: electronic de la 20:00 până la răsărit.', 'A former aircraft hangar turned club: electronic from 20:00 until sunrise.'],
            'padurea' => ['Pădurea', 'The Forest', 'acoustic', 1500, '#2FBF71', 'Scena mică dintre brazi, pentru concerte acustice și jazz.', 'The small stage among the pines, for acoustic shows and jazz.'],
        ];
        $out = [];
        $sort = 0;
        foreach ($defs as $key => [$ro, $en, $type, $cap, $color, $dRo, $dEn]) {
            $stage = Stage::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'echo-' . $key],
                ['venue_id' => $venue?->id, 'name' => $ro, 'description' => $dRo, 'stage_type' => $type, 'capacity' => $cap,
                    'is_active' => true, 'sort_order' => $sort++,
                    'meta' => ['color' => $color, 'i18n' => ['en' => ['name' => $en, 'description' => $dEn]]]]
            );
            $out[$key] = $stage;
        }
        return $out;
    }

    /** @return FestivalDay[] */
    private function seedDays(Tenant $tenant, Event $event, \Carbon\Carbon $start): array
    {
        // Zilele vechi (de la o rulare anterioară, cu alte date) se șterg: lineup-ul se refac mai jos
        FestivalDay::where('tenant_id', $tenant->id)->where('event_id', $event->id)
            ->whereNotIn('date', [$start->toDateString(), $start->copy()->addDay()->toDateString(), $start->copy()->addDays(2)->toDateString()])
            ->delete();
        $names = [['Vineri', 'Friday'], ['Sâmbătă', 'Saturday'], ['Duminică', 'Sunday']];
        $out = [];
        foreach ($names as $i => [$ro, $en]) {
            $out[] = FestivalDay::updateOrCreate(
                ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'date' => $start->copy()->addDays($i)->toDateString()],
                ['name' => $ro, 'gates_open' => '15:00', 'gates_close' => '05:00', 'status' => 'scheduled', 'sort_order' => $i,
                    'meta' => ['i18n' => ['en' => ['name' => $en]]]]
            );
        }
        return $out;
    }

    private function seedLineup(Tenant $tenant, array $days, array $stages, array $artists): void
    {
        FestivalLineupSlot::whereIn('festival_day_id', collect($days)->pluck('id'))->delete();
        $plan = [
            // [zi, scenă, artist, start, end, tier]
            [0, 'luna', 'twin-fjords', '17:00', '17:50', 'support'], [0, 'luna', 'mira-and-the-wolves', '18:30', '19:30', 'regular'],
            [0, 'luna', 'midnight-atlas', '20:15', '21:30', 'sub_headliner'], [0, 'luna', 'nova-saint', '22:30', '00:15', 'headliner'],
            [0, 'hangar', 'dara-vale', '20:00', '22:00', 'regular'], [0, 'hangar', 'neon-monks', '22:00', '00:00', 'regular'],
            [0, 'hangar', 'deep-atlas', '00:00', '02:30', 'sub_headliner'], [0, 'hangar', 'ruby-rails', '02:30', '04:30', 'regular'],
            [0, 'padurea', 'glass-meadow', '16:30', '17:30', 'emerging'], [0, 'padurea', 'ion-vantu', '18:00', '19:00', 'regular'], [0, 'padurea', 'luna-park-trio', '19:30', '20:45', 'regular'],

            [1, 'luna', 'kites-over-delta', '17:00', '17:50', 'support'], [1, 'luna', 'ana-mirea', '18:30', '19:30', 'regular'],
            [1, 'luna', 'lupii-de-campie', '20:15', '21:30', 'sub_headliner'], [1, 'luna', 'velvet-harbour', '22:30', '00:15', 'headliner'],
            [1, 'hangar', 'viza-nordica', '20:00', '22:00', 'regular'], [1, 'hangar', 'okra-collective', '22:00', '00:00', 'regular'],
            [1, 'hangar', 'lena-kova', '00:00', '02:30', 'sub_headliner'], [1, 'hangar', 'tape-ghosts', '02:30', '04:30', 'regular'],
            [1, 'padurea', 'the-quiet-fires', '16:30', '17:30', 'regular'], [1, 'padurea', 'hollow-pines', '18:00', '19:00', 'regular'], [1, 'padurea', 'marta-solano', '19:30', '20:45', 'regular'],

            [2, 'luna', 'sora-blue', '17:00', '17:50', 'support'], [2, 'luna', 'static-bloom', '18:30', '19:30', 'regular'],
            [2, 'luna', 'electric-orchard', '20:15', '21:30', 'sub_headliner'], [2, 'luna', 'kairo-lux', '22:30', '00:15', 'headliner'],
            [2, 'hangar', 'sundial', '20:00', '22:00', 'regular'], [2, 'hangar', 'polar-tides', '22:00', '00:00', 'sub_headliner'],
            [2, 'hangar', 'arctic-bloom', '00:00', '02:30', 'regular'], [2, 'hangar', 'deep-atlas', '02:30', '04:30', 'regular'],
            [2, 'padurea', 'glass-meadow', '16:30', '17:30', 'emerging'], [2, 'padurea', 'twin-fjords', '18:00', '19:00', 'regular'], [2, 'padurea', 'luna-park-trio', '19:30', '20:45', 'regular'],
        ];
        $pos = 0;
        foreach ($plan as [$d, $s, $a, $from, $to, $tier]) {
            if (! isset($days[$d], $stages[$s], $artists[$a])) {
                continue;
            }
            FestivalLineupSlot::create([
                'tenant_id' => $tenant->id, 'festival_day_id' => $days[$d]->id, 'stage_id' => $stages[$s]->id,
                'artist_id' => $artists[$a]->id, 'start_time' => $from, 'end_time' => $to,
                'slot_type' => $s === 'hangar' ? 'dj_set' : 'performance',
                'is_headliner' => $tier === 'headliner', 'status' => 'confirmed',
                'display_position' => $pos++, 'display_tier' => $tier,
            ]);
        }
        $this->line('  apariții în lineup: ' . $pos);
    }

    /* ---------------------------------------------------------------- bilete */

    private function seedTickets(Event $event, \Carbon\Carbon $start): void
    {
        $abo = ['Abonamente', 'Passes'];
        $day = ['Bilete de o zi', 'Day tickets'];
        $extra = ['Camping și extra', 'Camping & extras'];
        $defs = [
            // [sku, nume ro, nume en, grup, preț, capacitate, vândute, descriere ro, en, perks ro, perks en, extra]
            ['ECHO-GA3-EB', 'Abonament 3 zile — Early Bird', '3-day pass — Early Bird', $abo, 349, 1500, 1500,
                'Prima fază de preț: acces toate cele trei zile, la toate scenele.', 'First price tier: access on all three days, to every stage.',
                ['Acces 3 zile, toate scenele', 'Brățară cu cip pentru plăți'], ['3-day access, every stage', 'Chip wristband for payments'], []],
            ['ECHO-GA3', 'Abonament 3 zile', '3-day pass', $abo, 429, 12000, 0,
                'Acces toate cele trei zile, la toate scenele. Faza a doua de preț.', 'Access on all three days, to every stage. Second price tier.',
                ['Acces 3 zile, toate scenele', 'Brățară cu cip pentru plăți', 'Reintrare oricând'], ['3-day access, every stage', 'Chip wristband for payments', 'Re-entry any time'], []],
            ['ECHO-VIP3', 'Abonament VIP 3 zile', 'VIP 3-day pass', $abo, 899, 800, 0,
                'Zonă VIP lângă Scena Lunii, intrare separată, baruri și toalete VIP.', 'VIP area by the Moon Stage, separate entrance, VIP bars and toilets.',
                ['Tot ce include abonamentul', 'Zonă VIP cu vedere la scenă', 'Intrare separată, fără coadă', 'Baruri și toalete VIP'], ['Everything in the pass', 'VIP area with stage view', 'Separate entrance, no queue', 'VIP bars and toilets'], []],
            ['ECHO-D1', 'Bilet Vineri', 'Friday ticket', $day, 199, 4000, 0,
                'Acces vineri, de la deschiderea porților până la închidere.', 'Friday access, from gates open until close.',
                ['Acces o zi, toate scenele'], ['One-day access, every stage'], ['valid_date' => $start->toDateString()]],
            ['ECHO-D2', 'Bilet Sâmbătă', 'Saturday ticket', $day, 219, 4000, 0,
                'Acces sâmbătă, de la deschiderea porților până la închidere.', 'Saturday access, from gates open until close.',
                ['Acces o zi, toate scenele'], ['One-day access, every stage'], ['valid_date' => $start->copy()->addDay()->toDateString()]],
            ['ECHO-D3', 'Bilet Duminică', 'Sunday ticket', $day, 199, 4000, 0,
                'Acces duminică, de la deschiderea porților până la închidere.', 'Sunday access, from gates open until close.',
                ['Acces o zi, toate scenele'], ['One-day access, every stage'], ['valid_date' => $start->copy()->addDays(2)->toDateString()]],
            ['ECHO-CAMP', 'Camping 3 nopți (cort propriu)', 'Camping, 3 nights (own tent)', $extra, 149, 3000, 0,
                'Loc de cort pentru o persoană, dușuri și toalete. Necesită și un abonament.', 'A tent pitch for one person, showers and toilets. Requires a pass.',
                ['Loc de cort, 3 nopți', 'Dușuri și toalete', 'Zonă păzită'], ['Tent pitch, 3 nights', 'Showers and toilets', 'Guarded area'], []],
            ['ECHO-GLAMP', 'Glamping (cort montat, 2 persoane)', 'Glamping (tent pitched, 2 people)', $extra, 1290, 120, 0,
                'Cort montat cu paturi, lumină și priză, pentru două persoane, toate cele trei nopți.', 'A pitched tent with beds, light and power for two people, all three nights.',
                ['Cort montat pentru 2', 'Paturi, lenjerie, priză', 'Dușuri dedicate'], ['Pitched tent for 2', 'Beds, linen, power', 'Dedicated showers'], []],
            ['ECHO-PARK', 'Parcare 3 zile', 'Parking, 3 days', $extra, 99, 1500, 0,
                'Loc de parcare pentru un autoturism, toate cele trei zile.', 'A parking space for one car, all three days.',
                ['Un autoturism, 3 zile'], ['One car, 3 days'], ['is_parking' => true]],
        ];
        $existing = TicketType::where('event_id', $event->id)->get()->keyBy('sku');
        foreach ($defs as $i => [$sku, $ro, $en, $group, $price, $cap, $sold, $dRo, $dEn, $pRo, $pEn, $more]) {
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $event->id]);
            $tt->event_id = $event->id;
            $tt->name = $ro;
            $tt->sku = $sku;
            $tt->price_max = $price;
            $tt->is_active = true;
            $tt->currency = 'RON';
            $tt->capacity = $cap;
            foreach ($this->only('ticket_types', array_merge([
                'description' => $dRo, 'ticket_group' => $group[0], 'perks' => $pRo, 'sort_order' => $i + 1,
                'min_per_order' => 1, 'max_per_order' => 10,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en, 'description' => $dEn, 'perks' => $pEn, 'group' => $group[1]]]]),
            ], $more)) as $col => $val) {
                $tt->{$col} = $val;
            }
            // Early Bird epuizat: arată fazele de preț (următoarea fază e deja în vânzare)
            if ($this->has('ticket_types', 'quota_sold')) {
                $tt->quota_sold = max((int) ($tt->quota_sold ?? 0), $sold);
            }
            $tt->save();
        }
        $this->line('  bilete: ' . count($defs));
    }

    private function seedCoupon(Tenant $tenant): void
    {
        if (! Schema::hasTable('coupon_codes') || CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'ECHO10')->exists()) {
            return;
        }
        CouponCode::create($this->only('coupon_codes', [
            'tenant_id' => $tenant->id, 'code' => 'ECHO10', 'code_type' => 'multi_use', 'discount_type' => 'percentage',
            'discount_value' => 10, 'status' => 'active', 'is_public' => false, 'combinable' => false, 'source' => 'demo',
        ]));
    }

    /* ---------------------------------------------------------------- utilitare */

    private function guarded(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->warn("  [{$label}] sărit: " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }

    private function cols(string $table): array
    {
        if (! array_key_exists($table, $this->colCache)) {
            try {
                $this->colCache[$table] = Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $this->colCache[$table] = [];
            }
        }
        return $this->colCache[$table];
    }

    private function has(string $table, string $column): bool
    {
        return in_array($column, $this->cols($table), true);
    }

    private function only(string $table, array $data): array
    {
        $cols = $this->cols($table);
        return empty($cols) ? $data : array_intersect_key($data, array_flip($cols));
    }
}
