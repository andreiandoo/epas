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
 * Tenantul demo pentru verticala SPORT (demo-urile de template, demo-sport.tics.ro):
 * owner + tenant + domeniu + client demo + microservicii (cost zero) + locații +
 * categorii + 8 competiții din sporturi diferite, cu tipuri de bilete și afișe.
 * Datele sunt relative la ziua rulării (3–16 săptămâni în viitor), deci o
 * re-rulare le împinge din nou în viitor.
 *
 *   php artisan demo:seed-sport
 *
 * Copie a lui wukf:seed (aceleași garanții): idempotent după slug / email /
 * domeniu, pur aditiv, nu atinge alți tenanți. La final înregistrează demo-ul
 * live (demo:site competitie <tenant> sport).
 */
class DemoSportSeed extends Command
{
    protected $signature = 'demo:seed-sport
        {--domain=demo-sport.tics.ro : Hostname-ul demo-ului (galeria îl listează)}
        {--owner-email=demo-sport@tixello.ro : Emailul utilizatorului owner}
        {--owner-password= : Parola ownerului (goală = generată la creare / păstrată la re-rulare)}
        {--template=competitie : Template-ul demo-ului live}
        {--pass-fee= : Mută taxa procesatorului de plăți la cumpărător: „procent” sau „procent,fix_în_bani”; „off” o dezactivează}';

    protected $description = 'Seed demo complet pentru verticala sport (Liga Sportivă Demo) + demo-ul live demo-sport.tics.ro';

    private const NAME = 'Liga Sportivă Demo';
    private const TENANT_SLUG = 'demo-sport';

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

        $this->info('Seed demo sport — ' . self::NAME);

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

        // Demo-ul live pe domeniul seed-uit (galeria demo.tics.ro)
        $this->guarded('demo live', function () use ($tenant, $domain) {
            $base = (string) config('tenant-sites.demo_base_domain', 'tics.ro');
            if (preg_match('/^demo-([a-z0-9-]+)\.' . preg_quote($base, '/') . '$/', $domain, $m)) {
                $this->call('demo:site', [
                    'template' => (string) $this->option('template'),
                    'tenant' => $tenant->id,
                    'slug' => $m[1],
                    '--title' => 'Competiții și gale',
                    '--description' => 'Federații, cluburi, gale de box și MMA: calendar, numărătoare inversă, bilete pe tipuri sau pe locuri.',
                    '--sort' => 20,
                ]);
            } else {
                $this->warn("  {$domain} nu e de forma demo-*.{$base}: sar peste înregistrarea demo-ului live.");
            }
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
        $this->line('  Cod reducere:     SPORT10 (10% din bilete, pentru test)');
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
            'ticket_series_prefix' => 'DSPT',
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
            'website'              => null,
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
            'site_tagline'  => 'Bilete la competiții și gale',
        ];
        // Site-ul public: email de confirmare cu biletele + identitatea folosită în emailuri
        $storefront = [
            'order_emails' => true,
            'brand_dark'   => '#01012F',
            'brand_color'  => '#1151D3',
        ];

        if (! $tenant) {
            $data['settings'] = $siteSettings + ['storefront' => $storefront, 'panel' => ['simple_menu' => true]];
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
        // Meniul simplu al panoului + pagina „Primii pași” (o alegere deja făcută în setări nu e călcată)
        $data['settings']['panel'] = array_merge(['simple_menu' => true], is_array($data['settings']['panel'] ?? null) ? $data['settings']['panel'] : []);
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

        $coupon = CouponCode::withTrashed()->where('tenant_id', $tenant->id)->where('code', 'SPORT10')->first();
        if ($coupon) {
            if ($coupon->trashed()) {
                $coupon->restore();
            }
            $this->line('  cod de reducere existent: SPORT10 (' . $coupon->status . ')');
            return;
        }

        CouponCode::create($this->only('coupon_codes', [
            'tenant_id'      => $tenant->id,
            'code'           => 'SPORT10',
            'code_type'      => 'multi_use',
            'discount_type'  => 'percentage',
            'discount_value' => 10,
            'status'         => 'active',
            'is_public'      => false,
            'combinable'     => false,
            'source'         => 'demo',
        ]));
        $this->line('  cod de reducere creat: SPORT10 (10%)');
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
            'gale'        => ['name' => 'Gale', 'icon' => 'fire'],
            'cupe'        => ['name' => 'Cupe și turnee', 'icon' => 'trophy'],
            'campionate'  => ['name' => 'Campionate', 'icon' => 'star'],
            'alergare'    => ['name' => 'Alergare și outdoor', 'icon' => 'globe-alt'],
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
            'polivalenta' => [
                'slug' => 'sala-polivalenta-bucuresti-demo-sport', 'name' => 'Sala Polivalentă',
                'city' => 'București', 'state' => 'București', 'address' => 'Calea Piscului 10, București',
            ],
            'constanta' => [
                'slug' => 'sala-sporturilor-constanta-demo-sport', 'name' => 'Sala Sporturilor',
                'city' => 'Constanța', 'state' => 'Constanța', 'address' => 'Constanța',
            ],
            'cluj' => [
                'slug' => 'arena-sporturilor-cluj-demo-sport', 'name' => 'Arena Sporturilor',
                'city' => 'Cluj-Napoca', 'state' => 'Cluj', 'address' => 'Cluj-Napoca',
            ],
            'brasov' => [
                'slug' => 'baza-sportiva-municipala-brasov-demo-sport', 'name' => 'Baza Sportivă Municipală',
                'city' => 'Brașov', 'state' => 'Brașov', 'address' => 'Brașov',
            ],
            'mamaia' => [
                'slug' => 'plaja-mamaia-nord-demo-sport', 'name' => 'Plaja Mamaia Nord',
                'city' => 'Năvodari', 'state' => 'Constanța', 'address' => 'Mamaia Nord, Năvodari',
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
        $footer = '<p>Biletele se cumpără online și se prezintă la intrare pe telefon sau tipărite, cu cod QR. '
            . 'Acesta este un eveniment demonstrativ: plata trece printr-un procesator de test și biletele nu sunt valabile.</p>';
        $day = fn (int $days) => now()->addDays($days)->toDateString();
        $img = fn (string $id) => 'https://images.unsplash.com/photo-' . $id . '?w=1200&q=80';

        $gala = [
            ['name' => 'Tribună', 'price' => 90, 'cap' => 1500, 'desc' => 'Loc în tribună, acces la toată gala.'],
            ['name' => 'Ringside', 'price' => 250, 'cap' => 120, 'desc' => 'Primele rânduri lângă ring sau cușcă.'],
            ['name' => 'VIP (open bar)', 'price' => 450, 'cap' => 40, 'desc' => 'Loc VIP, open bar, acces la zona de after-party.'],
        ];

        // În ordine cronologică.
        return [
            [
                'slug' => 'gala-mma-noaptea-campionilor-demo', 'title' => 'Gala MMA „Noaptea Campionilor”',
                'venue' => 'polivalenta', 'cat' => 'gale', 'start' => $day(21), 'end' => null, 'time' => '19:00',
                'poster' => $img('1504450758481-7338eba7524a'), 'tickets' => $gala,
                'short' => 'Zece meciuri, două centuri puse în joc și o arenă plină: gala de MMA a toamnei.',
                'body' => '<p>„Noaptea Campionilor” aduce în cușcă cei mai buni luptători ai momentului, într-o seară cu zece meciuri și două titluri puse în joc.</p>'
                    . '<ul><li>Main card cu două lupte pentru centură</li><li>Prelims de la ora 19:00, main card de la 21:00</li><li>Ringside și VIP cu open bar</li></ul>' . $footer,
            ],
            [
                'slug' => 'gala-box-manusi-de-aur-demo', 'title' => 'Gala de box „Mănuși de Aur”',
                'venue' => 'constanta', 'cat' => 'gale', 'start' => $day(35), 'end' => null, 'time' => '19:30',
                'poster' => $img('1549719386-74dfcbf7dbed'), 'tickets' => $gala,
                'short' => 'Box profesionist la malul mării: opt meciuri și o finală la categoria grea.',
                'body' => '<p>Gala „Mănuși de Aur” reunește boxeri profesioniști și cele mai promițătoare nume ale boxului românesc.</p>'
                    . '<ul><li>Opt meciuri, cu finala la categoria grea</li><li>Ringside cu vedere direct în colțuri</li></ul>' . $footer,
            ],
            [
                'slug' => 'cupa-baschet-3x3-demo', 'title' => 'Cupa de baschet 3x3',
                'venue' => 'cluj', 'cat' => 'cupe', 'start' => $day(42), 'end' => $day(43),
                'poster' => $img('1546519638-68e109498ffc'),
                'short' => 'Două zile de baschet 3x3, 32 de echipe și concurs de aruncări de la trei puncte.',
                'body' => '<p>Cel mai rapid format al baschetului: meciuri de zece minute, ritm mare și multă atmosferă în tribune.</p>'
                    . '<ul><li>32 de echipe, tablou eliminatoriu</li><li>Concurs de aruncări de la trei puncte în pauza finalei</li></ul>' . $footer,
            ],
            [
                'slug' => 'cupa-sperantelor-fotbal-juniori-demo', 'title' => 'Cupa Speranțelor – fotbal juniori',
                'venue' => 'brasov', 'cat' => 'cupe', 'start' => $day(56), 'end' => $day(57),
                'poster' => $img('1574629810360-7efbbe195018'),
                'short' => 'Turneu de fotbal pentru juniori U13 și U15, cu 16 academii din țară.',
                'body' => '<p>Cupa Speranțelor adună academiile de fotbal pentru un weekend de meciuri, cu finalele duminică după-amiază.</p>'
                    . '<ul><li>Categorii U13 și U15</li><li>16 academii, grupe și eliminatorii</li></ul>' . $footer,
            ],
            [
                'slug' => 'campionatul-national-atletism-indoor-demo', 'title' => 'Campionatul Național de atletism indoor',
                'venue' => 'polivalenta', 'cat' => 'campionate', 'start' => $day(70), 'end' => $day(71),
                'poster' => $img('1461896836934-ffe607ba8211'),
                'short' => 'Sprint, sărituri și aruncări: titlurile naționale de sală, în două zile.',
                'body' => '<p>Cei mai buni atleți ai țării concurează pentru titlurile naționale de sală, de la 60 de metri la săritura cu prăjina.</p>'
                    . '<ul><li>Probe de sprint, garduri, sărituri și aruncarea greutății</li><li>Finalele de sprint duminică seara</li></ul>' . $footer,
            ],
            [
                'slug' => 'crosul-orasului-demo', 'title' => 'Crosul Orașului',
                'venue' => 'brasov', 'cat' => 'alergare', 'start' => $day(84), 'end' => null, 'time' => '09:00',
                'poster' => $img('1552674605-db6ffd4facb5'),
                'tickets' => [
                    ['name' => 'Participant 5 km', 'price' => 60, 'cap' => 800, 'desc' => 'Număr de concurs, cronometrare, medalie.'],
                    ['name' => 'Participant 10 km', 'price' => 80, 'cap' => 600, 'desc' => 'Număr de concurs, cronometrare, medalie, tricou tehnic.'],
                    ['name' => 'Cros copii (1 km)', 'price' => 20, 'cap' => 300, 'desc' => 'Pentru copii între 6 și 12 ani.'],
                ],
                'short' => 'Alergare de 5 și 10 km prin centrul orașului, plus crosul copiilor.',
                'body' => '<p>Crosul Orașului e alergarea pentru toată lumea: traseu plat, cronometrare electronică și medalie la final.</p>'
                    . '<ul><li>Trasee de 5 și 10 km</li><li>Cros de 1 km pentru copii</li></ul>' . $footer,
            ],
            [
                'slug' => 'turneul-volei-pe-plaja-demo', 'title' => 'Turneul de volei pe plajă',
                'venue' => 'mamaia', 'cat' => 'cupe', 'start' => $day(98), 'end' => $day(100),
                'poster' => $img('1612872087720-bb876e2e67d1'),
                'short' => 'Trei zile de volei pe plajă, feminin și masculin, cu finalele la apus.',
                'body' => '<p>Cele mai bune perechi de volei pe plajă se întâlnesc pe nisipul din Mamaia Nord, cu finalele programate la apus.</p>'
                    . '<ul><li>Turneu feminin și masculin</li><li>Tribună pe plajă și zonă de relaxare</li></ul>' . $footer,
            ],
            [
                'slug' => 'open-arte-martiale-demo', 'title' => 'Open-ul de arte marțiale',
                'venue' => 'cluj', 'cat' => 'campionate', 'start' => $day(112), 'end' => $day(113),
                'poster' => $img('1555597673-b21d5c935865'),
                'short' => 'Karate, judo și taekwondo: competiție open pe trei suprafețe de concurs.',
                'body' => '<p>Open-ul de arte marțiale aduce în aceeași sală karate, judo și taekwondo, pe trei suprafețe de concurs care rulează în paralel.</p>'
                    . '<ul><li>Probe de kata, kumite, judo și taekwondo</li><li>Categorii de la copii la seniori</li></ul>' . $footer,
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
            'event_website_url' => null,
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
                'door_time'     => date('H:i', strtotime(($e['time'] ?? '09:00') . ' -60 minutes')),
                'start_time'    => $e['time'] ?? '09:00',
                'end_time'      => date('H:i', strtotime(($e['time'] ?? '09:00') . ' +4 hours')),
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
            $this->seedTicketTypes($event, $isRange, $e['tickets'] ?? null);
        } catch (\Throwable $ex) {
            $this->warn("  bilete pentru {$e['slug']}: " . $ex->getMessage());
        }

        $this->line(($isNew ? '  eveniment creat: ' : '  eveniment actualizat: ') . "{$e['title']} (#{$event->id}) — {$e['start']}" . ($isRange ? " → {$e['end']}" : ''));

        return $event;
    }

    /* ------------------------------------------------------------------ */
    /* Tipuri de bilete                                                    */
    /* ------------------------------------------------------------------ */

    private function seedTicketTypes(Event $event, bool $isRange, ?array $custom = null): void
    {
        // Eveniment cu sală pe locuri (wukf:seed-seating): biletele de acces general nu se mai vând — nu le recrea / reactiva.
        if (! empty($event->seating_layout_id)) {
            return;
        }

        $defs = $custom ? array_map(fn ($d) => $d + ['range_only' => false], $custom) : [
            ['name' => 'Spectator – 1 zi', 'price' => 25, 'cap' => 400, 'range_only' => false,
                'desc' => 'Acces în tribună pentru o singură zi.'],
            ['name' => 'Spectator – ambele zile', 'price' => 40, 'cap' => 400, 'range_only' => true,
                'desc' => 'Acces în tribună în toate zilele.'],
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
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; demo-sport-seed/1.0)',
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
