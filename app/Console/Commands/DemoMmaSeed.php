<?php

namespace App\Console\Commands;

use App\Enums\TenantType;
use App\Models\Coupon\CouponCode;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Event;
use App\Models\EventBout;
use App\Models\Tenant;
use App\Models\TenantArtist;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tenantul demo pentru GALE DE LUPTE (template-ul gala, demo-mma.tics.ro):
 * „FURIA Fighting Championship”, o promoție inventată: 22 de luptători (TenantArtist
 * cu rol fighter + profil sportiv), FURIA 14 (trecută, cu rezultate), FURIA 15 și
 * FURIA 16 (viitoare), fight card-uri pe secțiuni, bilete pe zone, RO + EN.
 *
 *   php artisan demo:seed-mma
 *
 * Idempotent după slug / email / domeniu, pur aditiv, nu atinge alți tenanți.
 * Luptătorii sunt ai tenantului (tenant_artists), nu apar în nicio listă globală.
 * Datele sunt relative la ziua rulării. Cere migrarea event_bouts.
 */
class DemoMmaSeed extends Command
{
    protected $signature = 'demo:seed-mma
        {--domain=demo-mma.tics.ro : Hostname-ul demo-ului}
        {--owner-email=demo-mma@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului}';

    protected $description = 'Seed demo complet pentru gale de lupte (FURIA FC) + demo-ul live demo-mma.tics.ro';

    private const NAME = 'FURIA Fighting Championship';
    private const TENANT_SLUG = 'demo-mma';
    private const IMG = 'https://images.unsplash.com/photo-';

    private array $colCache = [];

    public function handle(): int
    {
        if (! Schema::hasTable('event_bouts')) {
            $this->error('Lipsește tabela event_bouts: rulează întâi php artisan migrate.');
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
        $venues = [];
        $this->guarded('locații', function () use ($tenant, &$venues) { $venues = $this->seedVenues($tenant); });
        $fighters = [];
        $this->guarded('luptători', function () use ($tenant, &$fighters) { $fighters = $this->seedFighters($tenant); });

        $events = [];
        foreach ($this->galas() as $g) {
            $this->guarded('gala ' . $g['slug'], function () use ($tenant, $g, $venues, $fighters, &$events) {
                $ev = $this->seedGala($tenant, $g, $venues[$g['venue']] ?? null);
                $this->seedCard($tenant, $ev, $g['card'], $fighters, $g['past'] ?? false);
                if (empty($g['past'])) {
                    $this->seedTickets($ev, $g['tickets'] ?? 'full');
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
                    'template' => 'gala', 'tenant' => $tenant->id, 'slug' => $m[1],
                    '--title' => 'Gale de MMA și box',
                    '--description' => 'Fight card pe secțiuni, față în față, numărătoare inversă, bilete pe zone, luptători cu record și rezultate.',
                    '--sort' => 15,
                ]);
            }
        });

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant:  #' . $tenant->id . ' ' . $tenant->slug . ' · owner ' . $ownerEmail . ' / ' . $pass);
        $this->line('  Site:    https://' . $domain . '/  · client demo@' . $domain . ' / demo1234 · cod FURIA10');
        foreach ($events as $ev) {
            $this->line('  Gala:    #' . $ev->id . ' ' . $ev->slug . ' (' . ($ev->event_date?->toDateString()) . ')');
        }
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- date */

    /** [cheie, nume, poreclă ro, poreclă en, țară, categorie ro, categorie en, kg, disciplină, w, l, d, echipă, înălțime, anvergură, gardă, campion, foto, bio ro, bio en] */
    private function fighterDefs(): array
    {
        return [
            ['croitoru', 'Andrei Croitoru', 'Ursul', 'The Bear', 'RO', 'Grea', 'Heavyweight', 120, 'mma', 14, 2, 0, 'Bear Pit Cluj', 193, 198, 'Orthodox', true, '1611672585731-fa10603fb9e0',
                'Campionul la categoria grea. Fost luptător de judo, termină meciurile repede: 9 victorii prin KO.', 'The heavyweight champion. A former judoka who finishes fights fast: 9 wins by KO.'],
            ['kowal', 'Mateusz Kowal', 'Ciocanul', 'The Hammer', 'PL', 'Grea', 'Heavyweight', 120, 'mma', 12, 3, 0, 'Ankos Poznań', 190, 194, 'Orthodox', false, '1603287681836-b174ce5074c2',
                'Pumnul cel mai greu din Polonia, vine după trei victorii la rând.', 'The heaviest hands in Poland, on a three-fight win streak.'],
            ['ionescu', 'Radu Ionescu', 'Fulger', 'Lightning', 'RO', 'Ușoară', 'Lightweight', 70, 'mma', 11, 1, 0, 'Fulger Team București', 178, 183, 'Southpaw', true, '1562771379-eafdca7a02f8',
                'Campion la ușoară, cel mai rapid luptător din promoție. O singură înfrângere, răzbunată.', 'Lightweight champion and the fastest fighter in the promotion. One loss, avenged.'],
            ['berisha', 'Ilir Berisha', 'Lupul', 'The Wolf', 'AL', 'Ușoară', 'Lightweight', 70, 'mma', 13, 4, 0, 'Wolfpack Tirana', 176, 180, 'Orthodox', false, '1594737625785-a6cbdabd333c',
                'Luptător complet, cu 7 victorii prin submission. Vrea centura de la prima încercare.', 'A complete fighter with 7 submission wins. Wants the belt at the first attempt.'],
            ['marin', 'Elena Marin', 'Viespea', 'The Wasp', 'RO', 'Muscă (feminin)', 'Women’s Flyweight', 57, 'mma', 8, 1, 0, 'Iron Lady Gym', 165, 168, 'Orthodox', true, '1584464491033-06628f3a6b7b',
                'Prima campioană a promoției, kickboxeră cu o tehnică de lovituri de picior foarte rară.', 'The promotion’s first women’s champion, a kickboxer with rare kicking technique.'],
            ['varga', 'Sofia Varga', 'Tigrisa', 'The Tigress', 'HU', 'Muscă (feminin)', 'Women’s Flyweight', 57, 'mma', 7, 2, 0, 'Budapest Top Team', 167, 170, 'Southpaw', false, '1517438322307-e67111335449',
                'Luptătoare de wrestling, câștigă meciurile la sol.', 'A wrestler who wins her fights on the ground.'],
            ['stan', 'Bogdan Stan', 'Tancul', 'The Tank', 'RO', 'Semimijlocie', 'Welterweight', 77, 'mma', 10, 3, 0, 'Tank Gym Iași', 181, 185, 'Orthodox', false, '1532029837206-abbe2b7620e3',
                'Presiune continuă trei runde. Nu a fost niciodată făcut KO.', 'Non-stop pressure for three rounds. Never been knocked out.'],
            ['petrovic', 'Luka Petrović', 'Coasa', 'The Scythe', 'RS', 'Semimijlocie', 'Welterweight', 77, 'mma', 9, 2, 0, 'Belgrade Fight Club', 183, 188, 'Orthodox', false, '1526506118085-60ce8714f8c5',
                'Lovituri de genunchi în clinch, 6 victorii înainte de limită.', 'Knees from the clinch, 6 wins before the final bell.'],
            ['dumitru', 'Cristian Dumitru', 'Mecanicul', 'The Mechanic', 'RO', 'Mijlocie', 'Middleweight', 84, 'mma', 7, 2, 0, 'Grapple Lab Timișoara', 185, 190, 'Orthodox', false, '1615117972428-28de67cda58e',
                'Centură neagră de jiu-jitsu brazilian, termină meciurile cu sufocări.', 'Brazilian jiu-jitsu black belt who finishes fights with chokes.'],
            ['neagu', 'Tudor Neagu', 'Zmeul', 'The Dragon', 'RO', 'Mijlocie', 'Middleweight', 84, 'mma', 9, 4, 0, 'Dragon MMA Brașov', 187, 191, 'Southpaw', false, null,
                'Veteran al promoției, cu cele mai multe meciuri disputate.', 'A promotion veteran with the most fights on record.'],
            ['pop', 'Marius Pop', 'Profesorul', 'The Professor', 'RO', 'Pană', 'Featherweight', 66, 'mma', 6, 0, 0, 'Fulger Team București', 172, 176, 'Orthodox', false, null,
                'Neînvins, profesor de sport în timpul zilei.', 'Unbeaten, a PE teacher by day.'],
            ['avram', 'Daniel Avram', null, null, 'RO', 'Pană', 'Featherweight', 66, 'mma', 8, 3, 0, 'Tank Gym Iași', 170, 173, 'Orthodox', false, null,
                'Boxer de formație, cu o bărbie greu de clintit.', 'A boxer by background, with a chin that’s hard to crack.'],
            ['georgiev', 'Nikolai Georgiev', 'Bulgarul', 'The Bulgarian', 'BG', 'Cocoș', 'Bantamweight', 61, 'mma', 10, 5, 0, 'Sofia Fight Academy', 168, 170, 'Orthodox', false, null,
                'Luptător de lupte libere, campion național la juniori.', 'Freestyle wrestler and former national junior champion.'],
            ['rusu', 'Alexandru Rusu', null, null, 'MD', 'Cocoș', 'Bantamweight', 61, 'mma', 7, 1, 0, 'Chișinău Top Team', 169, 172, 'Southpaw', false, null,
                'Din Chișinău, cu 5 victorii prin KO în primele două runde.', 'From Chișinău, with 5 KO wins inside two rounds.'],
            ['toma', 'Gabriel Toma', null, null, 'RO', 'Semigrea', 'Light Heavyweight', 93, 'mma', 5, 1, 0, 'Bear Pit Cluj', 188, 192, 'Orthodox', false, null,
                'Partenerul de antrenament al campionului Croitoru.', 'Champion Croitoru’s training partner.'],
            ['enache', 'Florin Enache', null, null, 'RO', 'Semigrea', 'Light Heavyweight', 93, 'mma', 6, 2, 0, 'Dragon MMA Brașov', 186, 190, 'Orthodox', false, null,
                'Fost jucător de rugby, puternic în clinch.', 'A former rugby player, strong in the clinch.'],
            ['moraru', 'Vlad Moraru', 'Copilul', 'The Kid', 'MD', 'Pană', 'Featherweight', 66, 'mma', 4, 0, 0, 'Chișinău Top Team', 174, 178, 'Switch', false, null,
                'Cel mai tânăr luptător din promoție, 20 de ani, patru victorii prin KO.', 'The youngest fighter in the promotion, 20, four wins by KO.'],
            ['badea', 'Ionuț Badea', null, null, 'RO', 'Ușoară', 'Lightweight', 70, 'mma', 9, 6, 0, 'Iron Lady Gym', 177, 180, 'Orthodox', false, null,
                'Mereu dispus să accepte meciuri cu o săptămână înainte.', 'Always ready to take a fight on a week’s notice.'],
            ['herrera', 'Tomás Herrera', 'El Toro', 'El Toro', 'ES', 'Semimijlocie', 'Welterweight', 77, 'mma', 11, 5, 0, 'Madrid Combat', 180, 184, 'Orthodox', false, null,
                'Veteran al circuitului spaniol.', 'A veteran of the Spanish circuit.'],
            ['lazar', 'Ștefan Lazăr', 'Bisturiul', 'The Scalpel', 'RO', 'Kickbox 70 kg', 'Kickboxing 70 kg', 70, 'kickboxing', 15, 3, 0, 'K1 Constanța', 179, 182, 'Southpaw', false, '1599058917212-d750089bc07e',
                'Kickboxer tehnic, campion european la amatori.', 'Technical kickboxer, a former European amateur champion.'],
            ['demir', 'Hakan Demir', null, null, 'TR', 'Kickbox 70 kg', 'Kickboxing 70 kg', 70, 'kickboxing', 18, 6, 0, 'Istanbul Gym', 177, 180, 'Orthodox', false, null,
                'Peste 20 de meciuri profesioniste în Europa.', 'More than 20 pro fights across Europe.'],
            ['ilie', 'Paul Ilie', null, null, 'RO', 'Mijlocie', 'Middleweight', 84, 'mma', 3, 1, 0, 'Grapple Lab Timișoara', 184, 188, 'Orthodox', false, null,
                'Debutant promițător, campion universitar la lupte.', 'A promising debutant and university wrestling champion.'],
        ];
    }

    /** Galele: [slug, nr, zile față de azi, oră, locație, trecută?, card, bilete] */
    private function galas(): array
    {
        $kickTitle = ['Centura la categoria grea', 'Heavyweight title'];
        return [
            ['slug' => 'furia-14-demo', 'n' => 14, 'days' => -42, 'time' => '19:00', 'venue' => 'constanta', 'past' => true,
                'card' => [
                    ['main_event', 'ionescu', 'badea', 'Ușoară (70 kg)', 'Lightweight (70 kg)', 'mma', 5, true, ['Centura la categoria ușoară', 'Lightweight title'], ['a', 'Submission (ghilotină)', 'Submission (guillotine)', 3, '2:41']],
                    ['co_main', 'croitoru', 'enache', 'Grea (120 kg)', 'Heavyweight (120 kg)', 'mma', 3, false, null, ['a', 'KO (cross de dreapta)', 'KO (right cross)', 1, '2:14']],
                    ['main_card', 'stan', 'herrera', 'Semimijlocie (77 kg)', 'Welterweight (77 kg)', 'mma', 3, false, null, ['a', 'Decizie unanimă', 'Unanimous decision', 3, '5:00']],
                    ['main_card', 'dumitru', 'ilie', 'Mijlocie (84 kg)', 'Middleweight (84 kg)', 'mma', 3, false, null, ['a', 'TKO (lovituri la sol)', 'TKO (ground strikes)', 2, '3:57']],
                    ['prelims', 'pop', 'rusu', 'Pană (66 kg)', 'Featherweight (66 kg)', 'mma', 3, false, null, ['a', 'Decizie divizată', 'Split decision', 3, '5:00']],
                    ['prelims', 'georgiev', 'avram', 'Cocoș (61 kg)', 'Bantamweight (61 kg)', 'mma', 3, false, null, ['a', 'Submission (cheie de braț)', 'Submission (armbar)', 1, '4:12']],
                ]],
            ['slug' => 'furia-15-demo', 'n' => 15, 'days' => 33, 'time' => '19:00', 'venue' => 'cluj', 'tickets' => 'full',
                'card' => [
                    ['main_event', 'croitoru', 'kowal', 'Grea (120 kg)', 'Heavyweight (120 kg)', 'mma', 5, true, $kickTitle, null],
                    ['co_main', 'ionescu', 'berisha', 'Ușoară (70 kg)', 'Lightweight (70 kg)', 'mma', 5, true, ['Centura la categoria ușoară', 'Lightweight title'], null],
                    ['main_card', 'marin', 'varga', 'Muscă feminin (57 kg)', 'Women’s Flyweight (57 kg)', 'mma', 5, true, ['Centura feminină la muscă', 'Women’s flyweight title'], null],
                    ['main_card', 'stan', 'petrovic', 'Semimijlocie (77 kg)', 'Welterweight (77 kg)', 'mma', 3, false, null, null],
                    ['main_card', 'lazar', 'demir', 'Kickbox 70 kg', 'Kickboxing 70 kg', 'kickboxing', 3, false, null, null],
                    ['main_card', 'dumitru', 'neagu', 'Mijlocie (84 kg)', 'Middleweight (84 kg)', 'mma', 3, false, null, null],
                    ['prelims', 'pop', 'avram', 'Pană (66 kg)', 'Featherweight (66 kg)', 'mma', 3, false, null, null],
                    ['prelims', 'georgiev', 'rusu', 'Cocoș (61 kg)', 'Bantamweight (61 kg)', 'mma', 3, false, null, null],
                    ['prelims', 'toma', 'enache', 'Semigrea (93 kg)', 'Light Heavyweight (93 kg)', 'mma', 3, false, null, null],
                    ['prelims', 'badea', 'herrera', 'Ușoară (70 kg)', 'Lightweight (70 kg)', 'mma', 3, false, null, null],
                ]],
            ['slug' => 'furia-16-demo', 'n' => 16, 'days' => 89, 'time' => '19:30', 'venue' => 'bucuresti', 'tickets' => 'full',
                'card' => [
                    ['main_event', 'moraru', 'pop', 'Pană (66 kg)', 'Featherweight (66 kg)', 'mma', 5, false, null, null],
                    ['co_main', 'petrovic', null, 'Semimijlocie (77 kg)', 'Welterweight (77 kg)', 'mma', 3, false, null, null],
                    ['main_card', 'ilie', 'neagu', 'Mijlocie (84 kg)', 'Middleweight (84 kg)', 'mma', 3, false, null, null],
                ]],
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
        $data = ['name' => self::NAME, 'public_name' => 'FURIA FC', 'company_name' => self::NAME, 'owner_id' => $user->id,
            'slug' => self::TENANT_SLUG, 'status' => 'active', 'tenant_type' => TenantType::Competition,
            'plan' => '2percent', 'work_method' => 'mixed', 'commission_rate' => 2.00, 'commission_mode' => 'included',
            'locale' => 'ro', 'currency' => 'RON', 'country' => 'RO', 'city' => 'Cluj-Napoca', 'contact_email' => $email,
            'has_own_website' => false, 'use_core_smtp' => true, 'onboarding_completed' => true, 'billing_cycle_days' => 30];
        if (! $taken) { $data['domain'] = $domain; }
        $settings = ['site_title' => 'FURIA FC', 'site_language' => 'ro', 'storefront' => ['order_emails' => true, 'brand_dark' => '#0A0A0A', 'brand_color' => '#E10600']];
        if (! $t) {
            $data += ['ticket_series_prefix' => 'FURIA', 'settings' => $settings + ['panel' => ['simple_menu' => true]],
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
        $defs = [
            'cluj' => ['arena-somes-cluj-demo-mma', 'Arena Someș', 'Someș Arena', 'Cluj-Napoca', 'Cluj', 'Str. Arenei 1, Cluj-Napoca'],
            'constanta' => ['sala-valul-constanta-demo-mma', 'Sala Valul', 'Valul Hall', 'Constanța', 'Constanța', 'Bd. Mamaia 40, Constanța'],
            'bucuresti' => ['arena-cetatii-bucuresti-demo-mma', 'Arena Cetății', 'Citadel Arena', 'București', 'București', 'Bd. Cetății 5, București'],
        ];
        $out = [];
        foreach ($defs as $k => [$slug, $ro, $en, $city, $state, $addr]) {
            $v = Venue::where('slug', $slug)->first();
            $data = $this->only('venues', ['tenant_id' => $tenant->id, 'name' => ['ro' => $ro, 'en' => $en], 'slug' => $slug, 'city' => $city, 'state' => $state, 'address' => $addr]);
            $v ? $v->update($data) : ($v = Venue::create($data));
            $out[$k] = $v;
        }
        return $out;
    }

    /** @return array<string, TenantArtist> */
    private function seedFighters(Tenant $tenant): array
    {
        $out = [];
        foreach ($this->fighterDefs() as $i => $d) {
            [$key, $name, $nickRo, $nickEn, $country, $wcRo, $wcEn, $kg, $disc, $w, $l, $dr, $team, $h, $reach, $stance, $champ, $photo, $bioRo, $bioEn] = $d;
            $f = TenantArtist::firstOrNew(['tenant_id' => $tenant->id, 'slug' => 'furia-' . $key]);
            $f->fill([
                'name' => $name, 'role' => 'fighter', 'status' => 'active',
                'birth_date' => now()->subYears(22 + ($i * 7) % 14)->subDays($i * 37)->toDateString(),
                'bio' => ['ro' => '<p>' . $bioRo . '</p>', 'en' => '<p>' . $bioEn . '</p>'],
                'photo_url' => $photo ? self::IMG . $photo . '?auto=format&fit=crop&w=900&h=1100&q=80' : null,
                'meta' => [
                    'nickname' => $nickRo, 'country' => $country, 'weight_class' => $wcRo, 'weight_kg' => $kg, 'discipline' => $disc,
                    'record' => ['w' => $w, 'l' => $l, 'd' => $dr], 'team' => $team, 'height_cm' => $h, 'reach_cm' => $reach,
                    'stance' => $stance, 'is_champion' => $champ,
                    'i18n' => ['en' => array_filter(['nickname' => $nickEn, 'weight_class' => $wcEn])],
                ],
            ]);
            $f->save();
            $out[$key] = $f;
        }
        $this->line('  luptători: ' . count($out));
        return $out;
    }

    private function seedGala(Tenant $tenant, array $g, ?Venue $venue): Event
    {
        $ev = Event::where('slug', $g['slug'])->first();
        if ($ev && (int) $ev->tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('slug-ul aparține altui tenant');
        }
        $date = now()->addDays($g['days'])->toDateString();
        $main = collect($g['card'])->firstWhere(0, 'main_event');
        $defs = collect($this->fighterDefs())->keyBy(0);
        $a = $defs[$main[1]][1] ?? 'TBA';
        $b = $main[2] ? ($defs[$main[2]][1] ?? 'TBA') : 'TBA';
        $last = fn ($n) => Str::afterLast($n, ' ');
        $posters = [14 => '1549719386-74dfcbf7dbed', 15 => '1504450758481-7338eba7524a', 16 => '1622599511051-16f55a1234d0'];
        $title = 'FURIA ' . $g['n'] . ': ' . $last($a) . ' vs ' . $last($b);
        $payload = $this->only('events', [
            'tenant_id' => $tenant->id, 'slug' => $g['slug'], 'venue_id' => $venue?->id,
            'title' => ['ro' => $title, 'en' => $title],
            'subtitle' => ['ro' => $main[3] . ($main[7] ? ' · meci pentru centură' : ''), 'en' => $main[4] . ($main[7] ? ' · title fight' : '')],
            'short_description' => [
                'ro' => 'Gala FURIA ' . $g['n'] . ': ' . count($g['card']) . ' meciuri, main event ' . $a . ' vs ' . $b . '.',
                'en' => 'FURIA ' . $g['n'] . ' fight night: ' . count($g['card']) . ' bouts, main event ' . $a . ' vs ' . $b . '.',
            ],
            'description' => [
                'ro' => '<p>O seară întreagă de MMA și kickbox: preliminariile încep la ' . $g['time'] . ', main card-ul la ' . date('H:i', strtotime($g['time'] . ' +2 hours')) . '.</p><p><em>Gală demonstrativă: luptătorii și meciurile sunt inventate, iar plata trece printr-un procesator de test.</em></p>',
                'en' => '<p>A full night of MMA and kickboxing: prelims start at ' . $g['time'] . ', the main card at ' . date('H:i', strtotime($g['time'] . ' +2 hours')) . '.</p><p><em>Demo event: the fighters and bouts are made up, and payments go through a test processor.</em></p>',
            ],
            'is_published' => true, 'is_cancelled' => false, 'is_sold_out' => false, 'is_indoor' => true, 'is_kid_friendly' => false,
            'duration_mode' => 'single_day', 'event_date' => $date,
            'door_time' => date('H:i', strtotime($g['time'] . ' -60 minutes')), 'start_time' => $g['time'],
            'end_time' => date('H:i', strtotime($g['time'] . ' +5 hours')),
            'poster_url' => self::IMG . $posters[$g['n']] . '?auto=format&fit=crop&w=1200&q=80',
            'hero_image_url' => self::IMG . $posters[$g['n']] . '?auto=format&fit=crop&w=2000&q=80',
            'commission_rate' => 2.00, 'commission_mode' => 'included',
        ]);
        $ev ? $ev->update($payload) : ($ev = Event::create($payload));
        return $ev;
    }

    private function seedCard(Tenant $tenant, Event $ev, array $card, array $fighters, bool $past): void
    {
        EventBout::where('event_id', $ev->id)->delete();
        $order = [];
        foreach ($card as [$section, $a, $b, $wcRo, $wcEn, $disc, $rounds, $title, $titleName, $result]) {
            $n = $order[$section] = ($order[$section] ?? -1) + 1;
            EventBout::create([
                'tenant_id' => $tenant->id, 'event_id' => $ev->id, 'card_section' => $section, 'sort_order' => $n,
                'fighter_a_id' => $fighters[$a]->id ?? null, 'fighter_b_id' => $b ? ($fighters[$b]->id ?? null) : null,
                'fighter_b_name' => $b ? null : 'TBA',
                'weight_class' => $wcRo, 'discipline' => $disc, 'rounds' => $rounds, 'round_minutes' => $disc === 'kickboxing' ? 3 : 5,
                'is_title_fight' => $title, 'title_name' => $titleName[0] ?? null,
                'status' => $past ? 'completed' : 'confirmed',
                'winner' => $result[0] ?? null, 'method' => $result[1] ?? null, 'result_round' => $result[3] ?? null, 'result_time' => $result[4] ?? null,
                'meta' => ['i18n' => ['en' => array_filter(['weight_class' => $wcEn, 'title_name' => $titleName[1] ?? null, 'method' => $result[2] ?? null])]],
            ]);
        }
    }

    private function seedTickets(Event $ev, string $kind): void
    {
        $defs = [
            ['FURIA-PEL', 'Peluză (în picioare)', 'Standing area', 79, 1500, 'Acces în zona în picioare din spatele tribunelor.', 'Access to the standing area behind the stands.', []],
            ['FURIA-T2', 'Tribuna 2', 'Stand 2', 119, 1200, 'Loc pe scaun în tribuna laterală.', 'A seat in the side stand.', []],
            ['FURIA-T1', 'Tribuna 1', 'Stand 1', 179, 800, 'Loc pe scaun în tribuna centrală, cu vedere frontală spre cușcă.', 'A seat in the central stand, facing the cage.', []],
            ['FURIA-RING', 'Ringside', 'Ringside', 349, 150, 'Primele rânduri lângă cușcă.', 'The first rows by the cage.', ['Primele 5 rânduri lângă cușcă', 'Intrare separată'], ],
            ['FURIA-VIP', 'VIP Cageside (open bar)', 'VIP Cageside (open bar)', 649, 40, 'Masă lângă cușcă, open bar și acces la after-party.', 'A table by the cage, open bar and after-party access.', ['Masă lângă cușcă', 'Open bar toată seara', 'Acces la after-party', 'Poză cu campionii']],
        ];
        $perksEn = ['FURIA-RING' => ['First 5 rows by the cage', 'Separate entrance'], 'FURIA-VIP' => ['A table by the cage', 'Open bar all night', 'After-party access', 'Photo with the champions']];
        $existing = TicketType::where('event_id', $ev->id)->get()->keyBy('sku');
        foreach ($defs as $i => [$sku, $ro, $en, $price, $cap, $dRo, $dEn, $perks]) {
            $tt = $existing[$sku] ?? new TicketType(['event_id' => $ev->id]);
            $tt->event_id = $ev->id; $tt->name = $ro; $tt->sku = $sku; $tt->price_max = $price; $tt->is_active = true; $tt->currency = 'RON'; $tt->capacity = $cap;
            foreach ($this->only('ticket_types', ['description' => $dRo, 'perks' => $perks, 'sort_order' => $i + 1, 'min_per_order' => 1, 'max_per_order' => 10,
                'meta' => array_merge((array) ($tt->meta ?? []), ['i18n' => ['en' => ['name' => $en, 'description' => $dEn, 'perks' => $perksEn[$sku] ?? []]]])]) as $c => $v) {
                $tt->{$c} = $v;
            }
            // VIP aproape epuizat, ca să se vadă „ultimele locuri”
            if ($sku === 'FURIA-VIP' && $this->has('ticket_types', 'quota_sold')) { $tt->quota_sold = max((int) ($tt->quota_sold ?? 0), 31); }
            $tt->save();
        }
    }

    private function seedCoupon(Tenant $tenant): void
    {
        if (! Schema::hasTable('coupon_codes') || CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'FURIA10')->exists()) {
            return;
        }
        CouponCode::create($this->only('coupon_codes', ['tenant_id' => $tenant->id, 'code' => 'FURIA10', 'code_type' => 'multi_use',
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
