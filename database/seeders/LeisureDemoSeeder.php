<?php

namespace Database\Seeders;

use App\Enums\TenantType;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\ResourceRental;
use App\Models\Leisure\TenantTaxRegistry;
use App\Models\Leisure\TenantTeamMember;
use App\Models\Leisure\TicketTypeCapacity;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demo data for a tenant_type=leisure venue. Creates EVERYTHING needed to
 * click through the entire feature set:
 *
 *   • 1 owner (admin) user — owner@aquasplash.demo / parola: aquasplash
 *   • 1 tenant (Aquapark Splash Demo) with all leisure features enabled
 *   • 2 tax registries (primary + parking-services SRL — multi-society demo)
 *   • 5 ticket types with full leisure config:
 *       – Bilet acces 1 zi (canale: online 30 / POS 35 / mobil 32)
 *       – Abonament sezon
 *       – Parcare (60 min + surcharge depășire)
 *       – Kayak 30/60/120 min (duration variants + overtime)
 *       – Tură ghidată (activitate cu durată)
 *   • Capacity rows pe 30 zile (per-zi) + 4 sloturi orare pentru kayak
 *   • 8 echipamente fizice (4 kayak + 4 biciclete) cu QR-uri unice
 *   • 5 team members cu roluri diferite (toți cu parola "operator123"):
 *       – manager@   → leisure_role=admin
 *       – checkin@   → leisure_role=check_in
 *       – rental@    → leisure_role=rental_operator
 *       – cashier@   → leisure_role=pos_cashier
 *       – inventory@ → leisure_role=inventory_manager
 *   • 6 customers cu istoric variat (high-value, returning, new)
 *   • 12 orders pe ultimele 30 zile (canale mixte) + tickets generate
 *   • 2 rentals active (overdue) ca să vezi alert-ul de depășire
 *
 * Idempotent: rulează safe de mai multe ori (verifică prin email/slug).
 *
 * Usage:
 *   php artisan db:seed --class=LeisureDemoSeeder
 */
class LeisureDemoSeeder extends Seeder
{
    private const TENANT_SLUG = 'aquapark-splash-demo';
    private const OWNER_EMAIL = 'owner@aquasplash.demo';
    private const OPERATOR_PASSWORD = 'operator123';

    public function run(): void
    {
        $this->command->info('Seeding leisure demo tenant...');

        DB::transaction(function () {
            // Microservices need to exist first.
            $this->callSeederIfMissing();

            $owner = $this->createOwnerUser();
            $tenant = $this->createTenant($owner);
            $this->activateMicroservices($tenant);

            [$primaryRegistry, $rentalsRegistry] = $this->createTaxRegistries($tenant);
            $teamMembers = $this->createTeam($tenant);
            $event = $this->createEvent($tenant);
            $ticketTypes = $this->createTicketTypes($event, $primaryRegistry, $rentalsRegistry);
            $this->createCapacities($tenant, $ticketTypes);
            $resources = $this->createPhysicalResources($tenant, $ticketTypes['kayak']);
            $customers = $this->createCustomers($tenant);
            $orders = $this->createOrdersWithTickets($tenant, $event, $ticketTypes, $customers);
            $this->createActiveRentals($tenant, $resources, $orders, $teamMembers['rental']);
        });

        $this->command->info('');
        $this->command->info('=================================================================');
        $this->command->info('  LEISURE DEMO TENANT SEEDED.');
        $this->command->info('=================================================================');
        $this->command->info('  Admin login (/admin):');
        $this->command->info('    email:    ' . self::OWNER_EMAIL);
        $this->command->info('    password: aquasplash');
        $this->command->info('');
        $this->command->info('  Operator logins (/operator) — toți cu parola: ' . self::OPERATOR_PASSWORD);
        $this->command->info('    manager@aquasplash.demo    (admin — vede tot)');
        $this->command->info('    checkin@aquasplash.demo    (check-in scanare)');
        $this->command->info('    rental@aquasplash.demo     (operator rentals)');
        $this->command->info('    cashier@aquasplash.demo    (POS casier)');
        $this->command->info('    inventory@aquasplash.demo  (manager inventar)');
        $this->command->info('');
        $this->command->info('  Tenant panel:  /tenant');
        $this->command->info('=================================================================');
    }

    private function callSeederIfMissing(): void
    {
        if (\App\Models\Microservice::where('slug', 'leisure-core')->doesntExist()) {
            $this->call(LeisureMicroservicesSeeder::class);
        }
    }

    /**
     * Live schemas don't always have first_name/last_name/phone/role/tenant_id —
     * those columns are added by tenant-system migrations that may not have run
     * on every environment. We filter the attribute array against the actual
     * users-table columns so the seeder is portable across schema variants.
     */
    private function userAttrs(array $attrs): array
    {
        return $this->writableAttrs('users', $attrs);
    }

    /**
     * Intersect attribute array with the set of columns that actually exist on
     * the table AND are NOT generated/computed. Generated columns (e.g.
     * tenant_microservices.is_active in prod) refuse explicit inserts.
     */
    private function writableAttrs(string $table, array $attrs): array
    {
        $cols = Schema::getColumnListing($table);
        $writable = array_diff($cols, $this->generatedColumns($table));
        return array_intersect_key($attrs, array_flip($writable));
    }

    private function createOwnerUser(): User
    {
        $attrs = $this->userAttrs([
            'name' => 'Andrei Demo',
            'first_name' => 'Andrei',
            'last_name' => 'Demo',
            'password' => Hash::make('aquasplash'),
            'role' => 'tenant',
            'phone' => '+40700000001',
        ]);
        // Hash::make may have been filtered out if there's no password column
        // (extremely unlikely), so ensure it survives.
        if (! isset($attrs['password'])) {
            $attrs['password'] = Hash::make('aquasplash');
        }
        return User::firstOrCreate(
            ['email' => self::OWNER_EMAIL],
            $attrs
        );
    }

    private function createTenant(User $owner): Tenant
    {
        $desired = [
            'name' => 'Aquapark Splash Demo SRL',
            'public_name' => 'Aquapark Splash',
            'tenant_type' => TenantType::Leisure,
            'status' => 'active',
            'plan' => '1percent',
            'owner_id' => $owner->id,
            'locale' => 'ro',
            'currency' => 'RON',
            'country' => 'RO',
            'state' => 'Brasov',
            'city' => 'Brasov',
            'postal_code' => '500001',
            'address' => 'Strada Demo nr. 1',
            'company_name' => 'Aquapark Splash Demo SRL',
            'cui' => 'RO99999991',
            'reg_com' => 'J08/9991/2024',
            'bank_account' => 'RO00DEMO0000000000099991',
            'bank_name' => 'BCR',
            'commission_mode' => 'included',
            'commission_rate' => 1.0,
            'work_method' => 'exclusive',
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            // Override the observer defaults — enable ALL leisure features
            // so the demo exercises every gated UI section.
            'features' => [
                'leisure' => [
                    'enabled' => true,
                    'rentals' => ['enabled' => true],
                    'pos' => ['enabled' => true],
                    'time_slots' => ['enabled' => true],
                    'physical_inventory' => ['enabled' => true],
                    'multi_society' => ['enabled' => true],
                    'channel_pricing' => ['enabled' => true],
                    'embed' => ['enabled' => true],
                    'crm' => ['enabled' => true],
                ],
            ],
        ];
        $attrs = $this->writableAttrs('tenants', $desired);
        $tenant = Tenant::firstOrCreate(['slug' => self::TENANT_SLUG], $attrs);

        // Link owner to tenant (only if the column exists on this schema).
        if (Schema::hasColumn('users', 'tenant_id') && $owner->tenant_id !== $tenant->id) {
            $owner->update(['tenant_id' => $tenant->id]);
        }

        return $tenant;
    }

    private function activateMicroservices(Tenant $tenant): void
    {
        $slugs = TenantType::Leisure->defaultMicroserviceSlugs();
        $microservices = \App\Models\Microservice::whereIn('slug', $slugs)->get();

        // is_active is a GENERATED column on some envs (derived from status);
        // exclude any generated/unwritable columns so we never INSERT into them.
        $pivotCols = Schema::getColumnListing('tenant_microservices');
        $generatedCols = $this->generatedColumns('tenant_microservices');
        $writableCols = array_values(array_diff($pivotCols, $generatedCols));

        foreach ($microservices as $ms) {
            $exists = DB::table('tenant_microservices')
                ->where('tenant_id', $tenant->id)
                ->where('microservice_id', $ms->id)
                ->exists();
            if ($exists) {
                continue;
            }
            $row = [
                'tenant_id' => $tenant->id,
                'microservice_id' => $ms->id,
                'status' => 'active',
                'is_active' => true,
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $row = array_intersect_key($row, array_flip($writableCols));
            DB::table('tenant_microservices')->insert($row);
        }
    }

    /**
     * Return the list of generated/virtual columns on a Postgres table — these
     * cannot be inserted into. On MySQL/sqlite we return an empty list.
     */
    private function generatedColumns(string $table): array
    {
        try {
            $driver = DB::connection()->getDriverName();
        } catch (\Throwable) {
            return [];
        }

        if ($driver !== 'pgsql') {
            return [];
        }
        try {
            return DB::table('information_schema.columns')
                ->where('table_name', $table)
                ->whereIn('is_generated', ['ALWAYS', 'BY DEFAULT'])
                ->pluck('column_name')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return TenantTaxRegistry[] */
    private function createTaxRegistries(Tenant $tenant): array
    {
        $primary = TenantTaxRegistry::firstOrCreate(
            ['tenant_id' => $tenant->id, 'cui' => 'RO99999991'],
            [
                'company_name' => 'Aquapark Splash Demo SRL',
                'reg_com' => 'J08/9991/2024',
                'vat_payer' => true,
                'vat_number' => 'RO99999991',
                'country' => 'RO',
                'state' => 'Brasov',
                'city' => 'Brasov',
                'address' => 'Strada Demo nr. 1',
                'bank_name' => 'BCR',
                'bank_account' => 'RO00DEMO0000000000099991',
                'invoice_series' => 'AQUA',
                'invoice_next_number' => 1,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        $rentals = TenantTaxRegistry::firstOrCreate(
            ['tenant_id' => $tenant->id, 'cui' => 'RO99999992'],
            [
                'company_name' => 'Splash Rentals & Parking SRL',
                'reg_com' => 'J08/9992/2024',
                'vat_payer' => true,
                'vat_number' => 'RO99999992',
                'country' => 'RO',
                'state' => 'Brasov',
                'city' => 'Brasov',
                'address' => 'Strada Demo nr. 1',
                'bank_name' => 'BCR',
                'bank_account' => 'RO00DEMO0000000000099992',
                'invoice_series' => 'RENT',
                'invoice_next_number' => 1,
                'is_default' => false,
                'is_active' => true,
            ]
        );

        return [$primary, $rentals];
    }

    /** @return array<string, TenantTeamMember> */
    private function createTeam(Tenant $tenant): array
    {
        $members = [
            'manager' => ['leisure_role' => 'admin',              'role' => 'admin'],
            'checkin' => ['leisure_role' => 'check_in',           'role' => 'staff'],
            'rental'  => ['leisure_role' => 'rental_operator',    'role' => 'staff'],
            'cashier' => ['leisure_role' => 'pos_cashier',        'role' => 'staff'],
            'inventory' => ['leisure_role' => 'inventory_manager','role' => 'staff'],
        ];

        $out = [];
        foreach ($members as $key => $config) {
            $email = "{$key}@aquasplash.demo";
            $user = User::firstOrCreate(
                ['email' => $email],
                $this->userAttrs([
                    'name' => ucfirst($key) . ' Demo',
                    'first_name' => ucfirst($key),
                    'last_name' => 'Demo',
                    'password' => Hash::make(self::OPERATOR_PASSWORD),
                    'role' => 'tenant',
                    'tenant_id' => $tenant->id,
                ]) + ['password' => Hash::make(self::OPERATOR_PASSWORD)]
            );

            $out[$key] = TenantTeamMember::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'role' => $config['role'],
                    'leisure_role' => $config['leisure_role'],
                    'permissions' => $config['leisure_role'] === 'admin' ? ['*'] : $this->defaultPermsForRole($config['leisure_role']),
                    'status' => TenantTeamMember::STATUS_ACTIVE,
                    'accepted_at' => now()->subDays(rand(1, 30)),
                ]
            );
        }
        return $out;
    }

    private function defaultPermsForRole(string $leisureRole): array
    {
        return match ($leisureRole) {
            'check_in' => ['tickets.scan', 'orders.view'],
            'rental_operator' => ['rentals.start', 'rentals.end', 'tickets.scan'],
            'pos_cashier' => ['pos.checkout', 'orders.view'],
            'pos_manager' => ['pos.checkout', 'pos.cash_drawer', 'orders.view', 'orders.refund', 'reports.view'],
            'inventory_manager' => ['inventory.manage', 'reports.view'],
            default => [],
        };
    }

    private function createEvent(Tenant $tenant): Event
    {
        // Idempotency: look up by JSON path on slug.ro (works on Postgres
        // and MySQL 5.7+; falls back to raw LIKE if neither is available).
        $existing = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $driver = $q->getConnection()->getDriverName();
                if ($driver === 'pgsql') {
                    $q->whereRaw("slug->>'ro' = ?", ['aquapark-splash-vara-2026']);
                } elseif ($driver === 'mysql') {
                    $q->whereRaw("JSON_EXTRACT(slug, '$.ro') = ?", ['aquapark-splash-vara-2026']);
                } else {
                    $q->where('slug', 'like', '%aquapark-splash-vara-2026%');
                }
            })
            ->first();
        if ($existing) {
            return $existing;
        }

        $titleTranslations = ['ro' => 'Aquapark Splash — Sezon Vară 2026', 'en' => 'Splash Aquapark — Summer 2026'];
        $descTranslations = ['ro' => 'Bilete de acces, parcare, kayak și tururi ghidate. Toate într-un singur loc.'];
        $shortTranslations = ['ro' => 'Aquapark + rentals + parcare.'];

        $desired = [
            'tenant_id' => $tenant->id,
            'title' => $titleTranslations,
            'slug' => ['ro' => 'aquapark-splash-vara-2026'],
            'description' => $descTranslations,
            'short_description' => $shortTranslations,
            'duration_mode' => 'range',
            'range_start_date' => now()->subDays(7)->toDateString(),
            'range_end_date' => now()->addDays(90)->toDateString(),
            'range_start_time' => '09:00',
            'range_end_time' => '20:00',
            'status' => 'published',
            'is_published' => true,
            'display_template' => 'leisure_venue',
        ];
        return Event::create($this->writableAttrs('events', $desired));
    }

    /** @return array<string, TicketType> */
    private function createTicketTypes(Event $event, TenantTaxRegistry $primary, TenantTaxRegistry $rentals): array
    {
        $defs = [
            'access_day' => [
                'name' => 'Bilet acces 1 zi',
                'price_cents' => 3000, // 30 RON online
                'quota_total' => 5000,
                'service_category' => 'access',
                'is_entry_ticket' => true,
                'tenant_tax_registry_id' => $primary->id,
                'channel_pricing' => ['online' => 3000, 'pos_fixed' => 3500, 'pos_mobile' => 3200, 'embed' => 3000],
                'leisure_pricing_rules' => [
                    ['label' => 'Weekend +20%', 'days' => [6, 7], 'type' => 'percent', 'value' => 20],
                ],
            ],
            'season_pass' => [
                'name' => 'Abonament sezon',
                'price_cents' => 25000, // 250 RON
                'quota_total' => 500,
                'service_category' => 'access',
                'is_subscription' => true,
                'is_entry_ticket' => true,
                'tenant_tax_registry_id' => $primary->id,
                'channel_pricing' => ['online' => 25000, 'pos_fixed' => 27000, 'pos_mobile' => 26000],
            ],
            'parking' => [
                'name' => 'Parcare 60 min',
                'price_cents' => 500, // 5 RON
                'quota_total' => 200,
                'service_category' => 'parking',
                'service_duration_minutes' => 60,
                'requires_access_ticket' => false,
                'tenant_tax_registry_id' => $rentals->id,
                'channel_pricing' => ['online' => 500, 'pos_fixed' => 700, 'pos_mobile' => 600],
                'leisure_is_overtime_chargeable' => true,
                'leisure_overtime_surcharge_cents' => 200, // 2 RON per interval
                'leisure_overtime_interval_minutes' => 30,
            ],
            'kayak' => [
                'name' => 'Kayak (rental)',
                'price_cents' => 2000, // 20 RON pentru 30 min (de bază)
                'quota_total' => 4, // 4 kayak fizice
                'service_category' => 'rental',
                'service_duration_minutes' => 30,
                'requires_access_ticket' => true,
                'tenant_tax_registry_id' => $rentals->id,
                'channel_pricing' => ['online' => 2000, 'pos_fixed' => 2200, 'pos_mobile' => 2100],
                'leisure_duration_variants' => [
                    ['duration_minutes' => 30, 'label' => '30 min', 'price_multiplier' => 1.0],
                    ['duration_minutes' => 60, 'label' => '1h', 'price_multiplier' => 1.8],
                    ['duration_minutes' => 120, 'label' => '2h', 'price_multiplier' => 3.0],
                ],
                'leisure_is_overtime_chargeable' => true,
                'leisure_overtime_surcharge_cents' => 1000, // 10 RON per interval
                'leisure_overtime_interval_minutes' => 30,
                'leisure_pricing_rules' => [
                    ['label' => 'Weekend +25%', 'days' => [6, 7], 'type' => 'percent', 'value' => 25],
                ],
            ],
            'guided_tour' => [
                'name' => 'Tură ghidată (90 min)',
                'price_cents' => 5000, // 50 RON
                'quota_total' => 30,
                'service_category' => 'activity',
                'service_duration_minutes' => 90,
                'requires_access_ticket' => false,
                'tenant_tax_registry_id' => $primary->id,
                'channel_pricing' => ['online' => 5000, 'pos_fixed' => 5500, 'pos_mobile' => 5200],
                'leisure_seasons' => [
                    [
                        'label' => 'Sezon vară',
                        'start_date' => now()->startOfMonth()->toDateString(),
                        'end_date' => now()->addMonths(2)->endOfMonth()->toDateString(),
                        'type' => 'percent', 'value' => 10,
                        'last_entry' => '18:00',
                    ],
                ],
            ],
        ];

        $out = [];
        foreach ($defs as $key => $attrs) {
            $existing = TicketType::where('event_id', $event->id)->where('name', $attrs['name'])->first();
            if ($existing) {
                $out[$key] = $existing;
                continue;
            }
            $out[$key] = TicketType::create(array_merge([
                'event_id' => $event->id,
                'currency' => 'RON',
                'status' => 'active',
                'quota_sold' => 0,
            ], $attrs));
        }
        return $out;
    }

    /**
     * @param  array<string, TicketType>  $tts
     */
    private function createCapacities(Tenant $tenant, array $tts): void
    {
        $start = CarbonImmutable::today();
        $end = $start->addDays(30);

        // Per-day capacity for access tickets, parking, season passes, guided tour.
        $perDayTickets = ['access_day' => 500, 'parking' => 50, 'season_pass' => 5, 'guided_tour' => 20];
        foreach ($perDayTickets as $key => $perDayCap) {
            $tt = $tts[$key] ?? null;
            if (! $tt) continue;
            for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
                TicketTypeCapacity::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'ticket_type_id' => $tt->id,
                        'capacity_date' => $d->toDateString(),
                        'time_slot_start' => null,
                    ],
                    [
                        'capacity' => $perDayCap,
                        'sold' => rand(0, (int) ($perDayCap * 0.6)),
                        'reserved' => 0,
                        'is_closed' => false,
                    ]
                );
            }
        }

        // Hourly slots for kayak: 4 slots per day (10:00, 12:00, 14:00, 16:00).
        $kayak = $tts['kayak'] ?? null;
        if ($kayak) {
            $slots = [
                ['10:00:00', '11:00:00'],
                ['12:00:00', '13:00:00'],
                ['14:00:00', '15:00:00'],
                ['16:00:00', '17:00:00'],
            ];
            for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
                foreach ($slots as [$slotStart, $slotEnd]) {
                    TicketTypeCapacity::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'ticket_type_id' => $kayak->id,
                            'capacity_date' => $d->toDateString(),
                            'time_slot_start' => $slotStart,
                        ],
                        [
                            'time_slot_end' => $slotEnd,
                            'capacity' => 4,
                            'sold' => rand(0, 3),
                            'reserved' => 0,
                            'is_closed' => false,
                        ]
                    );
                }
            }
        }
    }

    /** @return array<int, PhysicalResource> */
    private function createPhysicalResources(Tenant $tenant, TicketType $kayakType): array
    {
        // 4 kayaks linked to the kayak ticket type, + 4 bikes (no linked ticket = any rental).
        $items = [
            ['resource_type' => 'kayak', 'name' => 'Kayak Roșu #1', 'label' => 'RED-01', 'linked' => [$kayakType->id]],
            ['resource_type' => 'kayak', 'name' => 'Kayak Roșu #2', 'label' => 'RED-02', 'linked' => [$kayakType->id]],
            ['resource_type' => 'kayak', 'name' => 'Kayak Albastru #1', 'label' => 'BLU-01', 'linked' => [$kayakType->id]],
            ['resource_type' => 'kayak', 'name' => 'Kayak Albastru #2', 'label' => 'BLU-02', 'linked' => [$kayakType->id]],
            ['resource_type' => 'bike', 'name' => 'Bicicletă MTB #1', 'label' => 'MTB-01', 'linked' => null],
            ['resource_type' => 'bike', 'name' => 'Bicicletă MTB #2', 'label' => 'MTB-02', 'linked' => null],
            ['resource_type' => 'bike', 'name' => 'Bicicletă City #1', 'label' => 'CTY-01', 'linked' => null],
            ['resource_type' => 'bike', 'name' => 'Bicicletă City #2', 'label' => 'CTY-02', 'linked' => null],
        ];

        $resources = [];
        foreach ($items as $i => $item) {
            $existing = PhysicalResource::where('tenant_id', $tenant->id)
                ->where('name', $item['name'])->first();
            if ($existing) {
                $resources[] = $existing;
                continue;
            }
            $resources[] = PhysicalResource::create([
                'tenant_id' => $tenant->id,
                'resource_type' => $item['resource_type'],
                'name' => $item['name'],
                'label' => $item['label'],
                'qr_code' => PhysicalResource::generateQrCode($tenant->id, $item['resource_type']),
                'status' => PhysicalResource::STATUS_AVAILABLE,
                'linked_ticket_type_ids' => $item['linked'],
                'meta' => ['condition' => 'good'],
            ]);
        }
        return $resources;
    }

    /** @return array<int, Customer> */
    private function createCustomers(Tenant $tenant): array
    {
        $defs = [
            ['first_name' => 'Maria', 'last_name' => 'Popescu', 'email' => 'maria.popescu@example.com', 'city' => 'Brasov', 'phone' => '+40700000101'],
            ['first_name' => 'Ion', 'last_name' => 'Ionescu', 'email' => 'ion.ionescu@example.com', 'city' => 'Sibiu', 'phone' => '+40700000102'],
            ['first_name' => 'Alex', 'last_name' => 'Georgescu', 'email' => 'alex.georgescu@example.com', 'city' => 'Bucuresti', 'phone' => '+40700000103'],
            ['first_name' => 'Ana', 'last_name' => 'Munteanu', 'email' => 'ana.munteanu@example.com', 'city' => 'Cluj-Napoca', 'phone' => '+40700000104'],
            ['first_name' => 'Vlad', 'last_name' => 'Stoica', 'email' => 'vlad.stoica@example.com', 'city' => 'Brasov', 'phone' => '+40700000105'],
            ['first_name' => 'Elena', 'last_name' => 'Vasilescu', 'email' => 'elena.vasilescu@example.com', 'city' => 'Iasi', 'phone' => '+40700000106'],
        ];

        $out = [];
        foreach ($defs as $i => $d) {
            $attrs = array_merge($d, [
                'tenant_id' => $tenant->id,
                'primary_tenant_id' => $tenant->id,
                'full_name' => $d['first_name'] . ' ' . $d['last_name'],
                'country' => 'RO',
            ]);
            $out[] = Customer::firstOrCreate(
                ['email' => $d['email']],
                $this->writableAttrs('customers', $attrs)
            );
        }
        return $out;
    }

    /** @return Order[] */
    private function createOrdersWithTickets(Tenant $tenant, Event $event, array $tts, array $customers): array
    {
        $orders = [];
        $channels = ['online', 'online', 'pos_fixed', 'pos_mobile', 'online', 'embed'];

        // 12 orders distributed over the last 30 days.
        for ($i = 0; $i < 12; $i++) {
            $customer = $customers[$i % count($customers)];
            $channel = $channels[$i % count($channels)];
            $daysAgo = rand(0, 30);
            $createdAt = now()->subDays($daysAgo);

            // Pick a random ticket type + qty
            $ttKeys = ['access_day', 'parking', 'kayak', 'season_pass', 'guided_tour'];
            $pickedKey = $ttKeys[array_rand($ttKeys)];
            $tt = $tts[$pickedKey];
            $qty = rand(1, 4);
            $channelPrices = is_array($tt->channel_pricing) ? $tt->channel_pricing : [];
            $unitCents = (int) ($channelPrices[$channel] ?? $tt->price_cents);
            $totalCents = $unitCents * $qty;

            // Avoid duplicate orders if seeder re-runs — use a deterministic order_number.
            $orderNumber = "DEMO-{$tenant->id}-" . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $existing = Order::where('order_number', $orderNumber)->first();
            if ($existing) {
                $orders[] = $existing;
                continue;
            }

            $orderAttrs = [
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'customer_name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->email,
                'customer_phone' => $customer->phone ?? null,
                'order_number' => $orderNumber,
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_processor' => $channel === 'pos_fixed' ? 'cash' : 'netopia',
                'channel' => $channel,
                'subtotal' => $totalCents / 100,
                'total' => $totalCents / 100,
                'total_cents' => $totalCents,
                'currency' => 'RON',
                'paid_at' => $createdAt->copy()->addMinutes(5),
                'source' => 'demo',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
            $order = Order::create($this->writableAttrs('orders', $orderAttrs));

            // Generate the actual tickets so /operator check-in has things to scan.
            for ($t = 0; $t < $qty; $t++) {
                Ticket::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $tt->id,
                    'code' => 'DEMO-' . strtoupper(Str::random(10)),
                    'status' => 'valid',
                ]);
            }

            $orders[] = $order;
        }
        return $orders;
    }

    private function createActiveRentals(Tenant $tenant, array $resources, array $orders, TenantTeamMember $rentalOperator): void
    {
        // Find a paid kayak order with at least one ticket; reuse one to create
        // an active overdue rental so the operator dashboard shows the red badge.
        $kayakOrders = collect($orders)->filter(function ($o) {
            return Ticket::where('order_id', $o->id)
                ->whereHas('ticketType', fn ($q) => $q->where('service_category', 'rental'))
                ->exists();
        })->values();

        if ($kayakOrders->isEmpty()) {
            return;
        }

        $kayakResources = collect($resources)->where('resource_type', 'kayak')->values();
        if ($kayakResources->isEmpty()) {
            return;
        }

        $now = CarbonImmutable::now();

        // Active rental (currently overdue by ~15 min) — pick first ticket.
        $firstOrder = $kayakOrders->first();
        $firstTicket = Ticket::where('order_id', $firstOrder->id)
            ->whereHas('ticketType', fn ($q) => $q->where('service_category', 'rental'))
            ->first();

        if ($firstTicket) {
            $resource = $kayakResources[0];
            if (! ResourceRental::where('ticket_id', $firstTicket->id)->exists()) {
                ResourceRental::create([
                    'tenant_id' => $tenant->id,
                    'ticket_id' => $firstTicket->id,
                    'physical_resource_id' => $resource->id,
                    'started_by_user_id' => $rentalOperator->user_id,
                    'started_at' => $now->subMinutes(75),
                    'planned_end_at' => $now->subMinutes(15), // ended 15 min ago — overdue
                ]);
                $resource->update(['status' => PhysicalResource::STATUS_IN_USE]);
            }
        }

        // Second active rental — on time, not overdue (for variety).
        if ($kayakOrders->count() > 1 && $kayakResources->count() > 1) {
            $secondOrder = $kayakOrders[1];
            $secondTicket = Ticket::where('order_id', $secondOrder->id)
                ->whereHas('ticketType', fn ($q) => $q->where('service_category', 'rental'))
                ->first();
            $resource2 = $kayakResources[1];
            if ($secondTicket && ! ResourceRental::where('ticket_id', $secondTicket->id)->exists()) {
                ResourceRental::create([
                    'tenant_id' => $tenant->id,
                    'ticket_id' => $secondTicket->id,
                    'physical_resource_id' => $resource2->id,
                    'started_by_user_id' => $rentalOperator->user_id,
                    'started_at' => $now->subMinutes(20),
                    'planned_end_at' => $now->addMinutes(40),
                ]);
                $resource2->update(['status' => PhysicalResource::STATUS_IN_USE]);
            }
        }
    }
}
