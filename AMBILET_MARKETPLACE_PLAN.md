# Ambilet Marketplace Client Admin Dashboard - Implementation Plan

## Overview

Ambilet is a marketplace client type that operates its own ticketing platform (ambilet.ro). This document outlines the complete implementation plan for the Ambilet admin dashboard, which provides comprehensive management capabilities similar to the core platform.

---

## 1. Authentication & Authorization

### 1.1 Login System

```
Database Tables:
├── marketplace_clients (ambilet is one instance)
│   ├── id
│   ├── name (e.g., "Ambilet")
│   ├── slug (e.g., "ambilet")
│   ├── domain (e.g., "ambilet.ro")
│   ├── logo_url
│   ├── primary_color
│   ├── secondary_color
│   ├── settings (JSON - feature flags, limits)
│   ├── status (active, suspended, trial)
│   ├── created_at
│   └── updated_at
│
├── marketplace_users
│   ├── id
│   ├── marketplace_client_id (FK → marketplace_clients)
│   ├── email
│   ├── password_hash
│   ├── name
│   ├── phone
│   ├── role (super_admin, admin, editor, scanner, support)
│   ├── permissions (JSON - granular permissions)
│   ├── status (active, inactive, suspended)
│   ├── last_login_at
│   ├── created_at
│   └── updated_at
```

### 1.2 User Roles & Permissions

| Role | Description | Permissions |
|------|-------------|-------------|
| **super_admin** | Full access to everything | All permissions |
| **admin** | Manages most features | All except billing, user management |
| **editor** | Content management | Events, artists, venues, categories |
| **scanner** | Event check-in only | View events, scan tickets |
| **support** | Customer support | View orders, tickets, customers, refunds |

### 1.3 Authentication Flow

```
POST /api/marketplace/{slug}/auth/login
POST /api/marketplace/{slug}/auth/logout
POST /api/marketplace/{slug}/auth/refresh
POST /api/marketplace/{slug}/auth/forgot-password
POST /api/marketplace/{slug}/auth/reset-password
GET  /api/marketplace/{slug}/auth/me
```

---

## 2. Dashboard Overview

### 2.1 Main Dashboard Widgets

```
┌─────────────────────────────────────────────────────────────────┐
│  AMBILET ADMIN DASHBOARD                                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌─────────┐│
│  │ Total Revenue│ │ Tickets Sold │ │ Active Events│ │ Orders  ││
│  │   €125,430   │ │    8,234     │ │     47       │ │  1,892  ││
│  └──────────────┘ └──────────────┘ └──────────────┘ └─────────┘│
│                                                                 │
│  ┌────────────────────────────────┐ ┌─────────────────────────┐│
│  │     Revenue Chart (30 days)    │ │   Top Selling Events    ││
│  │         [LINE GRAPH]           │ │   1. Concert X - 1,200  ││
│  │                                │ │   2. Festival Y - 890   ││
│  │                                │ │   3. Show Z - 654       ││
│  └────────────────────────────────┘ └─────────────────────────┘│
│                                                                 │
│  ┌────────────────────────────────┐ ┌─────────────────────────┐│
│  │     Recent Orders              │ │   Check-in Stats        ││
│  │   [ORDER LIST]                 │ │   Today: 234/500 (47%)  ││
│  └────────────────────────────────┘ └─────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Core Modules

### 3.1 Events Management

```
Database Tables:
├── events
│   ├── id
│   ├── marketplace_client_id
│   ├── organizer_id (FK → organizers)
│   ├── category_id (FK → event_categories)
│   ├── venue_id (FK → venues)
│   ├── title
│   ├── slug
│   ├── description
│   ├── short_description
│   ├── start_date
│   ├── end_date
│   ├── doors_open_at
│   ├── status (draft, published, cancelled, completed)
│   ├── visibility (public, private, unlisted)
│   ├── featured
│   ├── cover_image
│   ├── gallery (JSON array)
│   ├── seo_title
│   ├── seo_description
│   ├── settings (JSON)
│   ├── created_at
│   └── updated_at
│
├── event_artists (pivot table)
│   ├── event_id
│   ├── artist_id
│   ├── performance_order
│   ├── set_time
│   └── headline (boolean)
│
├── ticket_types
│   ├── id
│   ├── event_id
│   ├── name
│   ├── description
│   ├── price
│   ├── quantity_total
│   ├── quantity_sold
│   ├── quantity_reserved
│   ├── min_per_order
│   ├── max_per_order
│   ├── sale_start_date
│   ├── sale_end_date
│   ├── visibility
│   ├── sort_order
│   └── settings (JSON - seat selection, etc.)
```

**API Endpoints:**
```
GET    /api/marketplace/{slug}/events
POST   /api/marketplace/{slug}/events
GET    /api/marketplace/{slug}/events/{id}
PUT    /api/marketplace/{slug}/events/{id}
DELETE /api/marketplace/{slug}/events/{id}
POST   /api/marketplace/{slug}/events/{id}/duplicate
PUT    /api/marketplace/{slug}/events/{id}/publish
PUT    /api/marketplace/{slug}/events/{id}/cancel
GET    /api/marketplace/{slug}/events/{id}/statistics
```

### 3.2 Orders Management

```
Database Tables:
├── orders
│   ├── id
│   ├── marketplace_client_id
│   ├── event_id
│   ├── customer_id (FK → customers)
│   ├── organizer_id
│   ├── order_number (unique, human-readable)
│   ├── status (pending, paid, refunded, cancelled, failed)
│   ├── subtotal
│   ├── tax_amount
│   ├── discount_amount
│   ├── total_amount
│   ├── currency
│   ├── payment_method
│   ├── payment_gateway (netopia, stripe, etc.)
│   ├── payment_id (external reference)
│   ├── coupon_id
│   ├── affiliate_id
│   ├── ip_address
│   ├── user_agent
│   ├── notes
│   ├── metadata (JSON)
│   ├── paid_at
│   ├── created_at
│   └── updated_at
│
├── order_items
│   ├── id
│   ├── order_id
│   ├── ticket_type_id
│   ├── quantity
│   ├── unit_price
│   ├── total_price
│   └── metadata (JSON)
```

**API Endpoints:**
```
GET    /api/marketplace/{slug}/orders
GET    /api/marketplace/{slug}/orders/{id}
POST   /api/marketplace/{slug}/orders/{id}/refund
POST   /api/marketplace/{slug}/orders/{id}/resend-tickets
GET    /api/marketplace/{slug}/orders/{id}/tickets
PUT    /api/marketplace/{slug}/orders/{id}/notes
GET    /api/marketplace/{slug}/orders/export
```

### 3.3 Tickets Management

```
Database Tables:
├── tickets
│   ├── id
│   ├── order_id
│   ├── order_item_id
│   ├── ticket_type_id
│   ├── event_id
│   ├── barcode (unique)
│   ├── qr_code_data
│   ├── holder_name
│   ├── holder_email
│   ├── seat_id (nullable, for seated events)
│   ├── status (valid, used, cancelled, transferred)
│   ├── checked_in_at
│   ├── checked_in_by (user_id)
│   ├── check_in_location
│   ├── created_at
│   └── updated_at
```

**API Endpoints:**
```
GET    /api/marketplace/{slug}/tickets
GET    /api/marketplace/{slug}/tickets/{barcode}
POST   /api/marketplace/{slug}/tickets/{barcode}/check-in
DELETE /api/marketplace/{slug}/tickets/{barcode}/check-in (undo)
PUT    /api/marketplace/{slug}/tickets/{barcode}/transfer
POST   /api/marketplace/{slug}/tickets/{barcode}/resend
GET    /api/marketplace/{slug}/tickets/export
```

### 3.4 Organizer Accounts

```
Database Tables:
├── organizers
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── slug
│   ├── email
│   ├── phone
│   ├── description
│   ├── logo_url
│   ├── cover_image
│   ├── website
│   ├── social_links (JSON)
│   ├── address
│   ├── city
│   ├── country
│   ├── tax_id
│   ├── bank_account
│   ├── commission_rate (percentage ambilet takes)
│   ├── payout_schedule (weekly, biweekly, monthly)
│   ├── status (pending, approved, suspended)
│   ├── verified
│   ├── settings (JSON)
│   ├── created_at
│   └── updated_at
│
├── organizer_users
│   ├── id
│   ├── organizer_id
│   ├── email
│   ├── password_hash
│   ├── name
│   ├── role (owner, admin, editor, scanner)
│   ├── permissions (JSON)
│   ├── status
│   ├── created_at
│   └── updated_at
```

**API Endpoints:**
```
GET    /api/marketplace/{slug}/organizers
POST   /api/marketplace/{slug}/organizers
GET    /api/marketplace/{slug}/organizers/{id}
PUT    /api/marketplace/{slug}/organizers/{id}
DELETE /api/marketplace/{slug}/organizers/{id}
PUT    /api/marketplace/{slug}/organizers/{id}/approve
PUT    /api/marketplace/{slug}/organizers/{id}/suspend
GET    /api/marketplace/{slug}/organizers/{id}/events
GET    /api/marketplace/{slug}/organizers/{id}/payouts
```

### 3.5 Customer Accounts

```
Database Tables:
├── customers
│   ├── id
│   ├── marketplace_client_id
│   ├── email
│   ├── password_hash (nullable - guest checkout)
│   ├── name
│   ├── phone
│   ├── date_of_birth
│   ├── gender
│   ├── address
│   ├── city
│   ├── country
│   ├── marketing_consent
│   ├── total_orders
│   ├── total_spent
│   ├── last_order_at
│   ├── tags (JSON array)
│   ├── notes
│   ├── status (active, blocked)
│   ├── created_at
│   └── updated_at
```

**API Endpoints:**
```
GET    /api/marketplace/{slug}/customers
GET    /api/marketplace/{slug}/customers/{id}
PUT    /api/marketplace/{slug}/customers/{id}
DELETE /api/marketplace/{slug}/customers/{id}
GET    /api/marketplace/{slug}/customers/{id}/orders
GET    /api/marketplace/{slug}/customers/{id}/tickets
PUT    /api/marketplace/{slug}/customers/{id}/block
GET    /api/marketplace/{slug}/customers/export
```

---

## 4. Reports & Analytics

### 4.1 Available Reports

```
├── Sales Reports
│   ├── Revenue by period (daily, weekly, monthly, yearly)
│   ├── Revenue by event
│   ├── Revenue by organizer
│   ├── Revenue by ticket type
│   ├── Revenue by payment method
│   └── Revenue by category
│
├── Ticket Reports
│   ├── Tickets sold by period
│   ├── Tickets by event
│   ├── Check-in rates
│   ├── No-show analysis
│   └── Ticket type popularity
│
├── Customer Reports
│   ├── New vs returning customers
│   ├── Customer lifetime value
│   ├── Geographic distribution
│   ├── Purchase frequency
│   └── Customer segments
│
├── Marketing Reports
│   ├── Affiliate performance
│   ├── Coupon usage
│   ├── Conversion funnels
│   ├── Traffic sources
│   └── Campaign ROI
│
├── Financial Reports
│   ├── Gross vs net revenue
│   ├── Tax collected
│   ├── Refunds issued
│   ├── Organizer payouts
│   └── Platform commission
```

**API Endpoints:**
```
GET /api/marketplace/{slug}/reports/dashboard
GET /api/marketplace/{slug}/reports/sales
GET /api/marketplace/{slug}/reports/tickets
GET /api/marketplace/{slug}/reports/customers
GET /api/marketplace/{slug}/reports/marketing
GET /api/marketplace/{slug}/reports/financial
GET /api/marketplace/{slug}/reports/export
```

---

## 5. Microservices Integration

### 5.1 Enabled Microservices Table

```
Database Tables:
├── marketplace_microservices
│   ├── id
│   ├── marketplace_client_id
│   ├── microservice_code
│   ├── enabled
│   ├── settings (JSON - service-specific config)
│   ├── created_at
│   └── updated_at
```

### 5.2 Available Microservices

#### 5.2.1 Venue Seating Maps
```
Tables:
├── venues
│   ├── id
│   ├── marketplace_client_id (nullable - shared venues)
│   ├── owner_type (core, marketplace, organizer)
│   ├── owner_id
│   ├── name
│   ├── slug
│   ├── description
│   ├── address
│   ├── city
│   ├── country
│   ├── latitude
│   ├── longitude
│   ├── capacity
│   ├── images (JSON)
│   ├── amenities (JSON)
│   ├── contact_info (JSON)
│   ├── status
│   └── created_at
│
├── seating_maps
│   ├── id
│   ├── venue_id
│   ├── name
│   ├── svg_data (the map SVG)
│   ├── configuration (JSON - zones, sections)
│   ├── status
│   └── created_at
│
├── seating_sections
│   ├── id
│   ├── seating_map_id
│   ├── name
│   ├── code
│   ├── capacity
│   ├── row_configuration (JSON)
│   └── sort_order
│
├── seats
│   ├── id
│   ├── seating_section_id
│   ├── row_label
│   ├── seat_number
│   ├── x_position
│   ├── y_position
│   ├── status (available, blocked, accessible)
│   └── metadata (JSON)
│
├── event_seating_prices
│   ├── id
│   ├── event_id
│   ├── seating_section_id
│   ├── ticket_type_id
│   ├── price
│   └── availability
```

#### 5.2.2 Affiliates System
```
Tables:
├── affiliates
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── email
│   ├── code (unique tracking code)
│   ├── commission_type (percentage, fixed)
│   ├── commission_value
│   ├── status
│   ├── total_sales
│   ├── total_commission
│   ├── created_at
│   └── updated_at
│
├── affiliate_links
│   ├── id
│   ├── affiliate_id
│   ├── event_id (nullable - all events if null)
│   ├── url
│   ├── clicks
│   ├── conversions
│   └── created_at
│
├── affiliate_commissions
│   ├── id
│   ├── affiliate_id
│   ├── order_id
│   ├── amount
│   ├── status (pending, approved, paid)
│   ├── paid_at
│   └── created_at
```

#### 5.2.3 Ticket Customizer
```
Tables:
├── ticket_templates
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── type (pdf, apple_wallet, google_wallet)
│   ├── design_data (JSON - layout, fonts, colors)
│   ├── background_image
│   ├── logo_position
│   ├── qr_position
│   ├── fields (JSON - which fields to show)
│   ├── is_default
│   └── created_at
│
├── event_ticket_templates
│   ├── event_id
│   ├── ticket_template_id
│   └── ticket_type_id (nullable - all types if null)
```

#### 5.2.4 Coupon Codes
```
Tables:
├── coupons
│   ├── id
│   ├── marketplace_client_id
│   ├── code
│   ├── name
│   ├── description
│   ├── discount_type (percentage, fixed, bogo)
│   ├── discount_value
│   ├── min_order_amount
│   ├── max_discount_amount
│   ├── usage_limit_total
│   ├── usage_limit_per_customer
│   ├── usage_count
│   ├── valid_from
│   ├── valid_until
│   ├── applicable_events (JSON - event IDs, null = all)
│   ├── applicable_ticket_types (JSON)
│   ├── status
│   └── created_at
│
├── coupon_usages
│   ├── id
│   ├── coupon_id
│   ├── order_id
│   ├── customer_id
│   ├── discount_amount
│   └── created_at
```

#### 5.2.5 Gamification
```
Tables:
├── gamification_campaigns
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── type (spin_wheel, scratch_card, quiz, treasure_hunt)
│   ├── event_id (nullable)
│   ├── configuration (JSON - prizes, rules)
│   ├── start_date
│   ├── end_date
│   ├── status
│   └── created_at
│
├── gamification_prizes
│   ├── id
│   ├── campaign_id
│   ├── name
│   ├── type (discount, free_ticket, merchandise, points)
│   ├── value
│   ├── quantity_total
│   ├── quantity_claimed
│   ├── probability (for random draws)
│   └── created_at
│
├── gamification_entries
│   ├── id
│   ├── campaign_id
│   ├── customer_id
│   ├── prize_id (nullable - if won)
│   ├── entry_data (JSON)
│   ├── won
│   ├── claimed
│   └── created_at
```

#### 5.2.6 Pixel Tracking
```
Tables:
├── tracking_pixels
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── type (facebook, google_analytics, google_ads, tiktok, custom)
│   ├── pixel_id
│   ├── configuration (JSON - events to track)
│   ├── status
│   └── created_at
│
├── tracking_events
│   ├── id
│   ├── pixel_id
│   ├── event_type (page_view, add_to_cart, purchase, etc.)
│   ├── event_data (JSON)
│   ├── customer_id (nullable)
│   ├── session_id
│   └── created_at
```

#### 5.2.7 Conversion Tracking
```
Tables:
├── conversion_funnels
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── steps (JSON - funnel step definitions)
│   └── created_at
│
├── conversion_events
│   ├── id
│   ├── funnel_id
│   ├── step_name
│   ├── session_id
│   ├── customer_id (nullable)
│   ├── event_id (nullable)
│   ├── metadata (JSON)
│   └── created_at
```

#### 5.2.8 Taxes
```
Tables:
├── tax_rates
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── rate (percentage)
│   ├── country
│   ├── region (nullable)
│   ├── tax_type (vat, sales_tax, gst)
│   ├── applies_to (tickets, fees, all)
│   ├── is_inclusive (price includes tax or added on top)
│   ├── is_default
│   └── created_at
│
├── tax_exemptions
│   ├── id
│   ├── tax_rate_id
│   ├── exemption_type (event_category, ticket_type, customer_type)
│   ├── exemption_id
│   └── created_at
```

#### 5.2.9 Invitations
```
Tables:
├── invitation_campaigns
│   ├── id
│   ├── marketplace_client_id
│   ├── event_id
│   ├── name
│   ├── template_id
│   ├── status
│   └── created_at
│
├── invitations
│   ├── id
│   ├── campaign_id
│   ├── email
│   ├── name
│   ├── ticket_type_id
│   ├── quantity
│   ├── code (unique)
│   ├── status (pending, sent, opened, claimed, expired)
│   ├── sent_at
│   ├── opened_at
│   ├── claimed_at
│   ├── order_id (if claimed)
│   └── created_at
│
├── invitation_templates
│   ├── id
│   ├── marketplace_client_id
│   ├── name
│   ├── subject
│   ├── body_html
│   ├── design_data (JSON)
│   └── created_at
```

#### 5.2.10 Online Shop (Merchandise)
```
Tables:
├── products
│   ├── id
│   ├── marketplace_client_id
│   ├── event_id (nullable - general merch if null)
│   ├── name
│   ├── slug
│   ├── description
│   ├── price
│   ├── compare_at_price
│   ├── cost_price
│   ├── sku
│   ├── barcode
│   ├── track_inventory
│   ├── quantity_in_stock
│   ├── images (JSON)
│   ├── category
│   ├── tags (JSON)
│   ├── status
│   └── created_at
│
├── product_variants
│   ├── id
│   ├── product_id
│   ├── name (e.g., "Large / Black")
│   ├── sku
│   ├── price
│   ├── quantity_in_stock
│   ├── options (JSON - size, color, etc.)
│   └── created_at
│
├── shop_orders
│   ├── id
│   ├── marketplace_client_id
│   ├── customer_id
│   ├── order_id (linked ticket order, nullable)
│   ├── order_number
│   ├── status (pending, processing, shipped, delivered, cancelled)
│   ├── subtotal
│   ├── shipping_amount
│   ├── tax_amount
│   ├── total_amount
│   ├── shipping_address (JSON)
│   ├── tracking_number
│   ├── notes
│   └── created_at
│
├── shop_order_items
│   ├── id
│   ├── shop_order_id
│   ├── product_id
│   ├── variant_id
│   ├── quantity
│   ├── unit_price
│   ├── total_price
│   └── created_at
```

---

## 6. Payment Integrations

### 6.1 Multiple Payment Gateways

```
Database Tables:
├── payment_gateways
│   ├── id
│   ├── marketplace_client_id
│   ├── gateway_code (netopia, stripe, paypal, etc.)
│   ├── name
│   ├── credentials (encrypted JSON)
│   ├── mode (sandbox, live)
│   ├── is_default
│   ├── supported_methods (JSON - card, bank_transfer, etc.)
│   ├── supported_currencies (JSON)
│   ├── platform (web, app, pos, all)
│   ├── fee_percentage
│   ├── fee_fixed
│   ├── status
│   └── created_at
│
├── payment_transactions
│   ├── id
│   ├── gateway_id
│   ├── order_id
│   ├── external_id (gateway transaction ID)
│   ├── amount
│   ├── currency
│   ├── status (pending, processing, completed, failed, refunded)
│   ├── payment_method
│   ├── card_last_four
│   ├── card_brand
│   ├── error_message
│   ├── metadata (JSON)
│   ├── created_at
│   └── updated_at
```

### 6.2 Supported Gateways

| Gateway | Platforms | Methods |
|---------|-----------|---------|
| **Netopia** | Web | Card, Bank Transfer |
| **Stripe** | Web, App, POS | Card, Apple Pay, Google Pay |
| **PayPal** | Web, App | PayPal Balance, Card |
| **Stripe Terminal** | POS | Card (Tap/Insert/Swipe) |
| **Cash** | POS | Manual Entry |
| **Bank Transfer** | Web | Wire Transfer |

---

## 7. User Management

### 7.1 Marketplace Staff Users

```
Roles:
├── super_admin
│   ├── Full platform access
│   ├── Manage other admins
│   ├── Billing & subscriptions
│   ├── All microservice settings
│   └── API access management
│
├── admin
│   ├── Events management
│   ├── Orders & tickets
│   ├── Organizers & customers
│   ├── Reports access
│   └── Most microservices
│
├── editor
│   ├── Create/edit events
│   ├── Manage artists & venues
│   ├── Content management
│   └── View-only reports
│
├── scanner
│   ├── Event check-in only
│   ├── View event details
│   └── Scan history
│
├── support
│   ├── View orders & tickets
│   ├── Process refunds
│   ├── Customer management
│   └── View-only access
```

### 7.2 Permission System

```
Permissions Structure:
├── events
│   ├── events.view
│   ├── events.create
│   ├── events.edit
│   ├── events.delete
│   ├── events.publish
│   └── events.cancel
│
├── orders
│   ├── orders.view
│   ├── orders.refund
│   ├── orders.resend
│   └── orders.export
│
├── tickets
│   ├── tickets.view
│   ├── tickets.checkin
│   ├── tickets.transfer
│   └── tickets.cancel
│
├── organizers
│   ├── organizers.view
│   ├── organizers.create
│   ├── organizers.edit
│   ├── organizers.delete
│   └── organizers.approve
│
├── customers
│   ├── customers.view
│   ├── customers.edit
│   ├── customers.delete
│   └── customers.block
│
├── reports
│   ├── reports.view
│   ├── reports.export
│   └── reports.financial
│
├── settings
│   ├── settings.view
│   ├── settings.edit
│   ├── settings.users
│   ├── settings.payments
│   └── settings.microservices
│
├── artists
│   ├── artists.view
│   ├── artists.create
│   ├── artists.edit_own
│   └── artists.delete_own
│
├── venues
│   ├── venues.view
│   ├── venues.create
│   ├── venues.edit_own
│   └── venues.delete_own
│
├── categories
│   ├── categories.view
│   ├── categories.create
│   ├── categories.edit
│   └── categories.delete
```

---

## 8. Event Categories

### 8.1 Custom Categories

```
Database Tables:
├── event_categories
│   ├── id
│   ├── marketplace_client_id
│   ├── parent_id (nullable - for subcategories)
│   ├── name
│   ├── slug
│   ├── description
│   ├── icon (icon name or custom SVG)
│   ├── image_url
│   ├── color
│   ├── sort_order
│   ├── is_featured
│   ├── seo_title
│   ├── seo_description
│   ├── status (active, inactive)
│   └── created_at
```

### 8.2 Category Features

- Hierarchical categories (parent/child)
- Custom icons (from icon library or upload)
- Custom images (for category pages)
- Custom colors (for UI theming)
- SEO optimization per category
- Featured categories for homepage

---

## 9. Artists Database

### 9.1 Shared Artists with Ownership

```
Database Tables:
├── artists
│   ├── id
│   ├── owner_type (core, marketplace, organizer)
│   ├── owner_id
│   ├── name
│   ├── slug
│   ├── bio
│   ├── short_bio
│   ├── profile_image
│   ├── cover_image
│   ├── gallery (JSON)
│   ├── genres (JSON)
│   ├── country
│   ├── website
│   ├── social_links (JSON - spotify, instagram, etc.)
│   ├── spotify_id
│   ├── youtube_channel
│   ├── monthly_listeners
│   ├── verified
│   ├── status (active, inactive)
│   └── created_at
│
├── artist_marketplace_access
│   ├── artist_id
│   ├── marketplace_client_id
│   ├── can_view (always true if exists)
│   ├── can_edit (only if owner)
│   └── created_at
```

### 9.2 Artist Access Rules

| Owner Type | Ambilet Can View | Ambilet Can Edit |
|------------|------------------|------------------|
| **core** | ✅ Yes | ❌ No |
| **ambilet** (self) | ✅ Yes | ✅ Yes |
| **other marketplace** | ❌ No (unless shared) | ❌ No |
| **organizer** (ambilet's) | ✅ Yes | ❌ No (organizer edits) |

---

## 10. Venues Database

### 10.1 Shared Venues with Ownership

```
Database Tables:
├── venues
│   ├── id
│   ├── owner_type (core, marketplace, organizer)
│   ├── owner_id
│   ├── name
│   ├── slug
│   ├── description
│   ├── short_description
│   ├── address
│   ├── city
│   ├── region
│   ├── country
│   ├── postal_code
│   ├── latitude
│   ├── longitude
│   ├── capacity
│   ├── venue_type (arena, theater, club, outdoor, etc.)
│   ├── images (JSON)
│   ├── amenities (JSON - parking, wheelchair, etc.)
│   ├── contact_email
│   ├── contact_phone
│   ├── website
│   ├── social_links (JSON)
│   ├── status
│   └── created_at
│
├── venue_marketplace_access
│   ├── venue_id
│   ├── marketplace_client_id
│   ├── can_view
│   ├── can_edit
│   └── created_at
```

### 10.2 Venue Access Rules

Same as Artists:
- Ambilet can add new venues (owner_type = 'marketplace', owner_id = ambilet_id)
- Ambilet can edit only venues they own
- Ambilet can view core venues and shared venues
- Cannot modify venues owned by core or other marketplaces

---

## 11. Frontend Architecture

### 11.1 Technology Stack

```
Frontend:
├── Framework: Next.js 14+ (App Router)
├── Language: TypeScript
├── UI Library: Tailwind CSS + shadcn/ui
├── State Management: Zustand
├── Forms: React Hook Form + Zod
├── Data Fetching: TanStack Query (React Query)
├── Charts: Recharts
├── Tables: TanStack Table
├── Date Handling: date-fns
├── File Upload: react-dropzone
├── Rich Text: TipTap
└── Icons: Lucide React
```

### 11.2 Dashboard Layout

```
┌─────────────────────────────────────────────────────────────────┐
│  [Logo]  Ambilet Admin                    [Search] [User Menu]  │
├──────────┬──────────────────────────────────────────────────────┤
│          │                                                      │
│ Dashboard│  [Main Content Area]                                 │
│ Events   │                                                      │
│ Orders   │  Breadcrumbs: Dashboard > Events > Edit Event        │
│ Tickets  │  ───────────────────────────────────────────────     │
│ ──────── │                                                      │
│ Organiz. │  [Page Title]                   [Action Buttons]     │
│ Customers│                                                      │
│ ──────── │  ┌─────────────────────────────────────────────┐     │
│ Reports  │  │                                             │     │
│ ──────── │  │           [Content / Forms / Tables]        │     │
│ Artists  │  │                                             │     │
│ Venues   │  │                                             │     │
│ Categor. │  │                                             │     │
│ ──────── │  └─────────────────────────────────────────────┘     │
│ Microser.│                                                      │
│ Payments │                                                      │
│ Users    │                                                      │
│ Settings │                                                      │
│          │                                                      │
└──────────┴──────────────────────────────────────────────────────┘
```

### 11.3 Page Structure

```
/admin/ambilet/
├── login                      # Login page
├── forgot-password            # Password recovery
├── dashboard                  # Main dashboard
│
├── events/
│   ├── index                  # Events list
│   ├── create                 # Create event
│   ├── [id]/
│   │   ├── edit               # Edit event
│   │   ├── tickets            # Manage ticket types
│   │   ├── attendees          # Attendees list
│   │   ├── check-in           # Check-in management
│   │   └── reports            # Event-specific reports
│
├── orders/
│   ├── index                  # Orders list
│   └── [id]                   # Order details
│
├── tickets/
│   ├── index                  # All tickets
│   └── [barcode]              # Ticket details
│
├── organizers/
│   ├── index                  # Organizers list
│   ├── create                 # Add organizer
│   └── [id]/
│       ├── edit               # Edit organizer
│       ├── events             # Organizer events
│       └── payouts            # Payout history
│
├── customers/
│   ├── index                  # Customers list
│   └── [id]                   # Customer details
│
├── reports/
│   ├── index                  # Reports dashboard
│   ├── sales                  # Sales reports
│   ├── tickets                # Ticket reports
│   ├── customers              # Customer analytics
│   ├── marketing              # Marketing reports
│   └── financial              # Financial reports
│
├── artists/
│   ├── index                  # Artists list
│   ├── create                 # Add artist
│   └── [id]/edit              # Edit artist (own only)
│
├── venues/
│   ├── index                  # Venues list
│   ├── create                 # Add venue
│   └── [id]/
│       ├── edit               # Edit venue (own only)
│       └── seating            # Seating map editor
│
├── categories/
│   ├── index                  # Categories list
│   ├── create                 # Create category
│   └── [id]/edit              # Edit category
│
├── microservices/
│   ├── index                  # Microservices overview
│   ├── affiliates/            # Affiliate management
│   ├── coupons/               # Coupon codes
│   ├── gamification/          # Gamification campaigns
│   ├── tracking/              # Pixel tracking
│   ├── taxes/                 # Tax configuration
│   ├── invitations/           # Invitation campaigns
│   ├── shop/                  # Online shop / merchandise
│   └── ticket-customizer/     # Ticket template designer
│
├── payments/
│   ├── index                  # Payment gateways
│   ├── transactions           # Transaction history
│   └── configure/[gateway]    # Gateway configuration
│
├── users/
│   ├── index                  # Staff users list
│   ├── create                 # Add user
│   └── [id]/edit              # Edit user
│
└── settings/
    ├── index                  # General settings
    ├── branding               # Logo, colors, etc.
    ├── notifications          # Email templates
    ├── api                    # API keys
    └── billing                # Subscription & billing
```

---

## 12. API Structure

### 12.1 Base URL Pattern

```
/api/marketplace/{marketplace_slug}/...

Example:
/api/marketplace/ambilet/events
/api/marketplace/ambilet/orders
```

### 12.2 Authentication Headers

```
Authorization: Bearer {access_token}
X-Marketplace-ID: {marketplace_client_id}
Content-Type: application/json
Accept: application/json
```

### 12.3 Response Format

```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully",
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "last_page": 6
  }
}
```

---

## 13. Implementation Phases

### Phase 1: Foundation (Week 1-2)
- [ ] Database migrations for all core tables
- [ ] Marketplace client model and authentication
- [ ] Basic API structure and routing
- [ ] User roles and permissions system
- [ ] Frontend project setup (Next.js)
- [ ] Authentication pages (login, forgot password)
- [ ] Dashboard layout and navigation

### Phase 2: Core Modules (Week 3-4)
- [ ] Events CRUD
- [ ] Orders management
- [ ] Tickets management
- [ ] Basic dashboard with statistics

### Phase 3: User Management (Week 5)
- [ ] Organizer accounts CRUD
- [ ] Customer accounts CRUD
- [ ] Staff user management
- [ ] Role-based access control

### Phase 4: Content Management (Week 6)
- [ ] Artists database (with ownership rules)
- [ ] Venues database (with ownership rules)
- [ ] Event categories (customizable)

### Phase 5: Microservices - Part 1 (Week 7-8)
- [ ] Venue seating maps
- [ ] Coupon codes
- [ ] Affiliates system
- [ ] Tax configuration

### Phase 6: Microservices - Part 2 (Week 9-10)
- [ ] Ticket customizer
- [ ] Gamification
- [ ] Invitations
- [ ] Online shop

### Phase 7: Payment & Tracking (Week 11)
- [ ] Multiple payment gateway integration
- [ ] Pixel tracking
- [ ] Conversion tracking

### Phase 8: Reports & Analytics (Week 12)
- [ ] Sales reports
- [ ] Ticket reports
- [ ] Customer analytics
- [ ] Marketing reports
- [ ] Financial reports
- [ ] Export functionality

### Phase 9: Testing & Polish (Week 13-14)
- [ ] Comprehensive testing
- [ ] Performance optimization
- [ ] Security audit
- [ ] Documentation
- [ ] Deployment

---

## 14. File Structure (Implementation)

```
/home/user/epas/
├── backend/                           # Laravel Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── Marketplace/
│   │   │   │       ├── AuthController.php
│   │   │   │       ├── DashboardController.php
│   │   │   │       ├── EventController.php
│   │   │   │       ├── OrderController.php
│   │   │   │       ├── TicketController.php
│   │   │   │       ├── OrganizerController.php
│   │   │   │       ├── CustomerController.php
│   │   │   │       ├── ArtistController.php
│   │   │   │       ├── VenueController.php
│   │   │   │       ├── CategoryController.php
│   │   │   │       ├── UserController.php
│   │   │   │       ├── ReportController.php
│   │   │   │       └── Microservices/
│   │   │   │           ├── AffiliateController.php
│   │   │   │           ├── CouponController.php
│   │   │   │           ├── GamificationController.php
│   │   │   │           ├── InvitationController.php
│   │   │   │           ├── ShopController.php
│   │   │   │           ├── SeatingController.php
│   │   │   │           ├── TaxController.php
│   │   │   │           ├── TrackingController.php
│   │   │   │           └── TicketTemplateController.php
│   │   │   └── Middleware/
│   │   │       └── MarketplaceAuth.php
│   │   ├── Models/
│   │   │   ├── MarketplaceClient.php
│   │   │   ├── MarketplaceUser.php
│   │   │   ├── Event.php
│   │   │   ├── Order.php
│   │   │   ├── Ticket.php
│   │   │   ├── Organizer.php
│   │   │   ├── Customer.php
│   │   │   ├── Artist.php
│   │   │   ├── Venue.php
│   │   │   ├── EventCategory.php
│   │   │   └── ... (all models)
│   │   ├── Services/
│   │   │   ├── PaymentGateway/
│   │   │   │   ├── NetopiaService.php
│   │   │   │   └── StripeService.php
│   │   │   └── ...
│   │   └── Policies/
│   │       ├── ArtistPolicy.php
│   │       └── VenuePolicy.php
│   ├── database/
│   │   └── migrations/
│   │       └── marketplace/
│   │           └── ... (all migrations)
│   └── routes/
│       └── marketplace.php
│
└── frontend/                          # Next.js Frontend
    └── apps/
        └── marketplace-admin/
            ├── app/
            │   ├── (auth)/
            │   │   ├── login/
            │   │   └── forgot-password/
            │   ├── (dashboard)/
            │   │   ├── layout.tsx
            │   │   ├── page.tsx
            │   │   ├── events/
            │   │   ├── orders/
            │   │   ├── tickets/
            │   │   ├── organizers/
            │   │   ├── customers/
            │   │   ├── reports/
            │   │   ├── artists/
            │   │   ├── venues/
            │   │   ├── categories/
            │   │   ├── microservices/
            │   │   ├── payments/
            │   │   ├── users/
            │   │   └── settings/
            │   └── layout.tsx
            ├── components/
            │   ├── ui/                # shadcn/ui components
            │   ├── forms/
            │   ├── tables/
            │   ├── charts/
            │   └── layout/
            ├── lib/
            │   ├── api/
            │   ├── hooks/
            │   └── utils/
            ├── stores/
            └── types/
```

---

## 15. Security Considerations

### 15.1 Authentication
- JWT tokens with short expiry (15 min access, 7 day refresh)
- Secure password hashing (bcrypt)
- Rate limiting on auth endpoints
- Account lockout after failed attempts

### 15.2 Authorization
- Role-based access control (RBAC)
- Resource-level permissions
- Ownership verification for artists/venues
- Audit logging for sensitive actions

### 15.3 Data Protection
- Input validation and sanitization
- SQL injection prevention (ORM)
- XSS prevention
- CSRF tokens
- Encrypted sensitive data (payment credentials)

### 15.4 API Security
- HTTPS only
- API rate limiting
- Request signing for webhooks
- IP whitelisting option

---

## Approval Required

Please review this comprehensive plan for the Ambilet Marketplace Admin Dashboard. Once approved, I will begin implementation on a new development branch.

**Key Decisions to Confirm:**
1. Technology stack (Laravel backend + Next.js frontend) - OK?
2. Database structure approach - OK?
3. Microservices to include - any to add/remove?
4. Implementation phases timeline realistic?
5. Any additional features to add?
