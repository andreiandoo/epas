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
 * Tenantul demo pentru AGENȚIE DE ARTIȘTI (template-ul agency, demo-agentie.tics.ro):
 * „Contrapunct Artists”, o agenție de management și booking inventată: 14 artiști în roster
 * (solo, trupe, DJ, jazz, ansamblu de coarde) cu profil complet, 10 concerte produse de
 * agenție (unul pe locuri numerotate cu hartă, unul sold out, unul cu ultimele bilete,
 * unul co-headliner, unul la un festival cu biletele la organizator) și 4 trecute. RO + EN.
 *
 *   php artisan demo:seed-agency
 *
 * Idempotent după slug / email / domeniu, pur aditiv, nu atinge alți tenanți. Cere
 * migrarea tenant_booking_requests (cererile de booking ale formularului).
 */
class DemoAgencySeed extends Command
{
    protected $signature = 'demo:seed-agency
        {--domain=demo-agentie.tics.ro : Hostname-ul demo-ului}
        {--owner-email=demo-agentie@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului}';

    protected $description = 'Seed demo complet pentru o agenție de artiști (Contrapunct Artists) + demo-ul live demo-agentie.tics.ro';

    private const NAME = 'Contrapunct Artists';
    private const TENANT_SLUG = 'demo-agentie';
    private const IMG = 'https://images.unsplash.com/photo-';

    private array $colCache = [];

    public function handle(MarketplaceEventSeatingService $seating): int
    {
        if (! Schema::hasTable('tenant_booking_requests')) {
            $this->error('Lipsește tabela tenant_booking_requests: rulează întâi php artisan migrate.');
            return self::FAILURE;
        }
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
        $roster = [];
        $this->guarded('roster', function () use ($tenant, &$roster) { $roster = $this->seedRoster($tenant); });
        $venues = [];
        $this->guarded('locații', function () use ($tenant, &$venues) { $venues = $this->seedVenues($tenant); });
        $layout = null;
        $this->guarded('sala cu locuri', function () use ($tenant, $venues, &$layout) { $layout = $this->seedHall($tenant, $venues['iasi']); });

        $events = [];
        foreach ($this->shows() as $c) {
            $this->guarded('concert ' . $c['slug'], function () use ($tenant, $c, $venues, $layout, $seating, $roster, &$events) {
                $ev = $this->seedShow($tenant, $c, $venues[$c['venue']], $roster);
                if (! empty($c['external'])) {
                    // biletele le vinde organizatorul festivalului
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
                    'template' => 'agency', 'tenant' => $tenant->id, 'slug' => $m[1],
                    '--title' => 'Agenție de artiști',
                    '--description' => 'Management și booking: roster filtrabil, pagini de artist cu concerte și cerere de booking, evenimente cu bilete și hartă de locuri.',
                    '--sort' => 18,
                ]);
            }
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant:  #' . $tenant->id . ' ' . $tenant->slug . ' · owner ' . $ownerEmail . ' / ' . $pass);
        $this->line('  Site:    https://' . $domain . '/  · client demo@' . $domain . ' / demo1234 · cod CONTRA10 · ' . count($roster) . ' artiști');
        foreach ($events as $ev) {
            $this->line('  Concert: #' . $ev->id . ' ' . $ev->slug . ' (' . ($ev->event_date?->toDateString()) . ')');
        }
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- date */

    /**
     * Rosterul: [cheie, nume, tip, genuri ro, genuri en, oraș, țară, frază ro, frază en, teritorii ro, teritorii en,
     *            foto, recomandat, ascultători, bio ro, bio en, citat, sursa]
     */
    private function rosterDefs(): array
    {
        $ro = ['România', 'Republica Moldova']; $roEn = ['Romania', 'Moldova'];
        $eu = ['România', 'Europa']; $euEn = ['Romania', 'Europe'];
        return [
            ['mira-vale', 'Mira Vale', 'solo', ['pop', 'electro-pop'], ['pop', 'electro-pop'], 'Cluj-Napoca', 'RO', 'Pop luminos, cântat ca într-o confesiune.', 'Bright pop, sung like a confession.', $eu, $euEn, '1504898770365-14faca6a7320', true, 412000,
                'Mira Vale a început cu piese urcate noaptea pe internet și a ajuns pe scenele marilor festivaluri în doi ani. Al doilea album, „Fum roz”, a ieșit în primăvară.', 'Mira Vale started with songs posted online at night and reached the big festival stages in two years. Her second album, Pink Smoke, came out in spring.', 'Cea mai sigură voce nouă a pop-ului românesc.', 'Revista Sunet'],
            ['delta-noua', 'Delta Nouă', 'band', ['indie rock'], ['indie rock'], 'Timișoara', 'RO', 'Chitare mari, refrene pentru stadioane mici.', 'Big guitars, choruses for small stadiums.', $eu, $euEn, '1598387993441-a364f854c3e1', true, 188000,
                'Patru prieteni din Timișoara care cântă împreună din liceu. Concertele lor sunt cunoscute pentru energia de la primul acord.', 'Four friends from Timișoara who have played together since high school. Their shows are known for the energy from the first chord.', 'O trupă de văzut live măcar o dată.', 'Rock Arena'],
            ['kosmo', 'Kosmo', 'dj', ['electronic', 'house', 'techno'], ['electronic', 'house', 'techno'], 'București', 'RO', 'Seturi lungi, lumini mari, zero pauze.', 'Long sets, big lights, zero breaks.', $eu, $euEn, '1574154894072-18ba0d48321b', true, 265000,
                'DJ și producător, rezident în cluburile din București și prezent pe line-up-urile festivalurilor din regiune.', 'DJ and producer, resident in Bucharest clubs and on festival line-ups across the region.', null, null],
            ['corvin-quartet', 'Corvin Quartet', 'ensemble', ['clasic', 'crossover'], ['classical', 'crossover'], 'Iași', 'RO', 'Patru instrumente de coarde și muzica pe care n-o aștepți de la ele.', 'Four string instruments and the music you wouldn’t expect from them.', $eu, $euEn, '1465847899084-d164df4dedc6', true, 54000,
                'Cvartet de coarde care cântă Bach și piese pop rearanjate în aceeași seară. Concertele lor se țin în săli cu locuri.', 'A string quartet that plays Bach and rearranged pop songs on the same night. Their concerts take place in seated halls.', 'Elegant fără să fie rigid.', 'Opera Azi'],
            ['brass-nord', 'Brass Nord', 'band', ['jazz', 'funk', 'brass'], ['jazz', 'funk', 'brass'], 'Sibiu', 'RO', 'O fanfară de jazz care nu te lasă să stai jos.', 'A jazz brass band that won’t let you sit down.', $ro, $roEn, '1415201364774-f6f0bb35f28f', false, 31000,
                'Opt suflători și o tobă mare. Au cântat pe străzi, în cluburi și la nunți care au devenit legendă.', 'Eight horns and a big drum. They have played streets, clubs and weddings that became legend.', null, null],
            ['tudor-vlad', 'Tudor Vlad', 'solo', ['jazz'], ['jazz'], 'Sibiu', 'RO', 'Trompetă caldă, standarde și compoziții proprii.', 'Warm trumpet, standards and original pieces.', $eu, $euEn, '1511192336575-5a79af67a629', false, 18000,
                'Trompetist de jazz, invitat constant al festivalurilor de jazz din România.', 'Jazz trumpeter, a regular guest of jazz festivals across Romania.', null, null],
            ['lia-dima', 'Lia Dima', 'solo', ['folk', 'cantautor'], ['folk', 'singer-songwriter'], 'Brașov', 'RO', 'Chitară acustică și povești din munți.', 'Acoustic guitar and stories from the mountains.', $ro, $roEn, '1524650359799-842906ca1c06', true, 76000,
                'Cântă singură cu chitara sau cu un trio de coarde. Piesele ei vorbesc despre case vechi, drumuri și oameni care rămân.', 'She plays alone with a guitar or with a string trio. Her songs are about old houses, roads and people who stay.', 'Rar auzi atâta liniște într-o sală plină.', 'Jurnalul de Folk'],
            ['fumul-gri', 'Fumul Gri', 'band', ['rock', 'stoner'], ['rock', 'stoner'], 'Oradea', 'RO', 'Rock greu, riff-uri lente, volum mare.', 'Heavy rock, slow riffs, loud.', $eu, $euEn, '1498038432885-c6f3f1b912ee', false, 22000,
                'Trio de rock cu trei albume și peste 200 de concerte în cluburi din Europa.', 'A rock trio with three albums and more than 200 club shows across Europe.', null, null],
            ['sora-luna', 'Sora Lună', 'duo', ['synth-pop'], ['synth-pop'], 'Cluj-Napoca', 'RO', 'Duo de sintetizatoare cu refrene de anii ’80.', 'A synth duo with ’80s choruses.', $eu, $euEn, '1525362081669-2b476bb628c3', false, 64000,
                'Două surori, patru sintetizatoare și un spectacol de lumini construit de ele.', 'Two sisters, four synths and a light show they built themselves.', null, null],
            ['mc-vector', 'MC Vector', 'solo', ['hip-hop'], ['hip-hop'], 'Constanța', 'RO', 'Versuri rapide despre oraș și mare.', 'Fast verses about the city and the sea.', $ro, $roEn, '1415886541506-6efc5e4b1786', false, 143000,
                'Rapper din Constanța, cu un album de debut care a stat trei luni în topuri.', 'A rapper from Constanța whose debut album stayed in the charts for three months.', null, null],
            ['radiofar', 'Radiofar', 'band', ['indie', 'post-rock'], ['indie', 'post-rock'], 'Iași', 'RO', 'Piese lungi care cresc încet și explodează.', 'Long songs that grow slowly and explode.', $eu, $euEn, '1516450360452-9312f5e86fc7', false, 27000,
                'Cvintet instrumental din Iași, cunoscut pentru concertele cu proiecții.', 'An instrumental five-piece from Iași, known for shows with projections.', null, null],
            ['elena-stan', 'Elena Stan', 'solo', ['crossover', 'clasic'], ['crossover', 'classical'], 'București', 'RO', 'Vioară electrică, de la Vivaldi la techno.', 'Electric violin, from Vivaldi to techno.', $eu, $euEn, '1460036521480-ff49c08c2781', false, 39000,
                'Violonistă cu formație clasică, cântă cu orchestre și cu DJ.', 'A classically trained violinist who plays with orchestras and with DJs.', null, null],
            ['bass-theory', 'Bass Theory', 'duo', ['drum & bass', 'electronic'], ['drum & bass', 'electronic'], 'Timișoara', 'RO', 'Două decks, un bas care se simte în piept.', 'Two decks, a bass you feel in your chest.', $eu, $euEn, '1563841930606-67e2bce48b78', false, 58000,
                'Duo de producători drum & bass, cu lansări pe case de discuri din Londra.', 'A drum & bass production duo with releases on London labels.', null, null],
            ['noaptea-alba', 'Noaptea Albă', 'band', ['dream-pop', 'shoegaze'], ['dream-pop', 'shoegaze'], 'București', 'RO', 'Chitare ca ceața, voci ca un vis.', 'Guitars like fog, vocals like a dream.', $ro, $roEn, '1508973379184-7517410fb0bc', false, 15000,
                'Trupă de dream-pop cu un singur album și o comunitate de fani foarte devotată.', 'A dream-pop band with one album and a very devoted fanbase.', null, null],
        ];
    }

    /** Concertele produse de agenție: artiști (chei din roster), locație, preț / capacitate / vândute. */
    private function shows(): array
    {
        return [
            ['slug' => 'mira-vale-timisoara-trecut-demo', 'artists' => ['mira-vale'], 'title' => 'Mira Vale', 'days' => -80, 'time' => '20:30', 'venue' => 'timisoara', 'photo' => '1504898770365-14faca6a7320', 'past' => true],
            ['slug' => 'delta-noua-cluj-trecut-demo', 'artists' => ['delta-noua'], 'title' => 'Delta Nouă', 'days' => -52, 'time' => '21:00', 'venue' => 'cluj', 'photo' => '1598387993441-a364f854c3e1', 'past' => true],
            ['slug' => 'kosmo-bucuresti-trecut-demo', 'artists' => ['kosmo'], 'title' => 'Kosmo', 'days' => -33, 'time' => '23:00', 'venue' => 'bucuresti-club', 'photo' => '1574154894072-18ba0d48321b', 'past' => true],
            ['slug' => 'lia-dima-sibiu-trecut-demo', 'artists' => ['lia-dima'], 'title' => 'Lia Dima', 'days' => -15, 'time' => '20:00', 'venue' => 'sibiu', 'photo' => '1524650359799-842906ca1c06', 'past' => true],
            ['slug' => 'mira-vale-cluj-demo', 'artists' => ['mira-vale'], 'title' => 'Mira Vale', 'days' => 11, 'time' => '20:30', 'venue' => 'cluj', 'photo' => '1504898770365-14faca6a7320', 'price' => 149, 'cap' => 1800, 'sold' => 1800, 'soldout' => true],
            ['slug' => 'lia-dima-brasov-demo', 'artists' => ['lia-dima'], 'title' => 'Lia Dima', 'days' => 18, 'time' => '20:00', 'venue' => 'brasov', 'photo' => '1524650359799-842906ca1c06', 'price' => 89, 'cap' => 350, 'sold' => 340],
            ['slug' => 'corvin-quartet-iasi-demo', 'artists' => ['corvin-quartet', 'elena-stan'], 'title' => 'Corvin Quartet & Elena Stan', 'days' => 25, 'time' => '19:30', 'venue' => 'iasi', 'photo' => '1465847899084-d164df4dedc6', 'seated' => true],
            ['slug' => 'delta-noua-radiofar-timisoara-demo', 'artists' => ['delta-noua', 'radiofar'], 'title' => 'Delta Nouă + Radiofar', 'days' => 32, 'time' => '20:30', 'venue' => 'timisoara', 'photo' => '1598387993441-a364f854c3e1', 'price' => 99, 'cap' => 1200, 'sold' => 520],
            ['slug' => 'kosmo-bass-theory-bucuresti-demo', 'artists' => ['kosmo', 'bass-theory'], 'title' => 'Kosmo b2b Bass Theory', 'days' => 39, 'time' => '23:00', 'venue' => 'bucuresti-club', 'photo' => '1574154894072-18ba0d48321b', 'price' => 119, 'cap' => 1500, 'sold' => 700],
            ['slug' => 'jazz-night-sibiu-demo', 'artists' => ['brass-nord', 'tudor-vlad'], 'title' => 'Jazz Night: Brass Nord & Tudor Vlad', 'days' => 46, 'time' => '20:00', 'venue' => 'sibiu', 'photo' => '1415201364774-f6f0bb35f28f', 'price' => 79, 'cap' => 300, 'sold' => 90],
            ['slug' => 'sora-luna-cluj-demo', 'artists' => ['sora-luna'], 'title' => 'Sora Lună', 'days' => 53, 'time' => '21:00', 'venue' => 'cluj-club', 'photo' => '1525362081669-2b476bb628c3', 'price' => 69, 'cap' => 400, 'sold' => 120],
            ['slug' => 'mc-vector-constanta-demo', 'artists' => ['mc-vector'], 'title' => 'MC Vector', 'days' => 60, 'time' => '21:00', 'venue' => 'constanta', 'photo' => '1415886541506-6efc5e4b1786', 'price' => 89, 'cap' => 2000, 'sold' => 640],
            ['slug' => 'kosmo-festival-val-demo', 'artists' => ['kosmo'], 'title' => 'Kosmo @ Festivalul Val', 'days' => 67, 'time' => '01:00', 'venue' => 'festival', 'photo' => '1563841930606-67e2bce48b78',
                'external' => ['https://example.com/festival-val/bilete', 'Bilete la Festivalul Val']],
            ['slug' => 'fumul-gri-oradea-demo', 'artists' => ['fumul-gri', 'noaptea-alba'], 'title' => 'Fumul Gri + Noaptea Albă', 'days' => 74, 'time' => '20:30', 'venue' => 'oradea', 'photo' => '1498038432885-c6f3f1b912ee', 'price' => 69, 'cap' => 500, 'sold' => 60],
        ];
    }

    /** Locațiile: [slug, nume ro, nume en, oraș, regiune, adresă, țară, lat, lng] */
    private function venueDefs(): array
    {
        return [
            'timisoara' => ['hala-faber-timisoara-demo-agentie', 'Hala Faber', 'Faber Hall', 'Timișoara', 'Timiș', 'Str. Industriei 8, Timișoara', 'RO', 45.7537, 21.2257],
            'cluj' => ['arena-ursus-cluj-demo-agentie', 'Arena Ursus', 'Ursus Arena', 'Cluj-Napoca', 'Cluj', 'Str. Berăriei 1, Cluj-Napoca', 'RO', 46.7745, 23.5891],
            'cluj-club' => ['clubul-contor-cluj-demo-agentie', 'Clubul Contor', 'Contor Club', 'Cluj-Napoca', 'Cluj', 'Str. Horea 12, Cluj-Napoca', 'RO', 46.7768, 23.5864],
            'bucuresti-club' => ['depozitul-23-bucuresti-demo-agentie', 'Depozitul 23', 'Warehouse 23', 'București', 'București', 'Str. Gării de Est 23, București', 'RO', 44.4370, 26.1560],
            'sibiu' => ['sala-thalia-sibiu-demo-agentie', 'Sala Teatrului Vechi', 'Old Theatre Hall', 'Sibiu', 'Sibiu', 'Str. Cetății 3, Sibiu', 'RO', 45.7936, 24.1478],
            'brasov' => ['casa-muzicii-brasov-demo-agentie', 'Casa Muzicii', 'House of Music', 'Brașov', 'Brașov', 'Str. Republicii 40, Brașov', 'RO', 45.6440, 25.5910],
            'iasi' => ['sala-concordia-iasi-demo-agentie', 'Sala Concordia', 'Concordia Hall', 'Iași', 'Iași', 'Bd. Ștefan cel Mare 18, Iași', 'RO', 47.1667, 27.5833],
            'constanta' => ['arena-mareea-constanta-demo-agentie', 'Arena Mareea', 'Tide Arena', 'Constanța', 'Constanța', 'Faleza Sud 7, Constanța', 'RO', 44.1730, 28.6383],
            'oradea' => ['clubul-fabrica-oradea-demo-agentie', 'Clubul Fabrica', 'Factory Club', 'Oradea', 'Bihor', 'Str. Fabricii 5, Oradea', 'RO', 47.0590, 21.9300],
            'festival' => ['festivalul-val-demo-agentie', 'Festivalul Val · Scena Plajă', 'Val Festival · Beach Stage', 'Vama Veche', 'Constanța', 'Plaja Vama Veche', 'RO', 43.7520, 28.5790],
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
        $data = ['name' => self::NAME, 'public_name' => self::NAME, 'company_name' => 'Contrapunct Artists SRL', 'owner_id' => $user->id,
            'slug' => self::TENANT_SLUG, 'status' => 'active', 'tenant_type' => TenantType::Agency,
            'plan' => '2percent', 'work_method' => 'mixed', 'commission_rate' => 2.00, 'commission_mode' => 'included',
            'locale' => 'ro', 'currency' => 'RON', 'country' => 'RO', 'city' => 'București', 'contact_email' => $email,
            'has_own_website' => false, 'use_core_smtp' => true, 'onboarding_completed' => true, 'billing_cycle_days' => 30];
        if (! $taken) { $data['domain'] = $domain; }
        $settings = ['site_title' => self::NAME, 'site_language' => 'ro', 'storefront' => ['order_emails' => true, 'brand_dark' => '#0F1A14', 'brand_color' => '#F2B33D']];
        if (! $t) {
            $data += ['ticket_series_prefix' => 'CTRP', 'settings' => $settings + ['panel' => ['simple_menu' => true]],
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
    private function seedRoster(Tenant $tenant): array
    {
        $out = [];
        foreach ($this->rosterDefs() as $i => [$key, $name, $kind, $gRo, $gEn, $city, $country, $tRo, $tEn, $terrRo, $terrEn, $photo, $featured, $listeners, $bioRo, $bioEn, $quote, $source]) {
            $a = TenantArtist::firstOrNew(['tenant_id' => $tenant->id, 'slug' => $key]);
            $a->fill([
                'name' => $name, 'role' => 'roster', 'status' => 'active', 'is_resident' => true,
                'bio' => ['ro' => '<p>' . $bioRo . '</p>', 'en' => '<p>' . $bioEn . '</p>'],
                'photo_url' => self::IMG . $photo . '?auto=format&fit=crop&w=1000&h=1200&q=80',
                'meta' => [
                    'kind' => $kind, 'genres' => $gRo, 'city' => $city, 'country' => $country, 'tagline' => $tRo, 'territories' => $terrRo,
                    'bookable' => true, 'featured' => $featured, 'listeners' => $listeners,
                    'hero_url' => self::IMG . $photo . '?auto=format&fit=crop&w=2000&q=80',
                    'links' => ['spotify' => 'https://open.spotify.com/', 'instagram' => 'https://www.instagram.com/', 'youtube' => 'https://www.youtube.com/'],
                    'quote' => $quote ? ['text' => $quote, 'source' => $source] : null,
                    'i18n' => ['en' => ['genres' => $gEn, 'tagline' => $tEn, 'territories' => $terrEn]],
                ],
            ]);
            $a->save();
            $out[$key] = $a;
        }
        $this->line('  roster: ' . count($out) . ' artiști');
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

    /* ---------------------------------------------------------------- sala cu locuri (Sala Concordia) */

    private function hallPlan(): array
    {
        return [
            ['Parter', 'PARTER', 139, '#F2B33D', 170, 170, 860, 400, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'], 22, 12],
            ['Lojă', 'LOJA', 189, '#E8E2D0', 60, 260, 90, 300, ['L1', 'L2'], 4, 0],
            ['Balcon', 'BALCON', 89, '#7FA88A', 120, 610, 960, 170, ['1', '2', '3', '4'], 26, 28],
        ];
    }

    private function seedHall(Tenant $tenant, Venue $venue): SeatingLayout
    {
        $name = 'Sala Concordia — plan concert';
        $layout = SeatingLayout::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', $name)->first();
        if ($layout) {
            return $layout;
        }
        return DB::transaction(function () use ($tenant, $venue, $name) {
            $layout = SeatingLayout::create($this->only('seating_layouts', [
                'tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'name' => $name, 'status' => 'published',
                'canvas_w' => 1200, 'canvas_h' => 850, 'version' => 1,
            ]));
            SeatingSection::create($this->only('seating_sections', [
                'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => 'Scena', 'section_code' => 'SCENA', 'section_type' => 'decorative',
                'x_position' => 350, 'y_position' => 40, 'width' => 500, 'height' => 80, 'display_order' => 0, 'color_hex' => '#E8E2D0',
            ]));
            foreach ($this->hallPlan() as $order => [$secName, $code, $price, $color, $x, $y, $w, $h, $rows, $per, $arc]) {
                $section = SeatingSection::create($this->only('seating_sections', [
                    'layout_id' => $layout->id, 'tenant_id' => $tenant->id, 'name' => $secName, 'section_code' => $code, 'section_type' => 'standard',
                    'x_position' => $x, 'y_position' => $y, 'width' => $w, 'height' => $h, 'rotation' => 0, 'display_order' => $order + 1,
                    'color_hex' => $color, 'seat_color' => $color, 'metadata' => ['seat_size' => 18],
                ]));
                $gap = $per > 1 ? ($w - 80) / ($per - 1) : 0;
                foreach ($rows as $ri => $rowLabel) {
                    $rowY = 30 + $ri * 34;
                    $row = SeatingRow::create(['section_id' => $section->id, 'label' => $rowLabel, 'seat_start_number' => 1, 'y' => $rowY, 'rotation' => 0, 'seat_count' => $per]);
                    for ($n = 1; $n <= $per; $n++) {
                        $t = ($n - 1) / max(1, $per - 1) * 2 - 1;
                        // Lojele: două rânduri verticale de câte 4 locuri, în stânga parterului
                        [$sx, $sy] = $code === 'LOJA' ? [20 + $ri * 30, 30 + ($n - 1) * 60] : [round(40 + ($n - 1) * $gap, 1), round($rowY - $arc * $t * $t, 1)];
                        SeatingSeat::create([
                            'row_id' => $row->id, 'label' => (string) $n, 'display_name' => $rowLabel . '-' . $n,
                            'x' => $sx, 'y' => $sy, 'angle' => 0, 'shape' => 'circle', 'status' => 'active',
                            'seat_uid' => $section->generateSeatUid($rowLabel, (string) $n),
                        ]);
                    }
                }
            }
            $this->line('  sala Concordia: layout #' . $layout->id);
            return $layout;
        });
    }

    private function seedSeatedTickets(Tenant $tenant, Event $ev, SeatingLayout $layout, MarketplaceEventSeatingService $svc): void
    {
        if ($this->has('events', 'seating_layout_id')) {
            $ev->forceFill(['seating_layout_id' => $layout->id])->saveQuietly();
        }
        $sections = SeatingSection::withoutGlobalScopes()->where('layout_id', $layout->id)->get()->keyBy('name');
        $en = ['Parter' => 'Stalls', 'Lojă' => 'Box', 'Balcon' => 'Balcony'];
        $desc = [
            'Parter' => ['Rândurile A–K, în fața scenei.', 'Rows A–K, facing the stage.'],
            'Lojă' => ['Loja din stânga scenei, 8 locuri, cu un pahar de vin la pauză.', 'The box to the left of the stage, 8 seats, with a glass of wine at the interval.'],
            'Balcon' => ['Balconul, cu vedere de ansamblu.', 'The balcony, with a full view.'],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($this->hallPlan() as $i => [$secName, $code, $price, $color, , , , , $rows, $per]) {
            PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'CTRP-' . $code)->first()
                ?? PriceTier::create(['tenant_id' => $tenant->id, 'name' => $secName, 'tier_code' => 'CTRP-' . $code, 'currency' => 'RON',
                    'price' => $price, 'color' => $color, 'is_active' => true, 'sort_order' => $i + 1]);
            $sku = 'CTRP-IAS-' . $code;
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
            $this->warn('  harta concertului din Iași nu s-a putut genera');
            return;
        }
        $tiers = PriceTier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tier_code', 'like', 'CTRP-%')->get()->keyBy('name');
        foreach ($tiers as $name => $tier) {
            EventSeat::where('event_seating_id', $es->id)->where('section_name', $name)->update(['price_tier_id' => $tier->id]);
        }
        if (! EventSeat::where('event_seating_id', $es->id)->where('status', 'sold')->exists()) {
            foreach (EventSeat::where('event_seating_id', $es->id)->where('status', 'available')->orderBy('id')->get() as $i => $seat) {
                if ($i % 5 === 0 || ($seat->section_name === 'Lojă' && $i % 2 === 0)) {
                    $seat->update(['status' => 'sold', 'version' => $seat->version + 1]);
                }
            }
        }
        $this->line('  harta concertului din Iași: event_seating #' . $es->id . ', ' . EventSeat::where('event_seating_id', $es->id)->count() . ' locuri');
    }

    /* ---------------------------------------------------------------- concerte */

    private function seedShow(Tenant $tenant, array $c, Venue $venue, array $roster): Event
    {
        $ev = Event::where('slug', $c['slug'])->first();
        if ($ev && (int) $ev->tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('slug-ul aparține altui tenant');
        }
        $date = now()->addDays($c['days'])->toDateString();
        [, $vRo, $vEn, $city] = $this->venueDefs()[$c['venue']];
        $cityEn = ['București' => 'Bucharest'][$city] ?? $city;
        $openAir = ! empty($c['external']);
        $names = array_map(fn ($k) => $roster[$k]->name ?? $k, $c['artists']);
        $payload = $this->only('events', [
            'tenant_id' => $tenant->id, 'slug' => $c['slug'], 'venue_id' => $venue->id,
            'title' => ['ro' => $c['title'] . ' · ' . $city, 'en' => str_replace('Festivalul Val', 'Val Festival', $c['title']) . ' · ' . $cityEn],
            'short_description' => [
                'ro' => implode(' și ', $names) . ' la ' . $vRo . ', ' . $city . '. Un concert produs de ' . self::NAME . '.',
                'en' => implode(' and ', $names) . ' at ' . $vEn . ', ' . $cityEn . '. A show produced by ' . self::NAME . '.',
            ],
            'description' => [
                'ro' => '<p>' . e(implode(' și ', $names)) . ' urcă pe scenă la ' . e($vRo) . '. ' . (count($names) > 1 ? 'Două seturi complete, în aceeași seară.' : 'Un set complet, cu piesele noi și cele cerute de public.') . '</p>'
                    . '<p>Accesul se face cu biletul electronic, de pe telefon sau tipărit.</p>'
                    . '<p><em>Concert demonstrativ: artiștii, agenția și datele sunt inventate, iar plata trece printr-un procesator de test.</em></p>',
                'en' => '<p>' . e(implode(' and ', $names)) . ' take the stage at ' . e($vEn) . '. ' . (count($names) > 1 ? 'Two full sets on the same night.' : 'A full set with the new songs and the ones the crowd asks for.') . '</p>'
                    . '<p>Entry with your e-ticket, on your phone or printed.</p>'
                    . '<p><em>Demo show: the artists, the agency and the dates are made up, and payments go through a test processor.</em></p>',
            ],
            'is_published' => true, 'is_cancelled' => false, 'is_sold_out' => ! empty($c['soldout']), 'is_indoor' => ! $openAir, 'is_kid_friendly' => true,
            'duration_mode' => 'single_day', 'event_date' => $date,
            'door_time' => date('H:i', strtotime($c['time'] . ' -60 minutes')), 'start_time' => $c['time'],
            'end_time' => date('H:i', strtotime($c['time'] . ' +150 minutes')),
            'poster_url' => self::IMG . $c['photo'] . '?auto=format&fit=crop&w=1200&q=80',
            'hero_image_url' => self::IMG . $c['photo'] . '?auto=format&fit=crop&w=2000&q=80',
            'commission_rate' => 2.00, 'commission_mode' => 'included',
            'external_ticket_url' => $c['external'][0] ?? null,
            'external_ticket_label' => $c['external'][1] ?? null,
        ]);
        $ev ? $ev->update($payload) : ($ev = Event::create($payload));
        // Cine cântă: concertul apare pe pagina fiecărui artist
        $sync = [];
        foreach ($c['artists'] as $i => $k) {
            if (isset($roster[$k])) {
                $sync[$roster[$k]->id] = ['role_in_event' => $i === 0 ? 'headliner' : 'guest', 'sort_order' => $i];
            }
        }
        $ev->tenantArtists()->sync($sync);
        return $ev;
    }

    private function seedTickets(Event $ev, array $c): void
    {
        $p = (int) $c['price'];
        $cap = (int) $c['cap'];
        $sold = (int) $c['sold'];
        $defs = [
            ['GA', 'Acces general', 'General admission', $p, $cap - 80, max(0, $sold - 80), 'Acces în sală.', 'Entry to the venue.', [], []],
            ['VIP', 'VIP', 'VIP', $p + 120, 60, min($sold, 60) ?: 0,
                'Acces pe balconul VIP, garderobă separată, o băutură inclusă.', 'VIP balcony access, separate cloakroom, one drink included.',
                ['Balcon VIP', 'Garderobă separată', 'O băutură inclusă'], ['VIP balcony', 'Separate cloakroom', 'One drink included']],
            ['FAN', 'Fan pack', 'Fan pack', $p + 90, 20, ! empty($c['soldout']) ? 20 : min(20, (int) round($sold / 50)),
                'Bilet + tricoul turneului + poster semnat.', 'Ticket + tour T-shirt + signed poster.', ['Tricoul turneului', 'Poster semnat'], ['Tour T-shirt', 'Signed poster']],
        ];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($defs as $i => [$code, $ro, $en, $price, $capacity, $sq, $dRo, $dEn, $perks, $perksEn]) {
            $sku = 'CTRP-' . strtoupper(substr(md5($ev->slug), 0, 6)) . '-' . $code;
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $ro; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON';
            $tt->capacity = max(1, $capacity);
            foreach ($this->only('ticket_types', ['description' => $dRo, 'perks' => $perks, 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => 10,
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
        if (! Schema::hasTable('coupon_codes') || CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'CONTRA10')->exists()) {
            return;
        }
        CouponCode::create($this->only('coupon_codes', ['tenant_id' => $tenant->id, 'code' => 'CONTRA10', 'code_type' => 'multi_use',
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
