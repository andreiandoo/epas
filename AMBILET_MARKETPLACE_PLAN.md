# Ambilet Marketplace Admin Dashboard - Implementation Plan

## Understanding the Architecture

Based on reviewing `core-main`, there are **two Filament panels**:

1. **Admin Panel** (`/admin`) - Core platform management
   - Path: `app/Filament/Resources/`
   - Provider: `AdminPanelProvider.php`

2. **Tenant Panel** (`/tenant`) - Tenant dashboard
   - Path: `app/Filament/Tenant/Resources/`
   - Provider: `TenantPanelProvider.php`

**What we need:**

3. **Marketplace Panel** (`/marketplace`) - For ambilet and similar marketplace clients
   - Path: `app/Filament/Marketplace/Resources/`
   - Provider: `MarketplacePanelProvider.php`
   - Same capabilities as Tenant + Organizer management

---

## Marketplace = Tenant + Organizers

A **Marketplace** is positioned between Tenant and Core:
- Has all Tenant capabilities (events, orders, tickets, customers, affiliates, coupons, etc.)
- Can accept **Organizer** users under its umbrella
- Uses its own authentication guard (`marketplace_admin`)

---

## What Exists vs What to Build

### Already Exists (from Tenant - to be copied/adapted):

| Resource | Tenant | Marketplace |
|----------|--------|-------------|
| EventResource | ✅ | 📋 Copy & adapt |
| OrderResource | ✅ | 📋 Copy & adapt |
| TicketResource | ✅ | 📋 Copy & adapt |
| CustomerResource | ✅ | 📋 Copy & adapt |
| VenueResource | ✅ | 📋 Copy & adapt |
| AffiliateResource | ✅ | 📋 Copy & adapt |
| CouponCodeResource | ✅ | 📋 Copy & adapt |
| TicketTemplateResource | ✅ | 📋 Copy & adapt |
| GamificationConfigResource | ✅ | 📋 Copy & adapt |
| ShopProductResource | ✅ | 📋 Copy & adapt |
| UserResource | ✅ | 📋 Copy & adapt |
| Dashboard | ✅ | 📋 Copy & adapt |
| Settings | ✅ | 📋 Copy & adapt |
| MicroserviceSettings | ✅ | 📋 Copy & adapt |
| PaymentConfig | ✅ | 📋 Copy & adapt |
| AnalyticsDashboard | ✅ | 📋 Copy & adapt |
| Invitations | ✅ | 📋 Copy & adapt |
| TrackingSettings | ✅ | 📋 Copy & adapt |

### New (Marketplace-specific):

| Resource | Description |
|----------|-------------|
| OrganizerResource | Manage organizers (approve, verify, suspend, commission) |
| OrganizerEventResource | View/manage organizer events |
| PayoutResource | Process organizer payout requests |
| MarketplaceEventResource | Marketplace-created events (by organizers) |

---

## Implementation Plan

### Phase 1: Marketplace Panel Setup

```
app/Providers/Filament/MarketplacePanelProvider.php

<?php
namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
// ... middlewares

class MarketplacePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('marketplace')
            ->path('marketplace')        // Login at /marketplace/login
            ->login()
            ->authGuard('marketplace_admin')  // Custom guard
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(
                in: app_path('Filament/Marketplace/Resources'),
                for: 'App\\Filament\\Marketplace\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Marketplace/Pages'),
                for: 'App\\Filament\\Marketplace\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Marketplace/Widgets'),
                for: 'App\\Filament\\Marketplace\\Widgets'
            )
            ->navigationGroups([
                'Sales',
                'Organizers',  // NEW - Marketplace specific
                'Services',
                'Content',
                'Settings',
            ])
            // ... middlewares
    }
}
```

### Phase 2: Auth Guard for MarketplaceAdmin

```php
// config/auth.php - add:

'guards' => [
    'marketplace_admin' => [
        'driver' => 'session',
        'provider' => 'marketplace_admins',
    ],
],

'providers' => [
    'marketplace_admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\MarketplaceAdmin::class,
    ],
],
```

### Phase 3: Copy Tenant Resources to Marketplace

```
app/Filament/Marketplace/
├── Pages/
│   ├── Dashboard.php           # Adapted from Tenant
│   ├── Settings.php            # Adapted from Tenant
│   ├── MicroserviceSettings.php
│   ├── PaymentConfig.php
│   ├── AnalyticsDashboard.php
│   ├── Invitations.php
│   ├── TrackingSettings.php
│   └── ...
├── Resources/
│   ├── EventResource.php       # Marketplace events
│   ├── OrderResource.php       # Orders with refund
│   ├── TicketResource.php      # Ticket management
│   ├── CustomerResource.php    # Customer management
│   ├── VenueResource.php       # Venue management
│   ├── AffiliateResource.php
│   ├── CouponCodeResource.php
│   ├── ShopProductResource.php
│   ├── UserResource.php        # Staff users
│   │
│   ├── OrganizerResource.php   # NEW - Organizer management
│   ├── OrganizerEventResource.php  # NEW - Organizer events
│   └── PayoutResource.php      # NEW - Payout processing
└── Widgets/
    └── ...
```

### Phase 4: Adapt Models for Marketplace Context

The MarketplaceAdmin model needs `canAccessPanel()`:

```php
// app/Models/MarketplaceAdmin.php

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class MarketplaceAdmin extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'marketplace'
            && $this->status === 'active';
    }
}
```

---

## Folder Structure

```
app/
├── Filament/
│   ├── Resources/          # Core Admin panel
│   ├── Pages/              # Core Admin pages
│   ├── Tenant/             # Tenant panel (existing)
│   │   ├── Resources/
│   │   └── Pages/
│   └── Marketplace/        # NEW - Marketplace panel
│       ├── Resources/
│       │   ├── EventResource.php
│       │   ├── OrderResource.php
│       │   ├── TicketResource.php
│       │   ├── CustomerResource.php
│       │   ├── VenueResource.php
│       │   ├── AffiliateResource.php
│       │   ├── CouponCodeResource.php
│       │   ├── CouponCampaignResource.php
│       │   ├── TicketTemplateResource.php
│       │   ├── GamificationConfigResource.php
│       │   ├── ShopProductResource.php
│       │   ├── ShopCategoryResource.php
│       │   ├── ShopOrderResource.php
│       │   ├── BlogArticleResource.php
│       │   ├── BlogCategoryResource.php
│       │   ├── UserResource.php
│       │   ├── GroupBookingResource.php
│       │   ├── CustomerPointsResource.php
│       │   │
│       │   ├── OrganizerResource.php       # Marketplace-specific
│       │   ├── OrganizerEventResource.php  # Marketplace-specific
│       │   └── PayoutResource.php          # Marketplace-specific
│       ├── Pages/
│       │   ├── Dashboard.php
│       │   ├── Settings.php
│       │   ├── MicroserviceSettings.php
│       │   ├── PaymentConfig.php
│       │   ├── AnalyticsDashboard.php
│       │   ├── Invitations.php
│       │   ├── TrackingSettings.php
│       │   ├── ThemeEditor.php
│       │   ├── PageBuilder.php
│       │   ├── VenueUsage.php
│       │   ├── TaxReports.php
│       │   └── Domains.php
│       └── Widgets/
│           └── ...
└── Providers/
    └── Filament/
        ├── AdminPanelProvider.php       # /admin
        ├── TenantPanelProvider.php      # /tenant
        └── MarketplacePanelProvider.php # /marketplace (NEW)
```

---

## Navigation Structure

```
MARKETPLACE PANEL (/marketplace)
│
├── 📊 Dashboard
│
├── 📅 SALES
│   ├── Events
│   ├── Orders
│   ├── Tickets
│   └── Customers
│
├── 👥 ORGANIZERS (Marketplace-specific)
│   ├── All Organizers
│   ├── Pending Approval
│   ├── Organizer Events
│   └── Payouts
│
├── 🔧 SERVICES
│   ├── Affiliates
│   ├── Coupons
│   ├── Ticket Customizer
│   ├── Gamification
│   ├── Group Bookings
│   ├── Invitations
│   └── Microservices
│
├── 🛍️ SHOP
│   ├── Products
│   ├── Categories
│   ├── Orders
│   └── Gift Cards
│
├── 📝 CONTENT
│   ├── Venues
│   ├── Blog
│   └── Pages
│
└── ⚙️ SETTINGS
    ├── General
    ├── Staff Users
    ├── Payment Config
    ├── Domains
    ├── Tracking
    ├── Taxes
    └── Theme
```

---

## Implementation Steps

### Step 1: Create MarketplacePanelProvider
- [ ] Create `app/Providers/Filament/MarketplacePanelProvider.php`
- [ ] Register in `config/app.php` providers
- [ ] Add marketplace_admin guard to `config/auth.php`
- [ ] Update `MarketplaceAdmin` model with `FilamentUser` interface

### Step 2: Create Marketplace Folder Structure
- [ ] Create `app/Filament/Marketplace/Resources/`
- [ ] Create `app/Filament/Marketplace/Pages/`
- [ ] Create `app/Filament/Marketplace/Widgets/`

### Step 3: Copy & Adapt Tenant Resources
- [ ] Copy all Tenant Resources to Marketplace
- [ ] Update namespaces
- [ ] Update model references (Tenant → Marketplace context)
- [ ] Add tenant_id filtering for shared data

### Step 4: Copy & Adapt Tenant Pages
- [ ] Copy Dashboard, Settings, etc.
- [ ] Update namespaces
- [ ] Adapt for marketplace context

### Step 5: Create Marketplace-Specific Resources
- [ ] OrganizerResource (approve, verify, suspend, commission)
- [ ] OrganizerEventResource (view organizer events)
- [ ] PayoutResource (process payouts)

### Step 6: Permissions & Access Control
- [ ] Define marketplace admin roles (super_admin, admin, editor, scanner)
- [ ] Implement permission checks in resources
- [ ] Add canAccessPanel() to MarketplaceAdmin

### Step 7: Testing
- [ ] Test login at `/marketplace`
- [ ] Test all resources
- [ ] Test organizer management

---

## Estimated Timeline

| Phase | Task | Time |
|-------|------|------|
| 1 | Panel Provider + Auth Guard | 0.5 day |
| 2 | Create folder structure | 0.5 day |
| 3 | Copy & adapt all Tenant Resources | 2-3 days |
| 4 | Copy & adapt all Tenant Pages | 1-2 days |
| 5 | Create OrganizerResource, PayoutResource | 1 day |
| 6 | Permissions & access control | 1 day |
| 7 | Testing & fixes | 1 day |

**Total: 7-9 days**

---

## Approval Required

1. **Is this the correct understanding?**
   - Marketplace = Tenant + Organizers management
   - Uses Filament panel at `/marketplace`
   - Copies Tenant structure and adapts it

2. **Should I proceed with Step 1?** (MarketplacePanelProvider + auth guard)

3. **Any additional features specific to marketplace?**
