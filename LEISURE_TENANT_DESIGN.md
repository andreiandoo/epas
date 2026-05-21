# Leisure Tenant — Design Document E0-E3

**Scope:** Construcția nativă în core Tixello a unui tip nou de tenant — `leisure` — pentru locații de agrement (parcuri, aquaparc, castele, camping etc.). Doar clienții **NOI** ai Tixello. Sf. Ana rămâne în Ambilet ca `marketplace_organizer` și nu se atinge.

**Strategie:** Reconstrucție nativă Filament 4 / Livewire 3 / Laravel 12. Codul Ambilet leisure (`epas/resources/marketplaces/ambilet/`) e doar inspirație — nu portăm direct, nu reutilizăm wrapper.

**Non-breaking guarantee:**
- Zero modificări pe tabele/migrări existente folosite de Ambilet (`marketplace_organizers`, `marketplace_event_date_capacities`, `marketplace_organizer_team_members`, `marketplace_tax_registries`, `leisure_boats`, `boat_rentals`, `leisure_shifts`, `leisure_resource_locks`)
- Toate adăugirile noi: tabele/coloane noi cu prefix `tenant_` sau dedicat `leisure_` doar pentru core, sau coloane noi pe tabele core (`tenants`, `ticket_types`, `orders`) cu default-uri sane
- Toate field-urile noi pe `ticket_types` sunt `nullable` cu default null/false → ticket-urile existente nu sunt afectate
- `TenantType::Leisure` nu modifică niciun behavior existent — doar activează feature flags / form fields condiționate când tenant_type === 'leisure'

**Estimare cumulativă E0-E3:** ~10-14 zile dezvoltare (1 dev senior, fără break-uri).

---

## E0 — Fundație arhitecturală

**Obiectiv:** TenantType::Leisure devine selecție validă; tenant-ul nou se poate crea cu defaults sensibile, dar fără funcționalitate operațională încă.

**Durată:** 1-2 zile

### E0.1 — Enum TenantType

[epas/app/Enums/TenantType.php](epas/app/Enums/TenantType.php)

Adaug case nou + label + defaults microservices:

```php
case Leisure = 'leisure';

// În label():
self::Leisure => 'Leisure Venue',

// În defaultMicroserviceSlugs():
self::Leisure => [
    'analytics',
    'crm',
    'door-sales',
    'efactura',
    'accounting',
    // microservices noi (E0.3):
    'leisure-core',
    'leisure-pos',
    'leisure-rentals',
    'leisure-multi-society',
    'leisure-embed',
],
```

### E0.2 — Migrare features defaults pentru leisure

Migrare nouă: `2026_05_22_100000_add_leisure_defaults_to_features_json.php`

Nu modific schema — doar adaug un `LeisureFeatureDefaults` service care, la `Tenant::created` event când `tenant_type === 'leisure'`, populează `features` JSON cu:

```json
{
  "leisure.enabled": true,
  "leisure.rentals.enabled": true,
  "leisure.pos.enabled": true,
  "leisure.time_slots.enabled": false,
  "leisure.physical_inventory.enabled": true,
  "leisure.multi_society.enabled": false,
  "leisure.channel_pricing.enabled": false,
  "leisure.embed.enabled": false,
  "leisure.crm.enabled": true
}
```

Observer: [epas/app/Observers/TenantObserver.php](epas/app/Observers/TenantObserver.php) (creare nouă sau extindere existent). Listener pe `creating`/`created`. Doar dacă `features` e null/empty, nu suprascrie ce e deja setat.

### E0.3 — Microservices noi (seeder)

Seeder nou: `database/seeders/LeisureMicroservicesSeeder.php`

Inserează 5 microservices:
- `leisure-core` — Funcționalitate de bază (capacity, calendar, subscription tickets)
- `leisure-pos` — Vânzare la fața locului + chitanță print
- `leisure-rentals` — Rentals cu durate variabile + depășire
- `leisure-multi-society` — Multi-CIF per tenant
- `leisure-embed` — Embed widget + sub-site

Toate cu translation key-uri în RO/EN. Activate by default doar pentru tenant_type='leisure' prin defaultMicroserviceSlugs.

Înregistrat în `DatabaseSeeder` ca opțional (idempotent — verifică dacă slug-ul există înainte de insert).

### E0.4 — Filament TenantResource: tab "Leisure"

[epas/app/Filament/Resources/Tenants/TenantResource.php](epas/app/Filament/Resources/Tenants/TenantResource.php)

Tab nou condiționat:

```php
Tab::make('Leisure')
    ->visible(fn ($get) => $get('tenant_type') === 'leisure')
    ->schema([
        Section::make('Feature Flags')
            ->schema([
                Toggle::make('features.leisure.rentals.enabled')->label('Rentals'),
                Toggle::make('features.leisure.pos.enabled')->label('POS la fața locului'),
                Toggle::make('features.leisure.time_slots.enabled')->label('Capacity pe slot orar'),
                Toggle::make('features.leisure.physical_inventory.enabled')->label('Inventar fizic + QR'),
                Toggle::make('features.leisure.multi_society.enabled')->label('Multi-societate'),
                Toggle::make('features.leisure.channel_pricing.enabled')->label('Prețuri per canal'),
                Toggle::make('features.leisure.embed.enabled')->label('Embed widget'),
                Toggle::make('features.leisure.crm.enabled')->label('CRM activat'),
            ])->columns(2),
        // Placeholder pentru tab-urile E1-E3:
        Section::make('Tickets, Capacity, Rentals')
            ->description('Gestionează din meniul lateral după ce salvezi tenant-ul.')
            ->schema([]),
    ]),
```

State path `features.leisure.*` cu cast `'features' => 'array'` pe Tenant model (verificare că există deja).

### E0.5 — Onboarding leisure branching

[epas/app/Http/Controllers/OnboardingController.php](epas/app/Http/Controllers/OnboardingController.php)

Step 2 (account type) — `leisure` apare ca opțiune cu copy:
> "Locație de agrement — parc, aquaparc, castel, camping. Vinzi bilete de acces și/sau servicii (rentals, parcare etc.)."

Step 5 (plan & microservices) — dacă `tenant_type === 'leisure'`:
- Plan default: `1percent` (sau ce decidem comercial)
- Microservices auto-checked din `TenantType::Leisure->defaultMicroserviceSlugs()`
- Mesaj orientativ: "Vei putea configura POS, rentals, echipa și multi-societate după onboarding, în Setări → Leisure."

### E0.6 — Tests

`tests/Feature/Leisure/E0FundationTest.php`:
- ✅ Tenant::create cu tenant_type='leisure' populează features JSON corect
- ✅ Default microservices se atașează automat la creare
- ✅ Filament tab "Leisure" e vizibil doar pentru tenant_type='leisure'
- ✅ Onboarding cu tenant_type='leisure' completează fără erori până la step 5

### E0 livrabile

| Fișier | Acțiune |
|---|---|
| `epas/app/Enums/TenantType.php` | Add Leisure case |
| `epas/app/Observers/TenantObserver.php` | Create/extend cu LeisureFeatureDefaults |
| `epas/app/Providers/EventServiceProvider.php` | Register observer |
| `epas/database/seeders/LeisureMicroservicesSeeder.php` | Create |
| `epas/app/Filament/Resources/Tenants/TenantResource.php` | Add Leisure tab |
| `epas/app/Http/Controllers/OnboardingController.php` | Extend step 2 + step 5 |
| `epas/resources/lang/{ro,en}/leisure.php` | Translation keys |
| `epas/tests/Feature/Leisure/E0FundationTest.php` | Tests |

**Niciun cod existent nu se șterge / modifică distructiv.**

---

## E1 — TicketType extins pentru leisure

**Obiectiv:** Un tenant leisure poate defini bilete cu: durate variabile (rentals 30min/1h/2h), pricing rules per zi (weekend ±%, sezon), categorie servicii.

**Durată:** 3-4 zile

### E1.1 — Migrare extensii TicketType

Migrare: `2026_05_22_110000_extend_ticket_types_for_leisure.php`

Adaug câmpuri NOI nullable pe `ticket_types`:

```php
$table->json('leisure_duration_variants')->nullable()
    ->comment('Variante durată pentru rentals: [{duration_minutes: 60, label: "1h", price_multiplier: 1.5}]');

$table->json('leisure_pricing_rules')->nullable()
    ->comment('Reguli pricing per zi/sezon: [{label, days: [1,2,3], type: percent|fixed, value, season_id?}]');

$table->json('leisure_seasons')->nullable()
    ->comment('Sezoane pentru ticket: [{label, start_date, end_date, schedule, last_entry}]');

$table->boolean('leisure_is_overtime_chargeable')->default(false)
    ->comment('Pentru rentals: dacă se aplică surcharge la depășire durată');

$table->integer('leisure_overtime_surcharge_cents')->nullable()
    ->comment('Cost depășire per overtime_interval_minutes');

$table->integer('leisure_overtime_interval_minutes')->nullable()
    ->comment('Interval pe care se calculează surcharge (ex: 30 min)');
```

**De ce câmpuri noi nullable cu prefix `leisure_`:**
- Zero impact pe ticket_types existente (toate rămân null)
- Prefix clar = când scoatem la lumină în queries, e evident că e leisure-specific
- Nu duplicăm `service_category`, `service_duration_minutes`, `is_subscription` care există deja

**Reutilizez fără modificare:**
- `service_category` (parking/rental/food etc — ENUM existent)
- `service_duration_minutes` (durata default a serviciului)
- `is_subscription` (abonament)
- `is_entry_ticket` (bilet de acces principal)
- `valid_date` (dată fixă pentru bilet)
- `sale_stock` (stoc independent)

### E1.2 — Model: cast-uri și helpers

[epas/app/Models/TicketType.php](epas/app/Models/TicketType.php)

```php
protected $casts = [
    // ... existing ...
    'leisure_duration_variants' => 'array',
    'leisure_pricing_rules' => 'array',
    'leisure_seasons' => 'array',
    'leisure_is_overtime_chargeable' => 'boolean',
];

public function isLeisureRental(): bool
{
    return in_array($this->service_category, ['rental', 'activity'])
        && filled($this->leisure_duration_variants);
}

public function getDurationVariantsCollection(): \Illuminate\Support\Collection
{
    return collect($this->leisure_duration_variants ?? [])
        ->map(fn ($v) => (object) array_merge([
            'duration_minutes' => null,
            'label' => null,
            'price_multiplier' => 1.0,
        ], $v));
}
```

### E1.3 — Service: LeisurePricingResolver

Service nou: [epas/app/Services/Leisure/LeisurePricingResolver.php](epas/app/Services/Leisure/LeisurePricingResolver.php)

Responsabilitate: calculează prețul efectiv al unui ticket type pentru o dată specifică, aplicând `leisure_pricing_rules` + `leisure_seasons`.

```php
class LeisurePricingResolver
{
    public function resolvePrice(
        TicketType $ticketType,
        \DateTimeInterface $forDate,
        ?int $durationMinutes = null,
    ): int {
        $basePrice = $ticketType->price_cents;
        
        // 1. Aplică duration variant dacă există
        if ($durationMinutes !== null) {
            $variant = $ticketType->getDurationVariantsCollection()
                ->firstWhere('duration_minutes', $durationMinutes);
            if ($variant) {
                $basePrice = (int) round($basePrice * $variant->price_multiplier);
            }
        }
        
        // 2. Aplică pricing rules pentru ziua săptămânii
        $dayOfWeek = (int) $forDate->format('N'); // 1=Mon, 7=Sun
        foreach (($ticketType->leisure_pricing_rules ?? []) as $rule) {
            if (!in_array($dayOfWeek, $rule['days'] ?? [])) {
                continue;
            }
            $basePrice = $this->applyRule($basePrice, $rule);
        }
        
        // 3. Aplică sezon dacă data e în interval
        // ... similar logic
        
        return $basePrice;
    }
    
    private function applyRule(int $price, array $rule): int
    {
        return match ($rule['type']) {
            'percent' => (int) round($price * (1 + ($rule['value'] / 100))),
            'fixed' => $price + ($rule['value'] * 100),
            default => $price,
        };
    }
}
```

Folosit în: checkout flow (calcul preț la add-to-cart), API public availability, POS UI.

### E1.4 — Filament: TicketType resource extensions

[epas/app/Filament/Resources/Tickets/TicketTypeResource.php](epas/app/Filament/Resources/Tickets/TicketTypeResource.php) (path probabil)

Adaug secțiuni condiționate pe `tenant.tenant_type === 'leisure'`:

```php
Section::make('Configurări Leisure')
    ->visible(fn ($livewire) => $livewire->getOwnerRecord()?->tenant?->tenant_type === 'leisure')
    ->schema([
        Select::make('service_category')
            ->options(['access' => 'Bilet acces', 'parking' => 'Parcare', 'rental' => 'Rental', 'activity' => 'Activitate', 'food' => 'Mâncare', 'extra' => 'Extra']),
        Toggle::make('is_subscription')->label('Abonament'),
        Toggle::make('is_entry_ticket')->label('Bilet de acces principal'),
        DatePicker::make('valid_date')->label('Valabil doar pentru data:'),
        Repeater::make('leisure_duration_variants')
            ->label('Variante durată (rentals)')
            ->schema([
                TextInput::make('duration_minutes')->numeric()->suffix('min'),
                TextInput::make('label'),
                TextInput::make('price_multiplier')->numeric()->step(0.01),
            ])
            ->columns(3)
            ->visible(fn ($get) => in_array($get('service_category'), ['rental', 'activity'])),
        Toggle::make('leisure_is_overtime_chargeable')->label('Surcharge la depășire'),
        Group::make([
            TextInput::make('leisure_overtime_surcharge_cents')->numeric()->prefix('cents'),
            TextInput::make('leisure_overtime_interval_minutes')->numeric()->suffix('min'),
        ])->columns(2)->visible(fn ($get) => $get('leisure_is_overtime_chargeable')),
        Repeater::make('leisure_pricing_rules')
            ->label('Reguli preț per zi')
            ->schema([
                TextInput::make('label'),
                CheckboxList::make('days')->options([1=>'Luni', 2=>'Marți', /* ... */ 7=>'Duminică'])->columns(7),
                Select::make('type')->options(['percent' => '% Modificare', 'fixed' => 'Sumă fixă RON']),
                TextInput::make('value')->numeric(),
            ]),
        Repeater::make('leisure_seasons')
            ->label('Sezoane')
            ->schema([
                TextInput::make('label'),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
                TextInput::make('last_entry')->placeholder('17:00'),
            ]),
    ])->collapsible(),
```

### E1.5 — Tests

`tests/Unit/Leisure/LeisurePricingResolverTest.php`:
- ✅ Preț de bază fără reguli = price_cents original
- ✅ Duration variant multiplier aplicat corect (60min × 1.5)
- ✅ Weekend rule +25% aplicată doar sâmbătă/duminică
- ✅ Fixed reduction -20 RON din price_cents corect
- ✅ Multiple rules cumulate (weekend +25% și sezon vară +10%)
- ✅ Sezon nu aplică dacă data e în afara intervalului

### E1 livrabile

| Fișier | Acțiune |
|---|---|
| `epas/database/migrations/2026_05_22_110000_extend_ticket_types_for_leisure.php` | Create |
| `epas/app/Models/TicketType.php` | Add casts + helpers |
| `epas/app/Services/Leisure/LeisurePricingResolver.php` | Create |
| `epas/app/Filament/Resources/Tickets/TicketTypeResource.php` | Add leisure section |
| `epas/tests/Unit/Leisure/LeisurePricingResolverTest.php` | Tests |

---

## E2 — Capacity pe zi + slot-uri orare

**Obiectiv:** Operatorul leisure poate seta stoc per ticket type per dată (`2026-07-15: 200 locuri`) sau per slot orar (`2026-07-15 10:00-11:00: 30 locuri`). Frontend public arată calendar cu disponibilitate vizuală.

**Durată:** 2-3 zile

### E2.1 — Migrare ticket_type_capacities

Migrare: `2026_05_22_120000_create_ticket_type_capacities_table.php`

```php
Schema::create('ticket_type_capacities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
    $table->date('capacity_date');
    $table->time('time_slot_start')->nullable();  // null = capacity pe toată ziua
    $table->time('time_slot_end')->nullable();
    $table->unsignedInteger('capacity');           // total disponibil
    $table->unsignedInteger('sold')->default(0);   // contor vânzări
    $table->unsignedInteger('reserved')->default(0); // bilete în cart, not paid yet
    $table->boolean('is_closed')->default(false);  // operator a închis ziua/slotul
    $table->integer('price_override_cents')->nullable(); // override LeisurePricingResolver
    $table->string('note')->nullable();
    $table->timestamps();
    
    $table->unique(['ticket_type_id', 'capacity_date', 'time_slot_start'], 'unique_capacity_slot');
    $table->index(['tenant_id', 'capacity_date']);
});
```

**De ce tabela nouă vs reutilizarea `marketplace_event_date_capacities`:** Memory rule "live site safety" + decizia "Sf. Ana rămâne marketplace, clienții NOI = tenant". `marketplace_event_date_capacities` aparține contextului marketplace_organizer și e folosit live de Ambilet — non-breaking guarantee zero modificări acolo.

### E2.2 — Model + scopes

[epas/app/Models/Leisure/TicketTypeCapacity.php](epas/app/Models/Leisure/TicketTypeCapacity.php)

```php
class TicketTypeCapacity extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'tenant_id', 'ticket_type_id', 'capacity_date', 'time_slot_start', 'time_slot_end',
        'capacity', 'sold', 'reserved', 'is_closed', 'price_override_cents', 'note',
    ];
    
    protected $casts = [
        'capacity_date' => 'date',
        'time_slot_start' => 'datetime:H:i:s',
        'time_slot_end' => 'datetime:H:i:s',
        'is_closed' => 'boolean',
    ];
    
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function getRemainingAttribute(): int
    {
        return max(0, $this->capacity - $this->sold - $this->reserved);
    }
    
    public function getStatusAttribute(): string
    {
        if ($this->is_closed) return 'closed';
        if ($this->remaining === 0) return 'sold_out';
        if ($this->remaining < ($this->capacity * 0.2)) return 'limited';
        return 'available';
    }
    
    public function scopeForDate($q, $date) {
        return $q->whereDate('capacity_date', $date);
    }
}
```

### E2.3 — Service: CapacityAvailabilityService

[epas/app/Services/Leisure/CapacityAvailabilityService.php](epas/app/Services/Leisure/CapacityAvailabilityService.php)

```php
class CapacityAvailabilityService
{
    public function __construct(
        private LeisurePricingResolver $pricing,
    ) {}
    
    /**
     * Returnează disponibilitate pentru un interval de date.
     * Folosit pentru calendar picker.
     */
    public function getAvailabilityForMonth(
        int $tenantId,
        \DateTimeInterface $monthStart,
        ?int $ticketTypeId = null,
    ): array {
        $monthEnd = (clone $monthStart)->modify('last day of this month');
        
        $capacities = TicketTypeCapacity::query()
            ->where('tenant_id', $tenantId)
            ->when($ticketTypeId, fn ($q) => $q->where('ticket_type_id', $ticketTypeId))
            ->whereBetween('capacity_date', [$monthStart, $monthEnd])
            ->get()
            ->groupBy(fn ($c) => $c->capacity_date->toDateString());
        
        $result = [];
        foreach ($capacities as $date => $items) {
            $totalRemaining = $items->sum('remaining');
            $minPrice = $items->min(fn ($c) => $c->price_override_cents
                ?? $this->pricing->resolvePrice($c->ticketType, $c->capacity_date));
            
            $result[$date] = [
                'status' => $this->aggregateStatus($items),
                'remaining' => $totalRemaining,
                'min_price_cents' => $minPrice,
            ];
        }
        return $result;
    }
    
    public function reserve(int $capacityId, int $quantity): bool
    {
        return DB::transaction(function () use ($capacityId, $quantity) {
            $row = TicketTypeCapacity::lockForUpdate()->findOrFail($capacityId);
            if ($row->remaining < $quantity) {
                return false;
            }
            $row->increment('reserved', $quantity);
            return true;
        });
    }
    
    public function confirm(int $capacityId, int $quantity): void
    {
        DB::transaction(function () use ($capacityId, $quantity) {
            $row = TicketTypeCapacity::lockForUpdate()->findOrFail($capacityId);
            $row->decrement('reserved', $quantity);
            $row->increment('sold', $quantity);
        });
    }
    
    public function release(int $capacityId, int $quantity): void
    {
        DB::transaction(function () use ($capacityId, $quantity) {
            $row = TicketTypeCapacity::lockForUpdate()->findOrFail($capacityId);
            $row->decrement('reserved', $quantity);
        });
    }
    
    private function aggregateStatus($items): string { /* ... */ }
}
```

### E2.4 — API public availability

Route: `GET /api/leisure/tenants/{tenant:slug}/ticket-types/{ticketType}/availability?month=YYYY-MM`

[epas/app/Http/Controllers/Api/Leisure/AvailabilityController.php](epas/app/Http/Controllers/Api/Leisure/AvailabilityController.php)

```php
public function show(Tenant $tenant, TicketType $ticketType, Request $request, CapacityAvailabilityService $service)
{
    abort_unless($tenant->tenant_type === 'leisure', 404);
    abort_unless($ticketType->tenant_id === $tenant->id, 404);
    
    $month = Carbon::parse($request->get('month', now()->format('Y-m')) . '-01');
    
    return response()->json([
        'tenant_id' => $tenant->id,
        'ticket_type_id' => $ticketType->id,
        'month' => $month->format('Y-m'),
        'dates' => $service->getAvailabilityForMonth($tenant->id, $month, $ticketType->id),
    ])->setMaxAge(60)->setPublic(); // cache 1 min
}
```

Rate limit: 60 req/min/IP (config).

### E2.5 — Filament: Capacity editor

Resursă Filament nouă: `app/Filament/Resources/Leisure/CapacityResource.php`

UI: tabel cu filtru per ticket_type + per dată range. Operations:
- Inline edit `capacity`, `sold` (readonly), `is_closed`
- Bulk action: "Setează capacitate pentru perioada X-Y, în zilele [Mon-Fri], la X locuri"
- Calendar view alternativ (Filament widget) cu code colors (verde/galben/roșu)

Sticky form action: "+ Adaugă slot orar" — apare modal cu `time_slot_start`, `time_slot_end`, `capacity`.

### E2.6 — Tests

`tests/Feature/Leisure/CapacityAvailabilityTest.php`:
- ✅ Capacity per zi (slot null) cu remaining calculat corect
- ✅ Capacity per slot orar — 2 sloturi pe 2026-07-15 cu remaining separat
- ✅ `reserve()` decrementează remaining atomic + lockForUpdate
- ✅ `confirm()` mută reserved → sold
- ✅ `release()` decrementează reserved fără efect pe sold
- ✅ API returnează 404 dacă tenant.tenant_type !== 'leisure'
- ✅ API cache 60s pe response

### E2 livrabile

| Fișier | Acțiune |
|---|---|
| `epas/database/migrations/2026_05_22_120000_create_ticket_type_capacities_table.php` | Create |
| `epas/app/Models/Leisure/TicketTypeCapacity.php` | Create |
| `epas/app/Services/Leisure/CapacityAvailabilityService.php` | Create |
| `epas/app/Http/Controllers/Api/Leisure/AvailabilityController.php` | Create |
| `epas/routes/api.php` | Add leisure API routes group |
| `epas/app/Filament/Resources/Leisure/CapacityResource.php` | Create |
| `epas/tests/Feature/Leisure/CapacityAvailabilityTest.php` | Tests |

---

## E3 — Rentals & inventar fizic

**Obiectiv:** Tenant leisure poate defini produse fizice (bărci, kayak, biciclete) cu QR-uri printabile; operatorul scanează QR-ul biletului + QR-ul echipamentului → start rental cu cronometru → finish cu calcul depășire automat.

**Durată:** 4-5 zile

### E3.1 — Migrare physical_resources

Migrare: `2026_05_22_130000_create_physical_resources_table.php`

```php
Schema::create('physical_resources', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('resource_type'); // 'boat', 'kayak', 'bike', 'sled', 'locker', custom
    $table->string('name');           // ex: "Barcă Roșie #3"
    $table->string('label')->nullable();
    $table->string('qr_code')->unique(); // ex: BOAT-{tenant_id}-{uuid8}
    $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])->default('available');
    $table->json('linked_ticket_type_ids')->nullable(); // ce TicketType-uri se pot rent cu acest produs
    $table->json('meta')->nullable(); // size, color, condition_notes, last_inspection_date
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['tenant_id', 'resource_type', 'status']);
});
```

### E3.2 — Migrare resource_rentals

Migrare: `2026_05_22_130100_create_resource_rentals_table.php`

```php
Schema::create('resource_rentals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('ticket_id')->constrained()->cascadeOnDelete(); // biletul plătit
    $table->foreignId('physical_resource_id')->constrained()->cascadeOnDelete();
    $table->foreignId('started_by_team_member_id')->nullable(); // FK la tenant_team_members (E4)
    $table->foreignId('ended_by_team_member_id')->nullable();
    $table->timestamp('started_at');
    $table->timestamp('planned_end_at'); // started_at + duration_minutes
    $table->timestamp('ended_at')->nullable();
    $table->integer('overtime_minutes')->default(0);
    $table->integer('overtime_surcharge_cents')->default(0);
    $table->boolean('surcharge_paid')->default(false);
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['tenant_id', 'started_at']);
    $table->index('physical_resource_id');
});
```

### E3.3 — Model PhysicalResource

[epas/app/Models/Leisure/PhysicalResource.php](epas/app/Models/Leisure/PhysicalResource.php)

```php
class PhysicalResource extends Model
{
    use SoftDeletes, HasFactory;
    
    protected $fillable = [/* ... */];
    protected $casts = [
        'meta' => 'array',
        'linked_ticket_type_ids' => 'array',
    ];
    
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function activeRental(): HasOne {
        return $this->hasOne(ResourceRental::class)->whereNull('ended_at');
    }
    public function rentals(): HasMany { return $this->hasMany(ResourceRental::class); }
    
    public static function generateQrCode(int $tenantId, string $resourceType): string
    {
        $prefix = strtoupper($resourceType);
        return "{$prefix}-{$tenantId}-" . Str::random(8);
    }
    
    public function scopeAvailable($q) {
        return $q->where('status', 'available');
    }
}
```

### E3.4 — Model ResourceRental

[epas/app/Models/Leisure/ResourceRental.php](epas/app/Models/Leisure/ResourceRental.php)

```php
class ResourceRental extends Model
{
    protected $fillable = [/* ... */];
    protected $casts = [
        'started_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'ended_at' => 'datetime',
        'surcharge_paid' => 'boolean',
    ];
    
    public function getIsActiveAttribute(): bool { return is_null($this->ended_at); }
    public function getIsOverdueAttribute(): bool {
        return $this->is_active && now()->greaterThan($this->planned_end_at);
    }
    public function getElapsedMinutesAttribute(): int {
        $end = $this->ended_at ?? now();
        return (int) $this->started_at->diffInMinutes($end);
    }
    public function getCurrentOvertimeMinutesAttribute(): int {
        if (!$this->is_overdue) return 0;
        return (int) $this->planned_end_at->diffInMinutes($this->ended_at ?? now());
    }
}
```

### E3.5 — Service: RentalService

[epas/app/Services/Leisure/RentalService.php](epas/app/Services/Leisure/RentalService.php)

```php
class RentalService
{
    public function start(Ticket $ticket, PhysicalResource $resource, ?int $teamMemberId = null): ResourceRental
    {
        // Validări
        abort_unless($ticket->status === 'valid', 422, 'Ticket invalid sau deja utilizat');
        abort_unless($resource->status === 'available', 422, 'Resursa nu e disponibilă');
        abort_unless($resource->tenant_id === $ticket->ticketType->tenant_id, 422);
        
        if (!empty($resource->linked_ticket_type_ids)
            && !in_array($ticket->ticket_type_id, $resource->linked_ticket_type_ids)) {
            abort(422, 'Acest tip de bilet nu permite rental al acestei resurse');
        }
        
        $duration = $ticket->ticketType->service_duration_minutes
            ?? 60; // fallback
        
        return DB::transaction(function () use ($ticket, $resource, $teamMemberId, $duration) {
            $resource->update(['status' => 'in_use']);
            
            return ResourceRental::create([
                'tenant_id' => $resource->tenant_id,
                'ticket_id' => $ticket->id,
                'physical_resource_id' => $resource->id,
                'started_by_team_member_id' => $teamMemberId,
                'started_at' => now(),
                'planned_end_at' => now()->addMinutes($duration),
            ]);
        });
    }
    
    public function end(ResourceRental $rental, ?int $teamMemberId = null): ResourceRental
    {
        abort_if($rental->ended_at, 422, 'Rental deja încheiat');
        
        return DB::transaction(function () use ($rental, $teamMemberId) {
            $overtime = $rental->current_overtime_minutes;
            $surcharge = $this->calculateSurcharge($rental, $overtime);
            
            $rental->update([
                'ended_at' => now(),
                'ended_by_team_member_id' => $teamMemberId,
                'overtime_minutes' => $overtime,
                'overtime_surcharge_cents' => $surcharge,
            ]);
            
            $rental->physicalResource->update(['status' => 'available']);
            
            return $rental->fresh();
        });
    }
    
    public function calculateSurcharge(ResourceRental $rental, int $overtimeMinutes): int
    {
        $tt = $rental->ticket->ticketType;
        if (!$tt->leisure_is_overtime_chargeable || $overtimeMinutes <= 0) {
            return 0;
        }
        $interval = $tt->leisure_overtime_interval_minutes ?? 30;
        $intervalsExceeded = (int) ceil($overtimeMinutes / $interval);
        return $intervalsExceeded * ($tt->leisure_overtime_surcharge_cents ?? 0);
    }
}
```

### E3.6 — Filament: PhysicalResourceResource

`app/Filament/Resources/Leisure/PhysicalResourceResource.php`

- Tabel: name, resource_type, qr_code, status (cu badge color), active_rental info
- Form: resource_type select, name, label, linked_ticket_type_ids multi-select, meta KeyValue
- Bulk action: **"Print QR codes"** → randează `resources/views/leisure/qr-print.blade.php` cu grid 4-col (200x200px QR via `endroid/qr-code` library)
- Action per row: "Set maintenance" / "Restore to available"

### E3.7 — Filament: ResourceRentalResource (read-only audit)

Tabel cu filter active/ended/overdue. Coloane: started_at, ticket.code, resource.name, elapsed, status, surcharge. Filter "Overdue active" implicit.

Acțiune manuală (admin only): "Forțează închidere rental".

### E3.8 — API endpoints pentru operator (precursor pentru E7)

- `POST /api/leisure/rentals/start` — body: `{ ticket_code, resource_qr_code }`
- `POST /api/leisure/rentals/{rental}/end` — body: `{ team_member_id }`
- `GET /api/leisure/rentals/active` — listă rentals active pentru tenant operator
- `GET /api/leisure/resources?status=available&resource_type=boat` — disponibile pentru rental

Auth: middleware `tenant.operator` (impl complet în E4). Pentru E3, folosim middleware temporar `auth:sanctum` + scope manual `tenant_id`.

### E3.9 — Tests

`tests/Feature/Leisure/RentalServiceTest.php`:
- ✅ Start rental setează status=in_use pe resource
- ✅ Start refuză dacă ticket deja folosit
- ✅ Start refuză dacă resource e in_use
- ✅ Start refuză dacă ticket nu e linked la resource
- ✅ End calculează overtime_minutes corect (planned 60min, actual 90min → 30min overtime)
- ✅ Surcharge: 30min overtime, interval 30min, 5 RON → 5 RON
- ✅ Surcharge: 45min overtime, interval 30min, 5 RON → 10 RON (ceil)
- ✅ End setează resource.status = 'available'

`tests/Unit/Leisure/PhysicalResourceTest.php`:
- ✅ generateQrCode generează unique cu prefix corect
- ✅ Soft delete păstrează rental history

### E3 livrabile

| Fișier | Acțiune |
|---|---|
| `epas/database/migrations/2026_05_22_130000_create_physical_resources_table.php` | Create |
| `epas/database/migrations/2026_05_22_130100_create_resource_rentals_table.php` | Create |
| `epas/app/Models/Leisure/PhysicalResource.php` | Create |
| `epas/app/Models/Leisure/ResourceRental.php` | Create |
| `epas/app/Services/Leisure/RentalService.php` | Create |
| `epas/app/Filament/Resources/Leisure/PhysicalResourceResource.php` | Create |
| `epas/app/Filament/Resources/Leisure/ResourceRentalResource.php` | Create |
| `epas/resources/views/leisure/qr-print.blade.php` | Create |
| `epas/app/Http/Controllers/Api/Leisure/RentalController.php` | Create |
| `epas/tests/Feature/Leisure/RentalServiceTest.php` | Tests |
| Add `endroid/qr-code` to composer.json | Already present, verify |

---

## Strategie migrare & risk register

### Migrări DB

| # | Migrare | Tabele afectate | Reversibilă? |
|---|---|---|---|
| 1 | extend_ticket_types_for_leisure | `ticket_types` (add cols, all nullable) | Da (drop cols) |
| 2 | create_ticket_type_capacities | NEW table | Da (drop table) |
| 3 | create_physical_resources | NEW table | Da |
| 4 | create_resource_rentals | NEW table | Da |

Toate migrările sunt down-able fără pierdere de date pe tabele existente.

### Risk register

| Risk | Mitigare |
|---|---|
| Confuzie nume `leisure_*` vs `marketplace_*` în queries | Convenție: doar prefix `leisure_` pe câmpuri core, `Models\Leisure\` namespace pentru modele noi; ZERO referințe la `marketplace_*` din codul leisure |
| Performance pe ticket_type_capacities cu multe rânduri (1 an × 365 zile × N tickets × N slot-uri) | Index pe `(tenant_id, capacity_date)`, paginare în UI, cache lunar pe API |
| Operator panel (E7) depinde de tenant_team_members (E4) | E3 API folosește auth:sanctum + tenant_id scope temporar; refactor în E7 |
| Sf. Ana cere accidental să fie tenant | Nu acceptăm; documentăm clear în onboarding că marketplace_organizer != tenant; flag separate |
| Time zone bugs (rentals pe timezone tenant) | Tenant model are deja `timezone`; toate datetime cast cu `->setTimezone($tenant->timezone)` în service layer |
| QR code collision între tenants | qr_code unique GLOBAL (nu doar per tenant) + prefix tenant_id pentru disambiguation vizuală |
| Locking pe capacity reserve sub load mare | `lockForUpdate` cu retry; eventual extend cu Redis distributed lock în E2 v2 dacă apare problemă |

### Compatibilitate cu memory rules userului

✅ **Live site safety (non-breaking):** Ambilet rămâne netinger, toate adăugirile noi cu null default.
✅ **Always commit & push after task:** Voi commit + push după fiecare E0/E1/E2/E3 finalizat.
✅ **Submodul epas:** Toate schimbările sunt în submodul `epas/` → commit acolo prima, push, apoi main repo "Update epas - leisure tenant Ex".
✅ **Two copies marketplace JS:** NU se aplică — leisure nou e doar în core, NU atinge `epas/resources/marketplaces/`.

---

## Open questions pentru E4-E11 (vor fi rezolvate înainte de E4)

1. **Tenant team members** — cum se face login operator? PIN? Magic link? Username/parolă tradițională?
2. **Multi-society** — cine emite facturile când multiple societăți sunt pe același order? UI cere selecție per item sau automat?
3. **Channel pricing** — discount/markup pe canal, sau prețuri complet diferite stocate? (Recomand: pricing modifier pe top of base, ușor de citit/audit).
4. **Embed widget** — autentificare tenant API (token? domain whitelist? CORS only?). Cum prevenim abuz?
5. **Mobile operator app** (E12) — Expo monorepo cu tixello-customer sau repo separat?

---

## Următorul pas

**După aprobarea acestui design doc:**
1. Eu încep E0 — TenantType::Leisure case + LeisureFeatureDefaults observer + Filament tab "Leisure"
2. Commit + push în submodul `epas/`, apoi commit main repo
3. Notice la userul: "E0 done, test rapid în /admin/tenants, apoi pornesc E1"
4. Continuă cu E1 (TicketType extensions) după go-ul tău

**Time tracking previzionat E0-E3:** ~10-14 zile efective; livrate săptămânal cu testabilitate intermediară.
