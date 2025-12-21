# Marketplace Platform Registration Plan

## Executive Summary

This document outlines the implementation plan for adding a new tenant type called **Marketplace** - a ticketing platform that can host multiple **Organizers** (event creators). Unlike regular tenants, a Marketplace:

- Has a **completely different website** (generated, but radically different template)
- Can host multiple **Organizers** with their own dashboards
- Sets **custom commission structures** (percentage, fixed, or both)
- Has access to **purchased microservices**
- Supports **multiple payment processors**
- **All data flows through Tixello** (same as regular tenants)
- **Tixello always takes 1%** from all marketplace orders

---

## Architecture Overview

### Entity Hierarchy

```
Platform (Tixello)
├── Users (super-admin, admin, editor, tenant roles)
│
├── Tenants (type: 'standard') - Existing functionality
│   ├── Events, Venues, Orders, Tickets
│   ├── Customers
│   ├── Domains (generated website)
│   └── Microservices (purchased)
│
└── Tenants (type: 'marketplace') - NEW
    ├── Marketplace Website (generated, different template)
    ├── Domains
    ├── Microservices (purchased)
    ├── Payment Processors (multiple)
    ├── Commission Settings (%, fixed, or both)
    │
    ├── Organizers (NEW - sub-entities)
    │   ├── Organizer Dashboard
    │   ├── Events (owned by organizer)
    │   ├── Venues
    │   ├── Revenue & Payouts
    │   └── Organizer Users
    │
    ├── Customers (shared across organizers)
    ├── Orders (linked to organizers)
    └── Tickets
```

### Key Principle: Marketplace IS a Tenant

The Marketplace is implemented as a **specialized tenant type**, not a completely separate entity. This means:

1. ✅ Same data flow through Tixello
2. ✅ Same infrastructure (domains, microservices, payment processors)
3. ✅ Same billing relationship with Tixello
4. ✅ Tixello's 1% commission applies to all orders
5. ✅ Website is generated (but uses different template)
6. ⚡ Additional layer: Organizers within the marketplace

---

## Commission Flow

### Revenue Distribution

```
Customer pays €100 for ticket
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│                     ORDER TOTAL: €100                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Tixello Platform Fee (always 1%)          →    €1.00    │
│                                                              │
│  2. Marketplace Commission (configurable)                    │
│     Example: 5% + €0.50 fixed                                │
│     = (€99 × 5%) + €0.50                      →    €5.45    │
│                                                              │
│  3. Organizer Revenue (remainder)             →   €93.55    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Commission Configuration Options

| Type | Example | Calculation |
|------|---------|-------------|
| **Percentage only** | 5% | order_total × 0.05 |
| **Fixed only** | €2.00 | flat €2.00 per order |
| **Percentage + Fixed** | 3% + €1.00 | (order_total × 0.03) + €1.00 |

---

## Database Schema

### Phase 1: Extend Existing Tables

#### 1.1 Modify `tenants` Table

```php
// Add to existing tenants migration
Schema::table('tenants', function (Blueprint $table) {
    // Tenant type distinction
    $table->string('tenant_type')->default('standard');  // 'standard' or 'marketplace'

    // Marketplace-specific commission settings
    $table->decimal('marketplace_commission_percent', 5, 2)->nullable();  // e.g., 5.00 = 5%
    $table->decimal('marketplace_commission_fixed', 10, 2)->nullable();   // e.g., 1.50 = €1.50
    $table->string('marketplace_commission_type')->nullable();            // 'percent', 'fixed', 'both'

    // Index for efficient queries
    $table->index('tenant_type');
});
```

### Phase 2: New Tables for Organizers

#### 2.1 `marketplace_organizers` Table

```php
Schema::create('marketplace_organizers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();  // The marketplace tenant

    $table->string('name');
    $table->string('slug');
    $table->string('status')->default('active');  // active, suspended, pending_approval

    // Company Details
    $table->string('company_name')->nullable();
    $table->string('cui')->nullable();               // Tax ID
    $table->string('reg_com')->nullable();           // Trade register number
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('county')->nullable();
    $table->string('country')->default('RO');
    $table->string('postal_code')->nullable();

    // Contact
    $table->string('contact_name');
    $table->string('contact_email');
    $table->string('contact_phone')->nullable();
    $table->string('logo')->nullable();
    $table->string('cover_image')->nullable();
    $table->text('description')->nullable();

    // Commission Override (can override marketplace default)
    $table->decimal('commission_percent', 5, 2)->nullable();
    $table->decimal('commission_fixed', 10, 2)->nullable();
    $table->string('commission_type')->nullable();  // null = use marketplace default

    // Payout Settings
    $table->string('payout_method')->default('bank_transfer');  // bank_transfer, paypal, stripe_connect
    $table->json('payout_details')->nullable();      // Bank account, IBAN, etc.
    $table->string('payout_frequency')->default('monthly');  // weekly, biweekly, monthly
    $table->decimal('minimum_payout', 10, 2)->default(50.00);

    // Settings & Features
    $table->json('settings')->nullable();
    $table->json('allowed_features')->nullable();    // Features the organizer can use

    // Verification
    $table->boolean('is_verified')->default(false);
    $table->timestamp('verified_at')->nullable();
    $table->string('verified_by')->nullable();

    // Contract
    $table->string('contract_status')->default('pending');  // pending, signed, expired
    $table->timestamp('contract_signed_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['tenant_id', 'slug']);
    $table->index(['tenant_id', 'status']);
});
```

#### 2.2 `marketplace_organizer_users` Table

```php
Schema::create('marketplace_organizer_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organizer_id')->constrained('marketplace_organizers')->cascadeOnDelete();

    $table->string('name');
    $table->string('email');
    $table->string('password');
    $table->string('phone')->nullable();
    $table->string('role')->default('admin');  // admin, editor, viewer
    $table->string('avatar')->nullable();

    $table->boolean('is_active')->default(true);
    $table->timestamp('last_login_at')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['organizer_id', 'email']);
});
```

#### 2.3 Extend `events` Table

```php
// Add to existing events table
Schema::table('events', function (Blueprint $table) {
    // Link event to organizer (for marketplace tenants)
    $table->foreignId('organizer_id')->nullable()->constrained('marketplace_organizers')->nullOnDelete();

    // Index for organizer queries
    $table->index('organizer_id');
});
```

#### 2.4 Extend `orders` Table

```php
// Add to existing orders table
Schema::table('orders', function (Blueprint $table) {
    // Link order to organizer
    $table->foreignId('organizer_id')->nullable()->constrained('marketplace_organizers')->nullOnDelete();

    // Commission tracking for marketplace orders
    $table->decimal('tixello_commission', 10, 2)->nullable();      // Tixello's 1%
    $table->decimal('marketplace_commission', 10, 2)->nullable();  // Marketplace's cut
    $table->decimal('organizer_revenue', 10, 2)->nullable();       // Organizer's revenue

    $table->index('organizer_id');
});
```

#### 2.5 `marketplace_payouts` Table

```php
Schema::create('marketplace_payouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();  // Marketplace
    $table->foreignId('organizer_id')->constrained('marketplace_organizers')->cascadeOnDelete();

    $table->string('reference')->unique();           // Payout reference number
    $table->decimal('amount', 10, 2);                // Payout amount
    $table->string('currency')->default('RON');

    $table->string('status')->default('pending');    // pending, processing, completed, failed
    $table->string('method');                        // bank_transfer, paypal, etc.
    $table->json('method_details')->nullable();      // Bank account used, etc.

    $table->date('period_start');                    // Payout period
    $table->date('period_end');

    $table->integer('orders_count');                 // Number of orders in payout
    $table->decimal('gross_revenue', 10, 2);         // Total order value
    $table->decimal('tixello_fees', 10, 2);          // Tixello's 1%
    $table->decimal('marketplace_fees', 10, 2);      // Marketplace commission

    $table->text('notes')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamp('completed_at')->nullable();

    $table->timestamps();

    $table->index(['organizer_id', 'status']);
    $table->index(['tenant_id', 'created_at']);
});
```

#### 2.6 `marketplace_payout_items` Table

```php
Schema::create('marketplace_payout_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payout_id')->constrained('marketplace_payouts')->cascadeOnDelete();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();

    $table->decimal('order_total', 10, 2);
    $table->decimal('tixello_fee', 10, 2);
    $table->decimal('marketplace_fee', 10, 2);
    $table->decimal('organizer_amount', 10, 2);

    $table->timestamps();
});
```

---

## Models

### 3.1 Update `Tenant` Model

```php
// app/Models/Tenant.php

class Tenant extends Model
{
    // ... existing code ...

    // Add constants
    const TYPE_STANDARD = 'standard';
    const TYPE_MARKETPLACE = 'marketplace';

    // Add casts
    protected $casts = [
        // ... existing casts ...
        'marketplace_commission_percent' => 'decimal:2',
        'marketplace_commission_fixed' => 'decimal:2',
    ];

    // Add relationships
    public function organizers(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizer::class);
    }

    // Add scopes
    public function scopeMarketplaces($query)
    {
        return $query->where('tenant_type', self::TYPE_MARKETPLACE);
    }

    public function scopeStandard($query)
    {
        return $query->where('tenant_type', self::TYPE_STANDARD);
    }

    // Add helpers
    public function isMarketplace(): bool
    {
        return $this->tenant_type === self::TYPE_MARKETPLACE;
    }

    public function isStandardTenant(): bool
    {
        return $this->tenant_type === self::TYPE_STANDARD;
    }

    /**
     * Calculate marketplace commission for an order
     */
    public function calculateMarketplaceCommission(float $orderTotal, ?MarketplaceOrganizer $organizer = null): array
    {
        // Get commission settings (organizer override or marketplace default)
        $commissionType = $organizer?->commission_type ?? $this->marketplace_commission_type;
        $commissionPercent = $organizer?->commission_percent ?? $this->marketplace_commission_percent ?? 0;
        $commissionFixed = $organizer?->commission_fixed ?? $this->marketplace_commission_fixed ?? 0;

        // Tixello always takes 1%
        $tixelloCommission = round($orderTotal * 0.01, 2);
        $afterTixello = $orderTotal - $tixelloCommission;

        // Calculate marketplace commission
        $marketplaceCommission = 0;

        switch ($commissionType) {
            case 'percent':
                $marketplaceCommission = round($afterTixello * ($commissionPercent / 100), 2);
                break;
            case 'fixed':
                $marketplaceCommission = $commissionFixed;
                break;
            case 'both':
                $marketplaceCommission = round($afterTixello * ($commissionPercent / 100), 2) + $commissionFixed;
                break;
        }

        // Organizer gets the rest
        $organizerRevenue = $afterTixello - $marketplaceCommission;

        return [
            'order_total' => $orderTotal,
            'tixello_commission' => $tixelloCommission,
            'marketplace_commission' => $marketplaceCommission,
            'organizer_revenue' => max(0, $organizerRevenue),  // Never negative
        ];
    }
}
```

### 3.2 `MarketplaceOrganizer` Model

```php
// app/Models/Marketplace/MarketplaceOrganizer.php

namespace App\Models\Marketplace;

use App\Models\Tenant;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class MarketplaceOrganizer extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'company_name',
        'cui',
        'reg_com',
        'address',
        'city',
        'county',
        'country',
        'postal_code',
        'contact_name',
        'contact_email',
        'contact_phone',
        'logo',
        'cover_image',
        'description',
        'commission_percent',
        'commission_fixed',
        'commission_type',
        'payout_method',
        'payout_details',
        'payout_frequency',
        'minimum_payout',
        'settings',
        'allowed_features',
        'is_verified',
        'verified_at',
        'contract_status',
        'contract_signed_at',
    ];

    protected $casts = [
        'payout_details' => 'array',
        'settings' => 'array',
        'allowed_features' => 'array',
        'commission_percent' => 'decimal:2',
        'commission_fixed' => 'decimal:2',
        'minimum_payout' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'contract_signed_at' => 'datetime',
    ];

    // Relationships
    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizerUser::class, 'organizer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'organizer_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(MarketplacePayout::class, 'organizer_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Helpers
    public function getEffectiveCommissionType(): ?string
    {
        return $this->commission_type ?? $this->marketplace->marketplace_commission_type;
    }

    public function getEffectiveCommissionPercent(): float
    {
        return $this->commission_percent ?? $this->marketplace->marketplace_commission_percent ?? 0;
    }

    public function getEffectiveCommissionFixed(): float
    {
        return $this->commission_fixed ?? $this->marketplace->marketplace_commission_fixed ?? 0;
    }

    /**
     * Get pending revenue (orders not yet paid out)
     */
    public function getPendingRevenue(): float
    {
        return $this->orders()
            ->where('status', 'paid')
            ->whereNull('payout_id')
            ->sum('organizer_revenue');
    }

    /**
     * Get total revenue (all time)
     */
    public function getTotalRevenue(): float
    {
        return $this->orders()
            ->where('status', 'paid')
            ->sum('organizer_revenue');
    }
}
```

### 3.3 `MarketplaceOrganizerUser` Model

```php
// app/Models/Marketplace/MarketplaceOrganizerUser.php

namespace App\Models\Marketplace;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class MarketplaceOrganizerUser extends Authenticatable implements FilamentUser
{
    use SoftDeletes;

    protected $guard = 'organizer';

    protected $fillable = [
        'organizer_id',
        'name',
        'email',
        'password',
        'phone',
        'role',
        'avatar',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // Role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_EDITOR = 'editor';
    const ROLE_VIEWER = 'viewer';

    // Relationships
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'organizer_id');
    }

    // Get the marketplace (tenant) through organizer
    public function marketplace(): Tenant
    {
        return $this->organizer->marketplace;
    }

    // Authorization
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'organizer') {
            return $this->is_active && $this->organizer->status === 'active';
        }
        return false;
    }

    public function canManageEvents(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR]);
    }

    public function canViewReports(): bool
    {
        return true;  // All roles can view reports
    }

    public function canManageSettings(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
```

---

## Authentication Configuration

### 4.1 Add Guards (`config/auth.php`)

```php
'guards' => [
    // ... existing guards ...

    'organizer' => [
        'driver' => 'session',
        'provider' => 'organizer_users',
    ],
],

'providers' => [
    // ... existing providers ...

    'organizer_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Marketplace\MarketplaceOrganizerUser::class,
    ],
],

'passwords' => [
    // ... existing passwords ...

    'organizer_users' => [
        'provider' => 'organizer_users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

---

## Filament Panels

### 5.1 Update Admin Panel - Add Marketplace Management

**New Resources for Admin Panel:**

```php
// app/Filament/Resources/MarketplaceResource.php
// Extends TenantResource with marketplace-specific fields

class MarketplaceResource extends Resource
{
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationGroup = 'Marketplaces';
    protected static ?string $navigationLabel = 'Marketplace Platforms';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_type', 'marketplace');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // All standard tenant fields...

            Section::make('Commission Settings')
                ->schema([
                    Select::make('marketplace_commission_type')
                        ->label('Commission Type')
                        ->options([
                            'percent' => 'Percentage Only',
                            'fixed' => 'Fixed Amount Only',
                            'both' => 'Percentage + Fixed',
                        ])
                        ->required(),

                    TextInput::make('marketplace_commission_percent')
                        ->label('Commission Percentage (%)')
                        ->numeric()
                        ->suffix('%')
                        ->visible(fn (Get $get) => in_array($get('marketplace_commission_type'), ['percent', 'both'])),

                    TextInput::make('marketplace_commission_fixed')
                        ->label('Fixed Commission Amount')
                        ->numeric()
                        ->prefix('RON')
                        ->visible(fn (Get $get) => in_array($get('marketplace_commission_type'), ['fixed', 'both'])),
                ]),

            Section::make('Platform Info')
                ->schema([
                    Placeholder::make('tixello_commission')
                        ->label('Tixello Commission')
                        ->content('1% of all orders (automatic)'),
                ]),
        ]);
    }
}
```

### 5.2 Update Tenant Panel for Marketplace Type

When a marketplace owner logs in, they see additional navigation:

```php
// Extend existing TenantPanelProvider

public function panel(Panel $panel): Panel
{
    return $panel
        // ... existing config ...
        ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
            $tenant = auth()->user()->getTenant();

            $items = [
                // Standard tenant navigation...
            ];

            // Add marketplace-specific navigation
            if ($tenant->isMarketplace()) {
                $items[] = NavigationGroup::make('Organizers')
                    ->items([
                        NavigationItem::make('All Organizers')
                            ->url(OrganizerResource::getUrl('index')),
                        NavigationItem::make('Pending Approvals')
                            ->url(OrganizerResource::getUrl('index', ['tableFilters' => ['status' => 'pending_approval']])),
                        NavigationItem::make('Payouts')
                            ->url(PayoutResource::getUrl('index')),
                    ]);

                $items[] = NavigationGroup::make('Commission Settings')
                    ->items([
                        NavigationItem::make('Default Commission')
                            ->url(route('filament.tenant.pages.commission-settings')),
                    ]);
            }

            return $builder->groups($items);
        });
}
```

### 5.3 New Organizer Panel

```php
// app/Providers/Filament/OrganizerPanelProvider.php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use App\Models\Marketplace\MarketplaceOrganizer;

class OrganizerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('organizer')
            ->path('organizer')
            ->login()
            ->authGuard('organizer')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Organizer/Resources'), for: 'App\\Filament\\Organizer\\Resources')
            ->discoverPages(in: app_path('Filament/Organizer/Pages'), for: 'App\\Filament\\Organizer\\Pages')
            ->pages([
                \App\Filament\Organizer\Pages\Dashboard::class,
            ])
            ->middleware([
                // ... standard middleware
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

**Organizer Panel Resources:**

1. **Dashboard** - Key metrics, recent orders, upcoming events
2. **EventResource** - Create/manage events (assigned to their organizer)
3. **VenueResource** - Create/manage venues
4. **OrderResource** - View orders (read-only, filtered by organizer)
5. **TicketResource** - View/validate tickets
6. **RevenueReport** - Earnings breakdown, pending payouts
7. **Settings** - Company info, payout settings

---

## Website Generation

### 6.1 Marketplace Website Template

The marketplace website is **completely different** from standard tenant websites:

```
Standard Tenant Website:
- Single event organizer
- Their events only
- Their branding

Marketplace Website:
- Multiple organizers
- Events from all organizers
- Marketplace branding
- Organizer profiles/pages
- Category-based browsing
- Search across all events
- Featured organizers section
```

**Template Structure:**

```
resources/tenant-client/src/templates/
├── standard/              # Existing tenant template
│   ├── pages/
│   ├── components/
│   └── ...
│
└── marketplace/           # NEW marketplace template
    ├── pages/
    │   ├── Home.vue              # Featured events, categories, organizers
    │   ├── Events.vue            # All events with filters
    │   ├── EventDetail.vue       # Event page
    │   ├── Organizers.vue        # List of organizers
    │   ├── OrganizerProfile.vue  # Organizer's page with their events
    │   ├── Categories.vue        # Browse by category
    │   └── ...
    ├── components/
    │   ├── OrganizerCard.vue
    │   ├── FeaturedOrganizers.vue
    │   ├── CategoryNav.vue
    │   └── ...
    └── layouts/
        └── MarketplaceLayout.vue
```

### 6.2 API Endpoints for Marketplace

```php
// routes/api.php - Add marketplace-specific endpoints

Route::prefix('tenant-client')->middleware(['tenant.client.cors'])->group(function () {
    // Existing endpoints...

    // Marketplace-specific endpoints
    Route::prefix('marketplace')->group(function () {
        // Organizers
        Route::get('/organizers', [MarketplaceOrganizerController::class, 'index']);
        Route::get('/organizers/featured', [MarketplaceOrganizerController::class, 'featured']);
        Route::get('/organizers/{slug}', [MarketplaceOrganizerController::class, 'show']);
        Route::get('/organizers/{slug}/events', [MarketplaceOrganizerController::class, 'events']);

        // Events with organizer info
        Route::get('/events', [MarketplaceEventController::class, 'index']);  // All events
        Route::get('/events/featured', [MarketplaceEventController::class, 'featured']);
        Route::get('/events/by-category/{slug}', [MarketplaceEventController::class, 'byCategory']);
    });
});
```

---

## Services Layer

### 7.1 Commission Calculation Service

```php
// app/Services/Marketplace/CommissionService.php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\Marketplace\MarketplaceOrganizer;

class CommissionService
{
    /**
     * Calculate and store commission breakdown for an order
     */
    public function calculateForOrder(Order $order): array
    {
        $tenant = $order->tenant;
        $organizer = $order->organizer;

        if (!$tenant->isMarketplace() || !$organizer) {
            // Standard tenant - only Tixello commission
            return [
                'tixello_commission' => round($order->total * 0.01, 2),
                'marketplace_commission' => 0,
                'organizer_revenue' => 0,
            ];
        }

        $breakdown = $tenant->calculateMarketplaceCommission(
            $order->total,
            $organizer
        );

        // Update order with commission breakdown
        $order->update([
            'tixello_commission' => $breakdown['tixello_commission'],
            'marketplace_commission' => $breakdown['marketplace_commission'],
            'organizer_revenue' => $breakdown['organizer_revenue'],
        ]);

        return $breakdown;
    }

    /**
     * Preview commission for a given amount (for UI)
     */
    public function preview(Tenant $tenant, float $amount, ?MarketplaceOrganizer $organizer = null): array
    {
        return $tenant->calculateMarketplaceCommission($amount, $organizer);
    }
}
```

### 7.2 Payout Service

```php
// app/Services/Marketplace/PayoutService.php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplacePayout;

class PayoutService
{
    /**
     * Generate payout for an organizer
     */
    public function generatePayout(MarketplaceOrganizer $organizer, Carbon $periodStart, Carbon $periodEnd): ?MarketplacePayout
    {
        // Get all unpaid orders in period
        $orders = $organizer->orders()
            ->where('status', 'paid')
            ->whereNull('payout_id')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $totals = [
            'gross_revenue' => $orders->sum('total'),
            'tixello_fees' => $orders->sum('tixello_commission'),
            'marketplace_fees' => $orders->sum('marketplace_commission'),
            'organizer_amount' => $orders->sum('organizer_revenue'),
        ];

        // Check minimum payout threshold
        if ($totals['organizer_amount'] < $organizer->minimum_payout) {
            return null;
        }

        // Create payout
        $payout = MarketplacePayout::create([
            'tenant_id' => $organizer->tenant_id,
            'organizer_id' => $organizer->id,
            'reference' => 'PAY-' . strtoupper(Str::random(10)),
            'amount' => $totals['organizer_amount'],
            'currency' => $organizer->marketplace->currency,
            'status' => 'pending',
            'method' => $organizer->payout_method,
            'method_details' => $organizer->payout_details,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'orders_count' => $orders->count(),
            'gross_revenue' => $totals['gross_revenue'],
            'tixello_fees' => $totals['tixello_fees'],
            'marketplace_fees' => $totals['marketplace_fees'],
        ]);

        // Link orders to payout
        foreach ($orders as $order) {
            MarketplacePayoutItem::create([
                'payout_id' => $payout->id,
                'order_id' => $order->id,
                'order_total' => $order->total,
                'tixello_fee' => $order->tixello_commission,
                'marketplace_fee' => $order->marketplace_commission,
                'organizer_amount' => $order->organizer_revenue,
            ]);

            $order->update(['payout_id' => $payout->id]);
        }

        return $payout;
    }
}
```

---

## Microservices Integration

Marketplaces can purchase and use microservices just like standard tenants:

```php
// Existing microservice infrastructure applies
// No changes needed - marketplace IS a tenant

// Access check in tenant panel:
$tenant->microservices()->where('microservice_id', $microserviceId)->exists();
```

---

## Payment Processors

Marketplaces configure payment processors the same as tenants:

```php
// Existing TenantPaymentConfig model applies
// No changes needed

// In marketplace tenant panel, they can:
// 1. Configure Stripe/other processors
// 2. Set up multiple payment methods
// 3. Configure per-organizer (optional extension)
```

---

## File Structure

```
app/
├── Models/
│   ├── Tenant.php                           # Extended with marketplace fields
│   └── Marketplace/
│       ├── MarketplaceOrganizer.php
│       ├── MarketplaceOrganizerUser.php
│       ├── MarketplacePayout.php
│       └── MarketplacePayoutItem.php
│
├── Filament/
│   ├── Resources/
│   │   └── MarketplaceResource.php          # Admin management of marketplaces
│   │
│   ├── Tenant/
│   │   ├── Resources/
│   │   │   ├── OrganizerResource.php        # Marketplace manages organizers
│   │   │   └── PayoutResource.php           # Marketplace manages payouts
│   │   └── Pages/
│   │       └── CommissionSettings.php       # Configure default commissions
│   │
│   └── Organizer/                           # NEW panel
│       ├── Pages/
│       │   ├── Dashboard.php
│       │   └── Revenue.php
│       └── Resources/
│           ├── EventResource.php
│           ├── VenueResource.php
│           ├── OrderResource.php
│           └── TicketResource.php
│
├── Http/
│   └── Controllers/
│       └── Api/
│           └── TenantClient/
│               ├── MarketplaceOrganizerController.php
│               └── MarketplaceEventController.php
│
├── Services/
│   └── Marketplace/
│       ├── CommissionService.php
│       ├── PayoutService.php
│       └── OrganizerRegistrationService.php
│
├── Providers/
│   └── Filament/
│       └── OrganizerPanelProvider.php
│
└── Notifications/
    └── Marketplace/
        ├── OrganizerApproved.php
        ├── PayoutProcessed.php
        └── NewOrganizerRegistration.php

database/
└── migrations/
    ├── xxxx_add_marketplace_fields_to_tenants_table.php
    ├── xxxx_create_marketplace_organizers_table.php
    ├── xxxx_create_marketplace_organizer_users_table.php
    ├── xxxx_add_organizer_id_to_events_table.php
    ├── xxxx_add_commission_fields_to_orders_table.php
    ├── xxxx_create_marketplace_payouts_table.php
    └── xxxx_create_marketplace_payout_items_table.php

resources/
└── tenant-client/
    └── src/
        └── templates/
            └── marketplace/               # NEW template
                ├── pages/
                ├── components/
                └── layouts/

config/
├── auth.php                              # Add organizer guard
└── marketplace.php                       # Marketplace configuration
```

---

## Implementation Sprints

### Sprint 1: Database & Models (3-4 days)
1. Create migrations for all tables
2. Extend Tenant model with marketplace fields
3. Create MarketplaceOrganizer model
4. Create MarketplaceOrganizerUser model
5. Create payout models
6. Configure authentication guards
7. Create seeders for testing

### Sprint 2: Admin Panel (2-3 days)
1. Create MarketplaceResource (extending TenantResource)
2. Add commission configuration UI
3. Add marketplace dashboard widgets
4. Add organizer approval workflow

### Sprint 3: Marketplace Tenant Panel (3-4 days)
1. Add OrganizerResource to tenant panel
2. Add PayoutResource
3. Create CommissionSettings page
4. Add marketplace-specific dashboard widgets
5. Integrate with existing microservices/payment config

### Sprint 4: Organizer Panel (4-5 days)
1. Create OrganizerPanelProvider
2. Build Dashboard
3. Create EventResource (scoped to organizer)
4. Create VenueResource
5. Create OrderResource (read-only)
6. Build Revenue/Payout page
7. Build Settings page

### Sprint 5: Commission & Payout System (3-4 days)
1. Implement CommissionService
2. Hook into order creation
3. Implement PayoutService
4. Create payout generation command
5. Build payout processing workflow
6. Create payout notifications

### Sprint 6: Website Template (4-5 days)
1. Create marketplace template structure
2. Build Home page with featured events/organizers
3. Build Events listing with filters
4. Build Organizer profiles
5. Build OrganizerProfile page
6. Update API endpoints
7. Test template switching

### Sprint 7: Testing & Polish (3-4 days)
1. Unit tests for commission calculations
2. Feature tests for organizer panel
3. Integration tests for payout flow
4. E2E tests for marketplace website
5. Documentation

**Total: ~23-29 development days**

---

## Key Differences Summary

| Aspect | Standard Tenant | Marketplace |
|--------|-----------------|-------------|
| **tenant_type** | 'standard' | 'marketplace' |
| **Website** | Standard template | Marketplace template |
| **Sub-users** | Editors only | Organizers + their users |
| **Events ownership** | Direct | Via organizers |
| **Commission** | Tixello 1% | Tixello 1% + Marketplace % + fixed |
| **Payouts** | N/A | To organizers |
| **Microservices** | ✅ Same | ✅ Same |
| **Payment processors** | ✅ Same | ✅ Same |
| **Data flow** | Through Tixello | Through Tixello |

---

## Important: Tixello Revenue

**Tixello always receives 1% from ALL orders**, regardless of:
- Tenant type (standard or marketplace)
- Organizer commission overrides
- Any other configuration

This is hardcoded in the commission calculation and cannot be changed or bypassed.
