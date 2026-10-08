<?php

namespace App\Console\Commands;

use App\Enums\TenantType;
use App\Models\Coupon\CouponCode;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Microservice;
use App\Models\Tenant;
use App\Models\TenantEventCategory;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Construiește cap-coadă tenantul demo „Federația Română de Karate WUKF”:
 * owner + tenant + domeniu + client demo + microservicii (cost zero) +
 * locații + categorii + 7 competiții cu tipuri de bilete.
 *
 *   php artisan wukf:seed
 *   php artisan wukf:seed --domain=competitie.tixello.ro --owner-email=wukf@tixello.ro --owner-password=Secret123
 *
 * Idempotent: caută după slug / email / domeniu și actualizează, nu dublează.
 * Pur aditiv: nu șterge nimic și nu atinge alți tenanți. Fără tranzacție
 * globală (pe PostgreSQL o eroare prinsă ar otrăvi toată tranzacția) — fiecare
 * pas opțional e izolat în try/catch și doar avertizează.
 */
class WukfSeed extends Command
{
    protected $signature = 'wukf:seed
        {--domain=competitie.tixello.ro : Hostname-ul site-ului public al tenantului}
        {--owner-email=wukf@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului (goală = generată la creare / păstrată la re-rulare)}
        {--pass-fee= : Mută taxa procesatorului de plăți la cumpărător: „procent” sau „procent,fix_în_bani” (ex. 1.9 sau 1.9,100); „off” o dezactivează}';

    protected $description = 'Seed demo complet pentru tenantul Federația Română de Karate WUKF';

    private const NAME = 'Federația Română de Karate WUKF';
    private const TENANT_SLUG = 'wukf';

    /** Slug microserviciu => slug-uri alternative încercate dacă primul lipsește. */
    private const MICROSERVICES = [
        'ticket-customizer'         => ['ticket-customiser', 'ticket-template-customizer'],
        'tracking-pixels-manager'   => ['tracking-pixels', 'tracking-pixel-manager'],
        'accounting-connectors'     => ['accounting-connector', 'accounting'],
        'invitations'               => ['invitation', 'invites'],
        'door-sales'                => ['door-sale', 'box-office'],
        'coupon-codes'              => ['coupons', 'coupon', 'promo-codes'],
        'facebook-capi-integration' => ['facebook-capi', 'facebook-conversions-api'],
        'shop'                      => ['merch-shop'],
    ];

    private array $colCache = [];

    public function handle(): int
    {
        $domain = strtolower(trim((string) $this->option('domain')));
        $ownerEmail = strtolower(trim((string) $this->option('owner-email')));
        $passwordOpt = (string) ($this->option('owner-password') ?? '');

        if ($domain === '' || ! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Opțiuni invalide: --domain și --owner-email sunt obligatorii.');
            return self::FAILURE;
        }

        $this->info('Seed WUKF — ' . self::NAME);

        // ── Pași obligatorii: orice eroare aici oprește comanda ──────────────
        try {
            // Domeniul nu trebuie să aparțină deja altui tenant.
            $existingTenant = Tenant::where('slug', self::TENANT_SLUG)->first();
            $foreignDomain = Domain::where('domain', $domain)->first();
            if ($foreignDomain && (! $existingTenant || (int) $foreignDomain->tenant_id !== (int) $existingTenant->id)) {
                $this->error("Domeniul {$domain} este deja legat de tenantul #{$foreignDomain->tenant_id}. Oprit, nu am modificat nimic.");
                return self::FAILURE;
            }

            [$user, $passwordShown] = $this->seedOwner($ownerEmail, $passwordOpt);
            $tenant = $this->seedTenant($user, $ownerEmail, $domain);
            $this->seedDomain($tenant, $domain);
        } catch (\Throwable $e) {
            $this->error('Eroare la owner/tenant/domeniu: ' . $e->getMessage());
            $this->line('  ' . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        // ── Pași opționali: avertizează și merge mai departe ─────────────────
        $demoEmail = 'demo@' . $domain;
        $demoPassword = 'demo1234';
        $this->guarded('client demo', fn () => $this->seedCustomer($tenant, $demoEmail, $demoPassword));

        $activated = [];
        $this->guarded('microservicii', function () use ($tenant, &$activated) {
            $activated = $this->seedMicroservices($tenant);
        });

        $tenantCats = [];
        $this->guarded('categorii tenant', function () use ($tenant, &$tenantCats) {
            $tenantCats = $this->seedCategories($tenant);
        });

        $globalType = null;
        $this->guarded('tip global de eveniment', function () use (&$globalType) {
            // Doar refolosim un tip global existent — nu poluăm taxonomia globală.
            $globalType = EventType::whereIn('slug', ['competitie-sportiva', 'sport-fitness', 'sport'])
                ->orderByRaw("CASE slug WHEN 'competitie-sportiva' THEN 0 WHEN 'sport-fitness' THEN 1 ELSE 2 END")
                ->first();
            if (! $globalType) {
                $this->warn('  niciun EventType global de sport găsit (competitie-sportiva / sport-fitness) — sar peste.');
            }
        });

        $venues = [];
        foreach ($this->venues() as $key => $v) {
            $this->guarded("locație {$key}", function () use ($tenant, $key, $v, &$venues) {
                $venues[$key] = $this->seedVenue($tenant, $v);
            });
        }

        $events = [];
        foreach ($this->events() as $e) {
            $this->guarded("eveniment {$e['slug']}", function () use ($tenant, $e, $venues, $tenantCats, $globalType, &$events) {
                $event = $this->seedEvent($tenant, $e, $venues[$e['venue']] ?? null, $tenantCats[$e['cat']] ?? null, $globalType);
                if ($event) {
                    $events[] = $event;
                }
            });
        }

        $this->guarded('cod de reducere demo', fn () => $this->seedCoupon($tenant));

        $passFee = $this->option('pass-fee');
        if ($passFee !== null && $passFee !== '') {
            $this->guarded('taxa de procesare', fn () => $this->seedPaymentFee($tenant, (string) $passFee));
        }

        $this->guarded('cache', function () use ($tenant, $domain) {
            Cache::forget("domain_tenant_{$domain}");
            Cache::forget("tenant_{$tenant->id}");
            Cache::forget("tenant_categories_{$tenant->id}");
        });

        // ── Rezumat ──────────────────────────────────────────────────────────
        $base = rtrim((string) (config('app.url') ?: 'https://core.tixello.com'), '/');
        if (! str_contains($base, 'tixello')) {
            $base = 'https://core.tixello.com';
        }

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Tenant ID:        ' . $tenant->id . '  (slug ' . $tenant->slug . ')');
        $this->line('  Owner email:      ' . $ownerEmail);
        $this->line('  Owner parolă:     ' . $passwordShown);
        $this->line('  Panou tenant:     ' . $base . '/tenant/login');
        $this->line('  Site public:      https://' . $domain . '/');
        $this->line('  Client demo:      ' . $demoEmail . ' / ' . $demoPassword);
        $this->line('  API listă:        https://core.tixello.com/api/tenant-client/events?tenant=' . $tenant->id);
        $this->line('  Microservicii:    ' . ($activated ? implode(', ', $activated) : '(niciunul)'));
        $fresh = $tenant->fresh();
        $fees = is_array($fresh->settings) ? ($fresh->settings['payment_fees'] ?? null) : null;
        $this->line('  Comision Tixello: ' . $fresh->commission_rate . '% (' . $fresh->commission_mode . ')');
        $this->line('  Taxă procesare:   ' . (! empty($fees['pass_to_customer'])
            ? 'mutată la cumpărător — ' . ($fees['percent_rate'] ?? 0) . '% + ' . number_format(($fees['fixed_cents'] ?? 0) / 100, 2) . ' lei'
            : 'suportată de organizator (--pass-fee=1.9,100 o mută la cumpărător)'));
        $this->line('  Cod reducere:     KARATE10 (10% din bilete, pentru test)');
        $this->line('  Evenimente (' . count($events) . '):');
        foreach ($events as $ev) {
            $this->line('    #' . $ev->id . '  ' . $ev->slug);
            $this->line('         https://core.tixello.com/api/tenant-client/events/' . $ev->slug . '?tenant=' . $tenant->id);
        }

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */
    /* Owner                                                               */
    /* ------------------------------------------------------------------ */

    /** @return array{0: User, 1: string} */
    private function seedOwner(string $email, string $passwordOpt): array
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            $shown = '(neschimbată — utilizator existent)';
            if ($passwordOpt !== '') {
                $user->password = Hash::make($passwordOpt);
                $shown = $passwordOpt;
            }
            if (empty($user->role)) {
                $user->role = 'tenant';
            } elseif ($user->role !== 'tenant') {
                $this->warn("  utilizatorul {$email} are rolul „{$user->role}” — NU îl schimb în „tenant” (panoul /tenant cere rolul tenant).");
            }
            if ($user->isDirty()) {
                $user->save();
            }
            $this->line("  owner existent: #{$user->id} {$email}");

            return [$user, $shown];
        }

        $password = $passwordOpt !== '' ? $passwordOpt : Str::random(14);

        // La fel ca OnboardingController: doar coloana `role` (fără spatie).
        $user = User::create($this->only('users', [
            'name'     => self::NAME,
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => 'tenant',
        ]));

        if ($this->has('users', 'email_verified_at')) {
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        }

        $this->line("  owner creat: #{$user->id} {$email}");

        return [$user, $password];
    }

    /* ------------------------------------------------------------------ */
    /* Tenant                                                              */
    /* ------------------------------------------------------------------ */

    private function seedTenant(User $user, string $ownerEmail, string $domain): Tenant
    {
        $tenant = Tenant::where('slug', self::TENANT_SLUG)->first();

        // tenants.domain e UNIQUE: îl setăm doar dacă nu-l folosește alt tenant.
        $domainTaken = Tenant::where('domain', $domain)
            ->when($tenant, fn ($q) => $q->where('id', '!=', $tenant->id))
            ->exists();

        $data = [
            'name'                 => self::NAME,
            'public_name'          => self::NAME,
            'company_name'         => self::NAME,
            'owner_id'             => $user->id,
            'slug'                 => self::TENANT_SLUG,
            'ticket_series_prefix' => 'WUKF',
            'status'               => 'active',
            // Organizator de competiții sportive (nu teatru / leisure).
            'tenant_type'          => TenantType::Competition,
            // 2% = work_method „mixed” / plan „2percent” (maparea din OnboardingController).
            'plan'                 => '2percent',
            'work_method'          => 'mixed',
            'commission_rate'      => 2.00,
            // „included” = comisionul NU se adaugă peste prețul biletului (îl suportă organizatorul).
            'commission_mode'      => 'included',
            'locale'               => 'ro',
            'currency'             => 'RON',
            'country'              => 'RO',
            'city'                 => 'București',
            'website'              => 'https://www.wukf.ro',
            'contact_email'        => $ownerEmail,
            'has_own_website'      => false,
            // Emailurile pleacă prin configurația de mail a platformei (Brevo-ul Tixello)
            'use_core_smtp'        => true,
            'onboarding_completed' => true,
            'billing_cycle_days'   => 30,
        ];
        if (! $domainTaken) {
            $data['domain'] = $domain;
        } else {
            $this->warn("  tenants.domain={$domain} e folosit de alt tenant — las coloana neatinsă (rezolvarea se face prin tabela domains).");
        }

        $siteSettings = [
            'site_title'    => self::NAME,
            'site_language' => 'ro',
            'site_tagline'  => 'Bilete la competițiile de karate WUKF',
        ];
        // Site-ul public: email de confirmare cu biletele + identitatea folosită în emailuri
        $storefront = [
            'order_emails' => true,
            'brand_dark'   => '#01012F',
            'brand_color'  => '#1151D3',
            'logo_url'     => 'https://' . $domain . '/assets/logo-wukf.png',
        ];

        if (! $tenant) {
            $data['settings'] = $siteSettings + ['storefront' => $storefront];
            $data['onboarding_completed_at'] = now();
            $data['billing_starts_at'] = now();
            $data['next_billing_date'] = now()->addDays(30)->toDateString();
            // payment_processor rămâne NULL (procesatorul nu e ales încă).

            if ($domainTaken && ! $this->nullable('tenants', 'domain')) {
                // Coloană NOT NULL + valoarea e ocupată: folosim un placeholder unic.
                $data['domain'] = self::TENANT_SLUG . '.' . $domain;
            }

            $tenant = Tenant::create($this->only('tenants', $data));
            $this->line("  tenant creat: #{$tenant->id}");

            return $tenant;
        }

        // Re-rulare: nu resetăm ciclul de facturare și nu călcăm setările existente.
        $data['settings'] = array_merge($siteSettings, is_array($tenant->settings) ? $tenant->settings : []);
        $data['settings']['storefront'] = array_merge($storefront, is_array($data['settings']['storefront'] ?? null) ? $data['settings']['storefront'] : []);
        if (empty($tenant->onboarding_completed_at)) {
            $data['onboarding_completed_at'] = now();
        }
        if (empty($tenant->billing_starts_at)) {
            $data['billing_starts_at'] = now();
        }
        if (empty($tenant->next_billing_date)) {
            $data['next_billing_date'] = now()->addDays(30)->toDateString();
        }
        if (! empty($tenant->ticket_series_prefix)) {
            unset($data['ticket_series_prefix']);
        }

        $tenant->update($this->only('tenants', $data));
        $this->line("  tenant existent actualizat: #{$tenant->id}");

        return $tenant;
    }

    /* ------------------------------------------------------------------ */
    /* Domeniu                                                             */
    /* ------------------------------------------------------------------ */

    private function seedDomain(Tenant $tenant, string $domain): void
    {
        $row = Domain::where('domain', $domain)->first();

        $data = $this->only('domains', [
            'tenant_id'    => $tenant->id,
            'domain'       => $domain,
            'is_active'    => true,
            'is_suspended' => false,
            'is_primary'   => true,
        ]);

        if ($row) {
            $row->update($data);
        } else {
            $row = Domain::create($data);
        }

        if ($this->has('domains', 'activated_at') && empty($row->activated_at)) {
            $row->forceFill(['activated_at' => now()])->saveQuietly();
        }

        $this->line("  domeniu: {$domain} -> tenant #{$tenant->id} (activ, primar)");
    }

    /* ------------------------------------------------------------------ */
    /* Client demo                                                         */
    /* ------------------------------------------------------------------ */

    private function seedCustomer(Tenant $tenant, string $email, string $password): void
    {
        $customer = Customer::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        $data = $this->only('customers', [
            'tenant_id'         => $tenant->id,
            'primary_tenant_id' => $tenant->id,
            'email'             => $email,
            'first_name'        => 'Client',
            'last_name'         => 'Demo',
            'phone'             => '0700000000',
            'password'          => Hash::make($password),
        ]);

        if ($customer) {
            if (method_exists($customer, 'trashed') && $customer->trashed()) {
                $customer->restore();
            }
            $customer->update($data);
        } else {
            $customer = Customer::create($data);
        }

        if ($this->has('customers', 'email_verified_at') && empty($customer->email_verified_at)) {
            $customer->forceFill(['email_verified_at' => now()])->saveQuietly();
        }

        try {
            $customer->tenants()->syncWithoutDetaching([$tenant->id]);
        } catch (\Throwable $e) {
            // pivotul customer_tenant poate lipsi — tenant_id direct e suficient
        }

        $this->line("  client demo: #{$customer->id} {$email}");
    }

    /* ------------------------------------------------------------------ */
    /* Microservicii                                                       */
    /* ------------------------------------------------------------------ */

    /** @return string[] slug-urile activate */
    private function seedMicroservices(Tenant $tenant): array
    {
        $table = 'tenant_microservices';
        if (! Schema::hasTable($table)) {
            $this->warn("  tabela {$table} lipsește — sar peste microservicii.");
            return [];
        }

        $cols = $this->cols($table);

        // is_active poate fi coloană GENERATĂ din status (nu se poate scrie) sau reală (legacy).
        $isActiveWritable = false;
        if (in_array('is_active', $cols, true)) {
            $isActiveWritable = true;
            try {
                foreach (Schema::getColumns($table) as $c) {
                    if (($c['name'] ?? null) === 'is_active' && ! empty($c['generation'])) {
                        $isActiveWritable = false;
                    }
                }
            } catch (\Throwable $e) {
                // Nu putem introspecta: dacă există `status`, presupunem coloană generată.
                $isActiveWritable = ! in_array('status', $cols, true);
            }
        }

        // tenant_id e string în migrarea nouă, bigint în tabela veche.
        $tenantKey = $tenant->id;
        try {
            $type = strtolower((string) Schema::getColumnType($table, 'tenant_id'));
            if (str_contains($type, 'char') || str_contains($type, 'string') || str_contains($type, 'text')) {
                $tenantKey = (string) $tenant->id;
            }
        } catch (\Throwable $e) {
            // păstrăm int
        }

        $activated = [];

        foreach (self::MICROSERVICES as $slug => $alternates) {
            try {
                $ms = Microservice::where('slug', $slug)->first();
                if (! $ms) {
                    foreach ($alternates as $alt) {
                        $ms = Microservice::where('slug', $alt)->first();
                        if ($ms) {
                            $this->warn("  microserviciu „{$slug}” negăsit — folosesc alternativa „{$alt}”.");
                            break;
                        }
                    }
                }
                if (! $ms) {
                    $this->warn("  microserviciu „{$slug}” negăsit (nici alternativele) — sar peste.");
                    continue;
                }

                $now = now();
                $values = [];
                if (in_array('status', $cols, true)) {
                    $values['status'] = 'active';
                }
                if ($isActiveWritable) {
                    $values['is_active'] = true;
                }
                // Cost zero: preț lunar suprascris la 0 pe pivot, fără expirare / trial / facturare.
                foreach (['monthly_price' => 0, 'expires_at' => null, 'trial_ends_at' => null, 'cancelled_at' => null,
                          'cancellation_reason' => null, 'next_billing_at' => null, 'deleted_at' => null] as $col => $val) {
                    if (in_array($col, $cols, true)) {
                        $values[$col] = $val;
                    }
                }
                if (in_array('updated_at', $cols, true)) {
                    $values['updated_at'] = $now;
                }

                $existing = DB::table($table)
                    ->where('tenant_id', $tenantKey)
                    ->where('microservice_id', $ms->id)
                    ->first();

                if ($existing) {
                    if (in_array('activated_at', $cols, true) && empty($existing->activated_at)) {
                        $values['activated_at'] = $now;
                    }
                    DB::table($table)
                        ->where('tenant_id', $tenantKey)
                        ->where('microservice_id', $ms->id)
                        ->update($values);
                } else {
                    $values['tenant_id'] = $tenantKey;
                    $values['microservice_id'] = $ms->id;
                    if (in_array('activated_at', $cols, true)) {
                        $values['activated_at'] = $now;
                    }
                    if (in_array('created_at', $cols, true)) {
                        $values['created_at'] = $now;
                    }
                    DB::table($table)->insert($values);
                }

                $activated[] = $ms->slug;
                $this->line("  microserviciu activ (0 lei): {$ms->slug}");
            } catch (\Throwable $e) {
                $this->warn("  microserviciu „{$slug}”: " . $e->getMessage());
            }
        }

        return $activated;
    }

    /* ------------------------------------------------------------------ */
    /* Cod de reducere + taxa de procesare                                 */
    /* ------------------------------------------------------------------ */

    /** Un cod de test (10%), ca reducerea să poată fi încercată imediat pe site. */
    private function seedCoupon(Tenant $tenant): void
    {
        if (! Schema::hasTable('coupon_codes')) {
            $this->warn('  tabela coupon_codes lipsește — sar peste codul de reducere.');
            return;
        }

        $coupon = CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'KARATE10')->first();
        if ($coupon) {
            if ($coupon->trashed()) {
                $coupon->restore();
            }
            $this->line('  cod de reducere existent: KARATE10 (' . $coupon->status . ')');
            return;
        }

        CouponCode::create($this->only('coupon_codes', [
            'tenant_id'      => $tenant->id,
            'code'           => 'KARATE10',
            'code_type'      => 'multi_use',
            'discount_type'  => 'percentage',
            'discount_value' => 10,
            'status'         => 'active',
            'is_public'      => false,
            'combinable'     => false,
            'source'         => 'demo',
        ]));
        $this->line('  cod de reducere creat: KARATE10 (10%)');
    }

    /**
     * settings.payment_fees = {pass_to_customer, percent_rate, fixed_cents}, citit de checkout-ul
     * demo. $spec: „off” sau „procent[,fix_în_bani]”.
     */
    private function seedPaymentFee(Tenant $tenant, string $spec): void
    {
        $tenant = $tenant->fresh();
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $spec = strtolower(trim($spec));

        if (in_array($spec, ['off', '0', 'no', 'false'], true)) {
            $settings['payment_fees'] = ['pass_to_customer' => false, 'percent_rate' => 0, 'fixed_cents' => 0];
            $this->line('  taxa de procesare: suportată de organizator');
        } else {
            [$percent, $fixed] = array_pad(explode(',', $spec, 2), 2, '0');
            $percent = (float) str_replace(',', '.', $percent);
            $fixed = (int) $fixed;
            if ($percent < 0 || $percent > 20 || $fixed < 0 || $fixed > 2000) {
                $this->warn('  --pass-fee invalid (aștept ex. 1.9 sau 1.9,100) — sar peste.');
                return;
            }
            $settings['payment_fees'] = ['pass_to_customer' => true, 'percent_rate' => $percent, 'fixed_cents' => $fixed];
            $this->line("  taxa de procesare: mutată la cumpărător ({$percent}% + {$fixed} bani)");
        }

        $tenant->update(['settings' => $settings]);
    }

    /* ------------------------------------------------------------------ */
    /* Categorii                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array<string, TenantEventCategory> */
    private function seedCategories(Tenant $tenant): array
    {
        $defs = [
            'cupe-nationale'       => ['name' => 'Cupe naționale', 'icon' => 'trophy'],
            'campionate-nationale' => ['name' => 'Campionate naționale', 'icon' => 'star'],
            'competitii-open'      => ['name' => 'Competiții open', 'icon' => 'globe-alt'],
        ];

        $out = [];
        $order = 1;
        foreach ($defs as $slug => $def) {
            $out[$slug] = TenantEventCategory::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name'       => ['ro' => $def['name'], 'en' => $def['name']],
                    'icon'       => $def['icon'],
                    'is_active'  => true,
                    'sort_order' => $order++,
                ]
            );
        }
        $this->line('  categorii tenant: ' . implode(', ', array_keys($out)));

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Locații                                                             */
    /* ------------------------------------------------------------------ */

    private function venues(): array
    {
        return [
            'ghimbav' => [
                'slug' => 'ghimbav-sport-arena-wukf', 'name' => 'Ghimbav Sport Arena',
                'city' => 'Ghimbav', 'state' => 'Brașov', 'address' => 'Ghimbav, jud. Brașov',
            ],
            'turda' => [
                'slug' => 'sala-sport-gheorghe-baritiu-turda-wukf', 'name' => 'Sala de Sport „Gheorghe Barițiu”',
                'city' => 'Turda', 'state' => 'Cluj', 'address' => 'Turda, jud. Cluj',
            ],
            'vladimirescu' => [
                'slug' => 'sala-sport-vladimirescu-wukf', 'name' => 'Sala de Sport Vladimirescu',
                'city' => 'Vladimirescu', 'state' => 'Arad', 'address' => 'Comuna Vladimirescu, jud. Arad',
            ],
            'gradistei' => [
                'slug' => 'complex-cheile-gradistei-fundata-wukf', 'name' => 'Complex Cheile Grădiștei',
                'city' => 'Fundata', 'state' => 'Brașov', 'address' => 'Fundata, jud. Brașov',
            ],
            'cnav' => [
                'slug' => 'colegiul-national-aurel-vlaicu-bucuresti-wukf', 'name' => 'Colegiul Național „Aurel Vlaicu”',
                'city' => 'București', 'state' => 'București', 'address' => 'Sala de sport a Colegiului Național „Aurel Vlaicu”, București',
            ],
            'medias' => [
                'slug' => 'sala-sporturilor-medias-wukf', 'name' => 'Sala Sporturilor Mediaș',
                'city' => 'Mediaș', 'state' => 'Sibiu', 'address' => 'Mediaș, jud. Sibiu',
            ],
        ];
    }

    private function seedVenue(Tenant $tenant, array $v): ?Venue
    {
        // venues.slug e unic global → căutăm doar după slug.
        $venue = Venue::where('slug', $v['slug'])->first();

        if ($venue && (int) $venue->tenant_id !== (int) $tenant->id) {
            $this->warn("  locația {$v['slug']} există la alt tenant (#{$venue->tenant_id}) — o refolosesc fără s-o modific.");
            return $venue;
        }

        $data = $this->only('venues', [
            'tenant_id' => $tenant->id,
            'name'      => ['ro' => $v['name'], 'en' => $v['name']],
            'slug'      => $v['slug'],
            'city'      => $v['city'],
            'state'     => $v['state'],
            'address'   => $v['address'],
        ]);

        if ($venue) {
            $venue->update($data);
            $this->line("  locație existentă: #{$venue->id} {$v['name']}");
        } else {
            $venue = Venue::create($data);
            $this->line("  locație creată: #{$venue->id} {$v['name']}");
        }

        return $venue;
    }

    /* ------------------------------------------------------------------ */
    /* Evenimente                                                          */
    /* ------------------------------------------------------------------ */

    private function events(): array
    {
        $footer = '<p>Biletele de spectator se cumpără online și se prezintă la intrare pe telefon sau tipărite. '
            . 'Programul detaliat pe categorii și suprafețe de concurs este cel din invitația oficială publicată de '
            . 'Federația Română de Karate WUKF; înscrierea sportivilor se face separat, prin cluburi.</p>';

        // În ordine cronologică.
        return [
            [
                'slug'   => 'cupa-romaniei-karate-echipe-wukf',
                'title'  => 'Cupa României Karate WUKF – echipe',
                'venue'  => 'ghimbav', 'cat' => 'cupe-nationale',
                'start'  => '2026-11-14', 'end' => '2026-11-15',
                'poster' => 'https://www.wukf.ro/wp-content/uploads/2025/10/Afis-.jpg',
                'src'    => 'https://www.wukf.ro/evenimente/nationale/cupa-romaniei-echipe-wukf-2025/',
                'short'  => 'Două zile de karate pe echipe la Ghimbav Sport Arena, în competiția națională de cupă a Federației Române de Karate WUKF.',
                'body'   => '<p>Cupa României Karate WUKF – echipe reunește la Ghimbav Sport Arena, lângă Brașov, echipele cluburilor afiliate Federației Române de Karate WUKF, într-un weekend dedicat exclusiv probelor pe echipe.</p>'
                    . '<ul><li>Competiție națională de cupă, rezervată probelor pe echipe</li><li>Două zile de concurs: sâmbătă și duminică</li><li>Categorii de vârstă și probe conform regulamentului și listei de categorii publicate de federație</li></ul>'
                    . '<p>Din tribună vezi cum se construiește un rezultat de echipă: sincronizare, schimburi rapide și susținerea colegilor de pe marginea suprafeței de concurs.</p>'
                    . $footer,
            ],
            [
                'slug'   => 'cupa-potaissa-wukf',
                'title'  => 'Cupa Potaissa',
                'venue'  => 'turda', 'cat' => 'competitii-open',
                'start'  => '2027-01-30', 'end' => '2027-01-31',
                'poster' => null,
                'src'    => 'https://www.wukf.ro/evenimente/cupa-potaissa-2025/',
                'short'  => 'Competiția de karate care deschide anul la Turda, în Sala de Sport „Gheorghe Barițiu”.',
                'body'   => '<p>Cupa Potaissa aduce la Turda sportivi de la cluburile de karate din țară, pentru prima competiție importantă a sezonului din calendarul Federației Române de Karate WUKF.</p>'
                    . '<ul><li>Două zile de concurs în Sala de Sport „Gheorghe Barițiu”</li><li>Categorii stabilite prin invitația și lista de categorii publicate de organizatori</li><li>Atmosferă de sală plină, cu familii și susținători în tribune</li></ul>'
                    . '<p>Este un bun prilej să vezi karate de aproape, de la cei mai mici sportivi până la categoriile de seniori.</p>'
                    . $footer,
            ],
            [
                'slug'   => 'cupa-vladimirescu-open-wukf',
                'title'  => 'Cupa Vladimirescu Open',
                'venue'  => 'vladimirescu', 'cat' => 'competitii-open',
                'start'  => '2027-03-06', 'end' => '2027-03-07',
                'poster' => 'https://www.wukf.ro/wp-content/uploads/2025/02/Afis.jpeg',
                'src'    => 'https://www.wukf.ro/evenimente/cupa-vladimirescu-open-2025/',
                'short'  => 'Competiție open de karate în Sala de Sport a comunei Vladimirescu, lângă Arad.',
                'body'   => '<p>Cupa Vladimirescu Open este o competiție deschisă de karate găzduită de Sala de Sport a comunei Vladimirescu, județul Arad, în calendarul Federației Române de Karate WUKF.</p>'
                    . '<ul><li>Format open, pe parcursul a două zile</li><li>Categorii conform listei publicate de organizatori</li><li>Sală accesibilă, la câțiva kilometri de Arad</li></ul>'
                    . '<p>Formatul open înseamnă multe cluburi și multe meciuri într-un singur weekend — un program plin pentru spectatori.</p>'
                    . $footer,
            ],
            [
                'slug'   => 'campionatele-nationale-copii-wukf',
                'title'  => 'Campionatele Naționale pentru copii – individual și echipe',
                'venue'  => 'gradistei', 'cat' => 'campionate-nationale',
                'start'  => '2027-03-20', 'end' => '2027-03-21',
                'poster' => null,
                'src'    => 'https://www.wukf.ro/evenimente/campionatele-natioanle-pentru-copii-categoriile-individuale-si-pe-echipe/',
                'short'  => 'Campionatele Naționale de karate WUKF pentru copii, la categoriile individuale și pe echipe, la Cheile Grădiștei – Fundata.',
                'body'   => '<p>Campionatele Naționale pentru copii sunt competiția la care cei mai tineri sportivi ai Federației Române de Karate WUKF concurează pentru titlurile naționale, atât la categoriile individuale, cât și pe echipe.</p>'
                    . '<ul><li>Categorii individuale și categorii pe echipe</li><li>Două zile de concurs la Complexul Cheile Grădiștei, Fundata (Brașov)</li><li>Categorii de vârstă conform listelor publicate de federație</li></ul>'
                    . '<p>Pentru părinți și bunici este competiția anului: emoții mari, mult fair-play și primii pași spre performanță.</p>'
                    . $footer,
            ],
            [
                'slug'   => 'cupa-cnav-wukf',
                'title'  => 'Cupa C.N.A.V. WUKF',
                'venue'  => 'cnav', 'cat' => 'cupe-nationale',
                'start'  => '2027-04-03', 'end' => null,
                'poster' => 'https://www.wukf.ro/wp-content/uploads/2026/03/Afis-.jpeg',
                'src'    => 'https://www.wukf.ro/evenimente/nationale/cupa-c-n-a-v-wukf-2026/',
                'short'  => 'O zi de karate în sala de sport a Colegiului Național „Aurel Vlaicu” din București.',
                'body'   => '<p>Cupa C.N.A.V. WUKF se desfășoară într-o singură zi, în sala de sport a Colegiului Național „Aurel Vlaicu” din București, sub egida Federației Române de Karate WUKF.</p>'
                    . '<ul><li>Competiție de o zi, în București</li><li>Categorii conform invitației și listei de categorii publicate de organizatori</li><li>Sală de școală, cu tribuna aproape de suprafețele de concurs</li></ul>'
                    . '<p>Fiind o competiție de o zi, biletul de spectator este valabil pentru întregul program.</p>'
                    . $footer,
            ],
            [
                'slug'   => 'campionatul-national-13-ani-wukf',
                'title'  => 'Campionatul Național Karate WUKF +13 ani',
                'venue'  => 'medias', 'cat' => 'campionate-nationale',
                'start'  => '2027-05-22', 'end' => '2027-05-23',
                'poster' => 'https://www.wukf.ro/wp-content/uploads/2025/04/Afis-Campionat-National-13-ANI-MEDIAS-scaled.jpg',
                'src'    => 'https://www.wukf.ro/evenimente/nationale/campionatul-national-13-ani-karate-wukf-2025/',
                'short'  => 'Campionatul Național de karate WUKF pentru sportivii de peste 13 ani, la Sala Sporturilor din Mediaș.',
                'body'   => '<p>Campionatul Național Karate WUKF +13 ani desemnează campionii naționali ai Federației Române de Karate WUKF la categoriile de vârstă de la 13 ani în sus.</p>'
                    . '<ul><li>Competiție națională pentru sportivi de peste 13 ani</li><li>Două zile de concurs la Sala Sporturilor Mediaș</li><li>Probe și categorii conform listei oficiale de categorii a campionatului</li></ul>'
                    . '<p>Este competiția cu cea mai mare miză din calendarul intern: aici se joacă titlurile naționale ale anului.</p>'
                    . $footer,
            ],
            [
                'slug'   => 'cupa-romaniei-13-ani-individual-wukf',
                'title'  => 'Cupa României Karate WUKF +13 ani – individual',
                'venue'  => 'gradistei', 'cat' => 'cupe-nationale',
                'start'  => '2027-09-11', 'end' => '2027-09-12',
                'poster' => 'https://www.wukf.ro/wp-content/uploads/2026/08/Afis-Cupa-Romaniei.jpeg',
                'src'    => 'https://www.wukf.ro/evenimente/nationale/cupa-romaniei-karate-wukf-13-ani/',
                'short'  => 'Cupa României la karate și kobudo pentru sportivii de peste 13 ani, la Sala Sporturilor de la Cheile Grădiștei – Fundata.',
                'body'   => '<p>Cupa României Karate WUKF +13 ani – individual deschide sezonul de toamnă la Sala Sporturilor din complexul Cheile Grădiștei, Fundata, cu probe individuale pentru sportivii de peste 13 ani.</p>'
                    . '<ul><li>Probe individuale de karate și kobudo</li><li>Două zile de concurs, sâmbătă și duminică</li><li>Categorii conform listei oficiale și regulamentului publicate de federație</li></ul>'
                    . '<p>Decorul montan de la Fundata face din acest weekend și o ieșire de familie, nu doar o competiție.</p>'
                    . $footer,
            ],
        ];
    }

    private function seedEvent(Tenant $tenant, array $e, ?Venue $venue, ?TenantEventCategory $cat, ?EventType $globalType): ?Event
    {
        // events.slug e unic global.
        $event = Event::where('slug', $e['slug'])->first();
        if ($event && (int) $event->tenant_id !== (int) $tenant->id) {
            $this->warn("  slug-ul {$e['slug']} aparține altui tenant (#{$event->tenant_id}) — sar peste.");
            return null;
        }
        $isNew = ! $event;
        $isRange = ! empty($e['end']);

        // Poster: nu re-descărcăm dacă fișierul există deja.
        $posterPath = null;
        if (! empty($e['poster'])) {
            $current = $event?->poster_url;
            if ($current && ! preg_match('#^https?://#', $current) && $this->fileExists($current)) {
                $posterPath = $current;
            } else {
                $posterPath = $this->dl($e['poster'], 'event-posters', $e['slug'] . '-poster');
                if (! $posterPath) {
                    $this->warn("  poster nedescărcat pentru {$e['slug']} — rămâne fără imagine.");
                }
            }
        }

        $payload = [
            'tenant_id'         => $tenant->id,
            'title'             => ['ro' => $e['title'], 'en' => $e['title']],
            'slug'              => $e['slug'],
            'venue_id'          => $venue?->id,
            'is_published'      => true,
            'is_cancelled'      => false,
            'is_sold_out'       => false,
            'short_description' => ['ro' => $e['short'], 'en' => $e['short']],
            'description'       => ['ro' => $e['body'], 'en' => $e['body']],
            'event_website_url' => $e['src'],
            'is_indoor'         => true,
            'is_kid_friendly'   => true,
            // Explicit pe eveniment: altfel API-ul public raportează comisionul implicit de 5%.
            'commission_rate'   => 2.00,
            'commission_mode'   => 'included',
        ];

        if ($isRange) {
            $payload += [
                'duration_mode'    => 'range',
                'range_start_date' => $e['start'],
                'range_end_date'   => $e['end'],
                'range_start_time' => '09:00',
                'range_end_time'   => '18:00',
            ];
        } else {
            $payload += [
                'duration_mode' => 'single_day',
                'event_date'    => $e['start'],
                'door_time'     => '08:30',
                'start_time'    => '09:00',
                'end_time'      => '18:00',
            ];
        }

        if ($posterPath) {
            $payload['poster_url'] = $posterPath;
            $payload['hero_image_url'] = $posterPath;
        }

        $payload = $this->only('events', $payload);

        if ($isNew) {
            $event = Event::create($payload);
        } else {
            $event->update($payload);
        }

        try {
            if ($cat) {
                $event->tenantEventCategories()->syncWithoutDetaching([$cat->id]);
            }
        } catch (\Throwable $ex) {
            $this->warn("  categorie tenant neatașată la {$e['slug']}: " . $ex->getMessage());
        }
        try {
            if ($globalType) {
                $event->eventTypes()->syncWithoutDetaching([$globalType->id]);
            }
        } catch (\Throwable $ex) {
            $this->warn("  tip global neatașat la {$e['slug']}: " . $ex->getMessage());
        }

        try {
            $this->seedTicketTypes($event, $isRange);
        } catch (\Throwable $ex) {
            $this->warn("  bilete pentru {$e['slug']}: " . $ex->getMessage());
        }

        $this->line(($isNew ? '  eveniment creat: ' : '  eveniment actualizat: ') . "{$e['title']} (#{$event->id}) — {$e['start']}" . ($isRange ? " → {$e['end']}" : ''));

        return $event;
    }

    /* ------------------------------------------------------------------ */
    /* Tipuri de bilete                                                    */
    /* ------------------------------------------------------------------ */

    private function seedTicketTypes(Event $event, bool $isRange): void
    {
        // Eveniment cu sală pe locuri (wukf:seed-seating): biletele de acces general nu se mai vând — nu le recrea / reactiva.
        if (! empty($event->seating_layout_id)) {
            return;
        }

        $defs = [
            ['name' => 'Spectator – 1 zi', 'price' => 25, 'cap' => 400, 'range_only' => false,
                'desc' => 'Acces în tribună pentru o singură zi de concurs.'],
            ['name' => 'Spectator – ambele zile', 'price' => 40, 'cap' => 400, 'range_only' => true,
                'desc' => 'Acces în tribună în ambele zile de concurs.'],
            ['name' => 'Copil / Elev (7–14 ani)', 'price' => 15, 'cap' => 200, 'range_only' => false,
                'desc' => 'Bilet redus pentru copii și elevi între 7 și 14 ani.'],
            ['name' => 'Familie (2 adulți + 2 copii)', 'price' => 60, 'cap' => 100, 'range_only' => false,
                'desc' => 'Un singur bilet pentru doi adulți și doi copii.'],
        ];

        // Potrivire în PHP (coloana name poate fi text sau json, în funcție de mediu).
        $existing = TicketType::where('event_id', $event->id)->get();
        $nameOf = function (TicketType $tt): string {
            $n = $tt->name;
            if (is_string($n) && str_starts_with(ltrim($n), '{')) {
                $decoded = json_decode($n, true);
                if (is_array($decoded)) {
                    $n = $decoded;
                }
            }
            if (is_array($n)) {
                $n = $n['ro'] ?? $n['en'] ?? (string) reset($n);
            }
            return trim((string) $n);
        };

        $order = 1;
        foreach ($defs as $def) {
            if ($def['range_only'] && ! $isRange) {
                continue;
            }

            $tt = $existing->first(fn (TicketType $t) => $nameOf($t) === $def['name']);
            $creating = ! $tt;
            if ($creating) {
                $tt = new TicketType();
                $tt->event_id = $event->id;
                $tt->name = $def['name'];
            }

            // Mutatori virtuali din model:
            //   price_max -> price_cents, capacity -> quota_total, is_active -> status ('active')
            $tt->price_max = $def['price'];
            $tt->is_active = true;
            $tt->currency = 'RON';

            $sold = (int) ($tt->quota_sold ?? 0);
            $tt->capacity = max($def['cap'], $sold);

            foreach ($this->only('ticket_types', [
                'description'   => $def['desc'],
                'sort_order'    => $order,
                'min_per_order' => 1,
                'max_per_order' => 10,
            ]) as $col => $val) {
                $tt->{$col} = $val;
            }

            if ($creating && $this->has('ticket_types', 'quota_sold')) {
                $tt->quota_sold = 0;
            }

            // Fereastra de vânzare rămâne deschisă: fără sales_start_at / sales_end_at /
            // scheduled_at / active_until, fără preț promoțional.
            $tt->save();
            $order++;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Utilitare                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Descarcă o imagine pe disk-ul public și întoarce calea RELATIVĂ
     * (compatibil FileUpload). Păstrează extensia reală. Null dacă eșuează.
     */
    private function dl(string $url, string $dir, string $name): ?string
    {
        try {
            if (! function_exists('curl_init')) {
                return null;
            }

            $fetch = function (bool $verify) use ($url) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 5,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => $verify,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; wukf-seed/1.0)',
                ]);
                $data = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                return [$data, $code];
            };

            [$data, $code] = $fetch(true);
            if ($data === false || $code === 0) {
                [$data, $code] = $fetch(false);
            }
            if ($data === false || $code >= 400 || strlen((string) $data) < 500) {
                return null;
            }

            // Extensia: din conținut dacă se poate, altfel din URL.
            $ext = null;
            $info = @getimagesizefromstring((string) $data);
            if (is_array($info) && ! empty($info[2])) {
                $ext = match ($info[2]) {
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_PNG  => 'png',
                    IMAGETYPE_GIF  => 'gif',
                    IMAGETYPE_WEBP => 'webp',
                    default        => null,
                };
            } elseif ($info === false) {
                // Nu e imagine (ex. pagină HTML de eroare).
                return null;
            }
            if (! $ext) {
                $urlExt = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $ext = in_array($urlExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $urlExt : 'jpg';
            }

            $path = trim($dir, '/') . '/' . $name . '.' . $ext;
            Storage::disk('public')->put($path, $data);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fileExists(string $path): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function guarded(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->warn("  [{$label}] sărit: " . $e->getMessage());
        }
    }

    /** Coloanele reale ale tabelei (cache per rulare). Gol dacă nu pot fi citite. */
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

    /** Păstrează doar cheile care sunt coloane reale (protecție la schema drift). */
    private function only(string $table, array $data): array
    {
        $cols = $this->cols($table);
        if (empty($cols)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($cols));
    }

    private function nullable(string $table, string $column): bool
    {
        try {
            foreach (Schema::getColumns($table) as $c) {
                if (($c['name'] ?? null) === $column) {
                    return (bool) ($c['nullable'] ?? true);
                }
            }
        } catch (\Throwable $e) {
            // necunoscut → presupunem nullable
        }

        return true;
    }
}
