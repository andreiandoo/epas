<?php

namespace Database\Seeders;

use App\Enums\TenantType;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\PhysicalResourceType;
use App\Models\Leisure\TenantTaxRegistry;
use App\Models\Leisure\TenantTeamMember;
use App\Models\Leisure\TicketTypeCapacity;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SchemaAwareSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Nordvale" — a complete tenant_type=leisure venue wired to parc.tixello.ro.
 *
 * Purpose-built to stand up an end-to-end leisure demo: the public storefront
 * (starter-kit template `parc`), the tenant admin panel, and the /operator
 * panel with POS + check-in + rentals, all against one coherent dataset.
 *
 * Distinct from LeisureDemoSeeder (Aquapark Splash), which stays untouched —
 * that one has no Domain and no PhysicalResourceType rows, so it cannot back a
 * real storefront.
 *
 * Everything is firstOrCreate + schema-filtered, so running it twice is safe
 * and a lagging environment does not blow up on a missing column.
 *
 *   php artisan db:seed --class=NordvaleParcSeeder
 *
 * Credentials (all demo):
 *   owner@nordvale.demo        / nordvale123   → /admin  + tenant panel
 *   casier@nordvale.demo       / operator123   → /operator (POS)
 *   receptie@nordvale.demo     / operator123   → /operator (check-in)
 *   rentals@nordvale.demo      / operator123   → /operator (rentals)
 *   manager@nordvale.demo      / operator123   → /operator (everything)
 */
class NordvaleParcSeeder extends Seeder
{
    use SchemaAwareSeeding;

    public const TENANT_SLUG = 'nordvale';
    public const DOMAIN = 'parc.tixello.ro';

    private const OPERATOR_PASSWORD = 'operator123';
    private const OWNER_PASSWORD = 'nordvale123';

    /** How many days of capacity to lay down, starting yesterday. */
    private const CAPACITY_DAYS = 120;

    public function run(): void
    {
        $owner = $this->user('owner@nordvale.demo', 'Ana Nordvale', self::OWNER_PASSWORD);
        $tenant = $this->tenant($owner);

        $this->domain($tenant);
        $registry = $this->taxRegistry($tenant);
        $venue = $this->venue($tenant);
        $event = $this->event($tenant, $venue);

        $ticketTypes = $this->ticketTypes($event, $registry);
        $this->capacity($tenant, $ticketTypes);
        $this->resourceTypes($tenant, $ticketTypes);
        $this->team($tenant);

        $this->command?->info('');
        $this->command?->info('  Nordvale is up.');
        $this->command?->info('  tenant_id  = ' . $tenant->id . '   (put this in templates/parc/site.config.php)');
        $this->command?->info('  slug       = ' . $tenant->slug);
        $this->command?->info('  domain     = ' . self::DOMAIN);
        $this->command?->info('  owner      = owner@nordvale.demo / ' . self::OWNER_PASSWORD);
        $this->command?->info('  operators  = {casier,receptie,rentals,manager}@nordvale.demo / ' . self::OPERATOR_PASSWORD);
        $this->command?->info('');
    }

    /* ---------------------------------------------------------------- users */

    private function user(string $email, string $name, string $password, array $extra = []): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }
        return User::create($this->writableAttrs('users', array_merge([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'locale' => 'ro',
        ], $extra)));
    }

    /* --------------------------------------------------------------- tenant */

    private function tenant(User $owner): Tenant
    {
        $existing = Tenant::where('slug', self::TENANT_SLUG)->first();
        if ($existing) {
            return $existing;
        }

        $tenant = Tenant::create($this->writableAttrs('tenants', [
            'name' => 'Nordvale Forest Reserve',
            'public_name' => 'Nordvale',
            'slug' => self::TENANT_SLUG,
            'owner_id' => $owner->id,
            'tenant_type' => TenantType::Leisure->value,
            'status' => 'active',
            'plan' => '1percent',
            'locale' => 'ro',
            'currency' => 'RON',
            'country' => 'RO',
            'state' => 'Brasov',
            'city' => 'Zarnesti',
            'postal_code' => '505800',
            'address' => 'DN73A km 12, Zărnești',
            'company_name' => 'Nordvale Outdoor SRL',
            'cui' => 'RO48120033',
            'reg_com' => 'J08/1200/2025',
            'bank_name' => 'BT',
            'bank_account' => 'RO49BTRL0000000000NORDVALE',
            'commission_mode' => 'included',
            'commission_rate' => 1.0,
            'work_method' => 'exclusive',
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'ticket_series_prefix' => 'NVL',
            'settings' => [
                'site_title' => 'Nordvale',
                'site_description' => 'Rezervație naturală, experiențe în pădure și pe lac.',
                'site_language' => 'ro',
                'theme' => ['primary_color' => '#09251d', 'secondary_color' => '#dffc62'],
                'legal' => [
                    'terms_title' => 'Termeni și condiții',
                    'privacy_title' => 'Politica de confidențialitate',
                ],
            ],
            // Turn everything on so the demo exercises every gated UI section.
            'features' => [
                'leisure' => [
                    'enabled' => true,
                    'rentals' => ['enabled' => true],
                    'pos' => ['enabled' => true],
                    'time_slots' => ['enabled' => true],
                    'physical_inventory' => ['enabled' => true],
                    'multi_society' => ['enabled' => false],
                    'channel_pricing' => ['enabled' => true],
                    'embed' => ['enabled' => true],
                    'crm' => ['enabled' => true],
                ],
            ],
        ]));

        if (Schema::hasColumn('users', 'tenant_id') && $owner->tenant_id !== $tenant->id) {
            $owner->update(['tenant_id' => $tenant->id]);
        }

        return $tenant;
    }

    /**
     * The storefront resolves its tenant by hostname (TenantClient\AuthController
     * accepts nothing else), and TenantClientCors matches the browser Origin
     * against this row. Without it the site renders but nobody can log in.
     */
    private function domain(Tenant $tenant): Domain
    {
        return Domain::firstOrCreate(
            ['domain' => self::DOMAIN],
            $this->writableAttrs('domains', [
                'tenant_id' => $tenant->id,
                'domain' => self::DOMAIN,
                'is_active' => true,
                'is_primary' => true,
                'is_suspended' => false,
                'is_managed_subdomain' => true,
                'subdomain' => 'parc',
                'base_domain' => 'tixello.ro',
                'activated_at' => now(),
                'notes' => 'Demo leisure storefront (starter-kit template `parc`).',
            ])
        );
    }

    private function taxRegistry(Tenant $tenant): ?TenantTaxRegistry
    {
        if (! Schema::hasTable('tenant_tax_registries')) {
            return null;
        }
        return TenantTaxRegistry::firstOrCreate(
            ['tenant_id' => $tenant->id, 'cui' => 'RO48120033'],
            $this->writableAttrs('tenant_tax_registries', [
                'tenant_id' => $tenant->id,
                'company_name' => 'Nordvale Outdoor SRL',
                'cui' => 'RO48120033',
                'reg_com' => 'J08/1200/2025',
                'vat_payer' => true,
                'vat_number' => 'RO48120033',
                'country' => 'RO',
                'state' => 'Brasov',
                'city' => 'Zarnesti',
                'postal_code' => '505800',
                'address' => 'DN73A km 12, Zărnești',
                'bank_name' => 'BT',
                'bank_account' => 'RO49BTRL0000000000NORDVALE',
                'invoice_series' => 'NVL',
                'invoice_next_number' => 1,
                'is_default' => true,
                'is_active' => true,
            ])
        );
    }

    /* ------------------------------------------------------- venue + event */

    private function venue(Tenant $tenant): Venue
    {
        $existing = Venue::where('tenant_id', $tenant->id)->first();
        if ($existing) {
            return $existing;
        }
        return Venue::create($this->writableAttrs('venues', [
            'tenant_id' => $tenant->id,
            'name' => ['ro' => 'Nordvale Forest Reserve', 'en' => 'Nordvale Forest Reserve'],
            'slug' => 'nordvale-forest-reserve',
            'address' => 'DN73A km 12',
            'city' => 'Zărnești',
            'state' => 'Brașov',
            'country' => 'RO',
            'lat' => 45.5645,
            'lng' => 25.3320,
            'capacity' => 1200,
            'timezone' => 'Europe/Bucharest',
            'description' => ['ro' => 'Rezervație de 400 de hectare: pădure, lac glaciar și trasee marcate.'],
            'open_hours' => '09:00 - 20:00',
        ]));
    }

    /**
     * One umbrella event holds the whole season; the sellable things are its
     * ticket types. That is the leisure model — a venue does not run "events".
     */
    private function event(Tenant $tenant, Venue $venue): Event
    {
        $existing = Event::where('tenant_id', $tenant->id)->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        return Event::create($this->writableAttrs('events', [
            'tenant_id' => $tenant->id,
            'venue_id' => $venue->id,
            'title' => ['ro' => 'Nordvale — Sezon 2026', 'en' => 'Nordvale — Season 2026'],
            'slug' => ['ro' => 'nordvale-sezon-2026'],
            'short_description' => ['ro' => 'Acces, închirieri și experiențe ghidate în rezervație.'],
            'description' => ['ro' => '<p>Patru sute de hectare de pădure și un lac glaciar. Intri pe poartă, '
                . 'închiriezi ce vrei și rămâi cât vrei.</p><p>Toate experiențele se rezervă online, pe zi și '
                . 'interval orar.</p>'],
            'duration_mode' => 'range',
            'range_start_date' => CarbonImmutable::today()->subDays(30)->toDateString(),
            'range_end_date' => CarbonImmutable::today()->addDays(self::CAPACITY_DAYS)->toDateString(),
            'display_template' => 'leisure_venue',
            'status' => 'published',
            'is_published' => true,
            'is_featured' => true,
        ]));
    }

    /* -------------------------------------------------------- ticket types */

    /** @return array<string, TicketType> */
    private function ticketTypes(Event $event, ?TenantTaxRegistry $registry): array
    {
        // Weekend surcharge + high season — the resolver applies both, so the
        // storefront never has to know they exist.
        $weekend = [['days' => [6, 7], 'type' => 'percent', 'value' => 20]];
        $summer = [[
            'start_date' => CarbonImmutable::today()->startOfYear()->addMonths(5)->toDateString(),
            'end_date' => CarbonImmutable::today()->startOfYear()->addMonths(8)->subDay()->toDateString(),
            'type' => 'percent',
            'value' => 15,
        ]];

        $defs = [
            'acces_adult' => [
                'name' => 'Acces adult', 'service_category' => 'access', 'price_cents' => 5000,
                'description' => 'Acces în rezervație, toată ziua. Trasee marcate și zona de plajă.',
                'daily_capacity' => 600, 'pricing_rules' => $weekend, 'seasons' => $summer,
                'channel_pricing' => ['online' => 5000, 'pos_fixed' => 5500, 'mobile' => 5200],
            ],
            'acces_copil' => [
                'name' => 'Acces copil (6–14 ani)', 'service_category' => 'access', 'price_cents' => 2500,
                'description' => 'Sub 6 ani intrarea este gratuită.',
                'daily_capacity' => 400, 'pricing_rules' => $weekend, 'seasons' => $summer,
            ],
            'acces_familie' => [
                'name' => 'Pass familie (2+2)', 'service_category' => 'access', 'price_cents' => 13000,
                'description' => 'Doi adulți și doi copii, o zi întreagă.',
                'daily_capacity' => 120, 'seasons' => $summer,
            ],
            'caiac' => [
                'name' => 'Caiac', 'service_category' => 'rental', 'price_cents' => 4000,
                'description' => 'Caiac de 1 sau 2 locuri, vestă inclusă.',
                'variants' => [
                    ['duration_minutes' => 30, 'label' => '30 min', 'price_multiplier' => 1.0],
                    ['duration_minutes' => 60, 'label' => '1 oră', 'price_multiplier' => 1.75],
                    ['duration_minutes' => 120, 'label' => '2 ore', 'price_multiplier' => 3.0],
                ],
                'overtime' => [1500, 15], 'daily_capacity' => 60,
            ],
            'sup' => [
                'name' => 'SUP (stand-up paddle)', 'service_category' => 'rental', 'price_cents' => 3500,
                'description' => 'Placă SUP cu padelă și vestă.',
                'variants' => [
                    ['duration_minutes' => 30, 'label' => '30 min', 'price_multiplier' => 1.0],
                    ['duration_minutes' => 60, 'label' => '1 oră', 'price_multiplier' => 1.7],
                    ['duration_minutes' => 120, 'label' => '2 ore', 'price_multiplier' => 2.9],
                ],
                'overtime' => [1200, 15], 'daily_capacity' => 40, 'slots' => ['10:00', '20:00', 60],
            ],
            'bicicleta' => [
                'name' => 'Bicicletă MTB', 'service_category' => 'rental', 'price_cents' => 3000,
                'description' => 'MTB hardtail, cască inclusă. Trasee marcate în rezervație.',
                'variants' => [
                    ['duration_minutes' => 60, 'label' => '1 oră', 'price_multiplier' => 1.0],
                    ['duration_minutes' => 240, 'label' => '4 ore', 'price_multiplier' => 3.2],
                    ['duration_minutes' => 480, 'label' => 'toată ziua', 'price_multiplier' => 5.0],
                ],
                'overtime' => [1000, 30], 'daily_capacity' => 80,
            ],
            'tur_ghidat' => [
                'name' => 'Tur ghidat pe lac', 'service_category' => 'activity', 'price_cents' => 9000,
                'description' => '90 de minute cu ghid, grup de maximum 10 persoane.',
                'daily_capacity' => 40, 'slots' => ['10:00', '18:00', 90],
            ],
            'bar_cafea' => [
                'name' => 'Cafea', 'service_category' => 'access', 'price_cents' => 1200,
                'description' => 'Articol de bar — vândut doar la POS.',
            ],
            'bar_limonada' => [
                'name' => 'Limonadă de casă', 'service_category' => 'access', 'price_cents' => 1800,
                'description' => 'Articol de bar — vândut doar la POS.',
            ],
        ];

        $out = [];
        $sort = 0;
        foreach ($defs as $key => $d) {
            $existing = TicketType::where('event_id', $event->id)->where('name', $d['name'])->first();
            if ($existing) {
                $out[$key] = $existing;
                continue;
            }

            $attrs = [
                'event_id' => $event->id,
                'name' => $d['name'],
                'description' => $d['description'] ?? '',
                'sku' => strtoupper(str_replace('_', '-', $key)),
                'service_category' => $d['service_category'],
                'price_cents' => $d['price_cents'],
                'price' => $d['price_cents'] / 100,
                'price_max' => $d['price_cents'] / 100,
                'currency' => 'RON',
                'status' => 'active',
                'sort_order' => $sort += 10,
                'quota_total' => -1,          // capacity is governed per-day
                'quota_sold' => 0,
                'max_per_order' => 10,
                'min_per_order' => 1,
                'leisure_duration_variants' => $d['variants'] ?? null,
                'leisure_pricing_rules' => $d['pricing_rules'] ?? null,
                'leisure_seasons' => $d['seasons'] ?? null,
                'leisure_is_overtime_chargeable' => isset($d['overtime']),
                'leisure_overtime_surcharge_cents' => $d['overtime'][0] ?? null,
                'leisure_overtime_interval_minutes' => $d['overtime'][1] ?? null,
                'leisure_default_daily_capacity' => $d['daily_capacity'] ?? null,
                'leisure_schedule_open_time' => $d['slots'][0] ?? '09:00',
                'leisure_schedule_close_time' => $d['slots'][1] ?? '20:00',
                'leisure_schedule_days' => [1, 2, 3, 4, 5, 6, 7],
                'leisure_slot_duration_minutes' => $d['slots'][2] ?? null,
                'channel_pricing' => $d['channel_pricing'] ?? null,
            ];
            if ($registry && Schema::hasColumn('ticket_types', 'issuing_tax_registry_id')) {
                $attrs['issuing_tax_registry_id'] = $registry->id;
            }

            $out[$key] = TicketType::create($this->writableAttrs('ticket_types', $attrs));
        }

        return $out;
    }

    /**
     * Capacity rows are what the public calendar reads. Bar items get none —
     * they are POS-only and must not appear as bookable days.
     *
     * @param array<string, TicketType> $ticketTypes
     */
    private function capacity(Tenant $tenant, array $ticketTypes): void
    {
        if (! Schema::hasTable('ticket_type_capacities')) {
            return;
        }

        $start = CarbonImmutable::today()->subDay();
        $bookable = array_diff_key($ticketTypes, array_flip(['bar_cafea', 'bar_limonada']));

        foreach ($bookable as $key => $tt) {
            $daily = (int) ($tt->leisure_default_daily_capacity ?: 100);
            $slotMinutes = (int) ($tt->leisure_slot_duration_minutes ?: 0);

            for ($i = 0; $i < self::CAPACITY_DAYS; $i++) {
                $date = $start->addDays($i);

                // Mondays out of season: the reserve is closed for maintenance.
                $closed = $date->dayOfWeekIso === 1 && (int) $date->format('n') >= 10;

                if ($slotMinutes > 0) {
                    // Slot-based: split the opening window into fixed intervals.
                    $open = CarbonImmutable::parse($date->toDateString() . ' ' . ($tt->leisure_schedule_open_time ?: '10:00'));
                    $close = CarbonImmutable::parse($date->toDateString() . ' ' . ($tt->leisure_schedule_close_time ?: '18:00'));
                    $perSlot = max(1, (int) floor($daily / max(1, (int) floor($open->diffInMinutes($close) / $slotMinutes))));

                    for ($t = $open; $t->lt($close); $t = $t->addMinutes($slotMinutes)) {
                        TicketTypeCapacity::firstOrCreate([
                            'tenant_id' => $tenant->id,
                            'ticket_type_id' => $tt->id,
                            'capacity_date' => $date->toDateString(),
                            'time_slot_start' => $t->format('H:i:s'),
                        ], $this->writableAttrs('ticket_type_capacities', [
                            'tenant_id' => $tenant->id,
                            'ticket_type_id' => $tt->id,
                            'capacity_date' => $date->toDateString(),
                            'time_slot_start' => $t->format('H:i:s'),
                            'time_slot_end' => $t->addMinutes($slotMinutes)->format('H:i:s'),
                            'capacity' => $perSlot,
                            'sold' => 0,
                            'reserved' => 0,
                            'is_closed' => $closed,
                        ]));
                    }
                    continue;
                }

                // Whole-day capacity. Seed a little realistic occupancy on the
                // next two weekends so the calendar shows "limited" and
                // "sold_out" states instead of a wall of green.
                $sold = 0;
                if ($i > 0 && $i < 16 && in_array($date->dayOfWeekIso, [6, 7], true)) {
                    $sold = $i < 9 ? (int) round($daily * 0.97) : (int) round($daily * 0.85);
                }

                TicketTypeCapacity::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'ticket_type_id' => $tt->id,
                    'capacity_date' => $date->toDateString(),
                    'time_slot_start' => null,
                ], $this->writableAttrs('ticket_type_capacities', [
                    'tenant_id' => $tenant->id,
                    'ticket_type_id' => $tt->id,
                    'capacity_date' => $date->toDateString(),
                    'time_slot_start' => null,
                    'time_slot_end' => null,
                    'capacity' => $daily,
                    'sold' => $sold,
                    'reserved' => 0,
                    'is_closed' => $closed,
                ]));
            }
        }
    }

    /* ----------------------------------------------- physical rental stock */

    /**
     * Resource TYPES back the public rentals catalogue; the units under them
     * back the operator panel's start/end rental flow and the live "N
     * available" badge on the site.
     *
     * @param array<string, TicketType> $ticketTypes
     */
    private function resourceTypes(Tenant $tenant, array $ticketTypes): void
    {
        if (! Schema::hasTable('physical_resource_types')) {
            return;
        }

        $defs = [
            ['slug' => 'caiac', 'name' => 'Caiac', 'icon' => '🛶', 'color' => '#09251d',
             'description' => 'Caiace de 1 și 2 locuri, veste incluse.',
             'tt' => 'caiac', 'units' => ['Caiac Pin #1', 'Caiac Pin #2', 'Caiac Pin #3',
                                          'Caiac Mesteacăn #1', 'Caiac Mesteacăn #2', 'Caiac Mesteacăn #3']],
            ['slug' => 'sup', 'name' => 'SUP', 'icon' => '🏄', 'color' => '#dffc62',
             'description' => 'Plăci stand-up paddle, padelă și vestă incluse.',
             'tt' => 'sup', 'units' => ['SUP #1', 'SUP #2', 'SUP #3', 'SUP #4']],
            ['slug' => 'bicicleta-mtb', 'name' => 'Bicicletă MTB', 'icon' => '🚲', 'color' => '#f27b4a',
             'description' => 'MTB hardtail, mărimi M–XL, cască inclusă.',
             'tt' => 'bicicleta', 'units' => ['MTB-01', 'MTB-02', 'MTB-03', 'MTB-04',
                                              'MTB-05', 'MTB-06', 'MTB-07', 'MTB-08']],
        ];

        foreach ($defs as $d) {
            $tt = $ticketTypes[$d['tt']] ?? null;
            if (! $tt) {
                continue;
            }

            $type = PhysicalResourceType::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $d['slug']],
                $this->writableAttrs('physical_resource_types', [
                    'tenant_id' => $tenant->id,
                    'slug' => $d['slug'],
                    'name' => $d['name'],
                    'description' => $d['description'],
                    'icon' => $d['icon'],
                    'color' => $d['color'],
                    'is_active' => true,
                    'linked_ticket_type_ids' => [$tt->id],
                ])
            );

            foreach ($d['units'] as $i => $unitName) {
                if (PhysicalResource::where('tenant_id', $tenant->id)->where('name', $unitName)->exists()) {
                    continue;
                }
                $attrs = [
                    'tenant_id' => $tenant->id,
                    'resource_type' => $d['slug'],
                    'name' => $unitName,
                    'label' => strtoupper(substr($d['slug'], 0, 3)) . '-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'qr_code' => PhysicalResource::generateQrCode($tenant->id, $d['slug']),
                    // Leave two units out of service so the operator panel has
                    // something to show in every status.
                    'status' => $i === 0 && $d['slug'] === 'bicicleta-mtb'
                        ? PhysicalResource::STATUS_MAINTENANCE
                        : PhysicalResource::STATUS_AVAILABLE,
                    'linked_ticket_type_ids' => [$tt->id],
                    'meta' => ['condition' => 'good'],
                ];
                if (Schema::hasColumn('physical_resources', 'physical_resource_type_id')) {
                    $attrs['physical_resource_type_id'] = $type->id;
                }
                PhysicalResource::create($this->writableAttrs('physical_resources', $attrs));
            }
        }
    }

    /* ----------------------------------------------------------- operators */

    /**
     * /operator access is gated on an ACTIVE TenantTeamMember row; the panel
     * then filters its pages by leisure_role (POS only for cashiers/managers).
     */
    private function team(Tenant $tenant): void
    {
        if (! Schema::hasTable('tenant_team_members')) {
            return;
        }

        $defs = [
            ['email' => 'manager@nordvale.demo', 'name' => 'Mihai Manager',
             'role' => TenantTeamMember::ROLE_MANAGER, 'leisure_role' => 'admin'],
            ['email' => 'casier@nordvale.demo', 'name' => 'Corina Casier',
             'role' => TenantTeamMember::ROLE_STAFF, 'leisure_role' => 'pos_cashier'],
            ['email' => 'receptie@nordvale.demo', 'name' => 'Radu Recepție',
             'role' => TenantTeamMember::ROLE_STAFF, 'leisure_role' => 'check_in'],
            ['email' => 'rentals@nordvale.demo', 'name' => 'Raluca Rentals',
             'role' => TenantTeamMember::ROLE_STAFF, 'leisure_role' => 'rental_operator'],
            ['email' => 'inventar@nordvale.demo', 'name' => 'Ionuț Inventar',
             'role' => TenantTeamMember::ROLE_STAFF, 'leisure_role' => 'inventory_manager'],
        ];

        foreach ($defs as $d) {
            $user = $this->user($d['email'], $d['name'], self::OPERATOR_PASSWORD);
            TenantTeamMember::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                $this->writableAttrs('tenant_team_members', [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => $d['role'],
                    'leisure_role' => $d['leisure_role'],
                    'status' => TenantTeamMember::STATUS_ACTIVE,
                    'accepted_at' => now(),
                ])
            );
        }
    }
}
