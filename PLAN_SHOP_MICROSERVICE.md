# Shop Microservice Implementation Plan

## Overview

The Shop microservice enables tenants to sell physical and digital products through their platform. It integrates with the existing event ticketing system, payment processors, and coupon system.

---

## Database Schema

### 1. Products Table (`shop_products`)

```
id (uuid, PK)
tenant_id (FK → tenants)
category_id (FK → shop_categories, nullable)
title (json, translatable)
slug (string, unique per tenant)
description (json, translatable)
short_description (json, translatable)
type (enum: physical, digital)
sku (string, unique per tenant)
price_cents (integer)
sale_price_cents (integer, nullable)
cost_cents (integer, nullable) - for profit tracking
currency (string, default: tenant currency)
stock_quantity (integer, nullable) - null = unlimited
low_stock_threshold (integer, default: 5)
track_inventory (boolean, default: true)
weight_grams (integer, nullable) - for shipping calc
dimensions (json, nullable) - {length, width, height, unit}
image_url (string, nullable)
gallery (json, nullable) - array of image URLs
digital_file_url (string, nullable) - for digital products
digital_download_limit (integer, nullable)
digital_download_expiry_days (integer, nullable)
status (enum: draft, active, out_of_stock, discontinued)
is_featured (boolean, default: false)
meta (json, nullable) - custom fields
seo (json, nullable)
created_at, updated_at, deleted_at (soft deletes)

Indexes: tenant_id, category_id, slug, sku, status
Unique: (tenant_id, slug), (tenant_id, sku)
```

### 2. Categories Table (`shop_categories`)

```
id (uuid, PK)
tenant_id (FK → tenants)
parent_id (FK → shop_categories, nullable) - for nested categories
name (json, translatable)
slug (string, unique per tenant)
description (json, translatable, nullable)
image_url (string, nullable)
sort_order (integer, default: 0)
is_active (boolean, default: true)
meta (json, nullable)
created_at, updated_at

Indexes: tenant_id, parent_id, slug
Unique: (tenant_id, slug)
```

### 3. Attributes Table (`shop_attributes`)

```
id (uuid, PK)
tenant_id (FK → tenants)
name (json, translatable) - e.g., "Size", "Color"
slug (string, unique per tenant)
type (enum: select, color, text)
is_visible (boolean, default: true)
sort_order (integer, default: 0)
created_at, updated_at

Unique: (tenant_id, slug)
```

### 4. Attribute Values Table (`shop_attribute_values`)

```
id (uuid, PK)
attribute_id (FK → shop_attributes)
value (json, translatable) - e.g., "Small", "Red"
slug (string)
color_hex (string, nullable) - for color type attributes
sort_order (integer, default: 0)
created_at, updated_at

Unique: (attribute_id, slug)
```

### 5. Product Variants Table (`shop_product_variants`)

```
id (uuid, PK)
product_id (FK → shop_products)
sku (string, unique per tenant)
price_cents (integer, nullable) - override product price
sale_price_cents (integer, nullable)
stock_quantity (integer, nullable)
weight_grams (integer, nullable) - override
image_url (string, nullable)
is_active (boolean, default: true)
sort_order (integer, default: 0)
created_at, updated_at

Unique: (product_id, sku)
```

### 6. Variant Attribute Values Pivot (`shop_variant_attribute_value`)

```
variant_id (FK → shop_product_variants)
attribute_value_id (FK → shop_attribute_values)

Primary: (variant_id, attribute_value_id)
```

### 7. Product Attributes Pivot (`shop_product_attribute`)

```
product_id (FK → shop_products)
attribute_id (FK → shop_attributes)

Primary: (product_id, attribute_id)
```

### 8. Shop Orders Table (`shop_orders`)

```
id (uuid, PK)
tenant_id (FK → tenants)
order_number (string, unique per tenant) - e.g., "SH-2024-00001"
customer_id (FK → customers, nullable)
customer_email (string)
status (enum: pending, processing, shipped, delivered, completed, cancelled, refunded)
payment_status (enum: pending, paid, failed, refunded)
fulfillment_status (enum: unfulfilled, partial, fulfilled)
subtotal_cents (integer)
discount_cents (integer, default: 0)
shipping_cents (integer, default: 0)
tax_cents (integer, default: 0)
total_cents (integer)
currency (string)
coupon_code (string, nullable)
coupon_discount_cents (integer, nullable)
billing_address (json)
shipping_address (json, nullable)
shipping_method (string, nullable)
shipping_provider (string, nullable)
tracking_number (string, nullable)
notes (text, nullable)
internal_notes (text, nullable)
event_id (FK → events, nullable) - if purchased during event checkout
meta (json, nullable)
paid_at (timestamp, nullable)
shipped_at (timestamp, nullable)
delivered_at (timestamp, nullable)
completed_at (timestamp, nullable)
cancelled_at (timestamp, nullable)
created_at, updated_at

Indexes: tenant_id, customer_id, status, order_number, event_id
```

### 9. Shop Order Items Table (`shop_order_items`)

```
id (uuid, PK)
order_id (FK → shop_orders)
product_id (FK → shop_products)
variant_id (FK → shop_product_variants, nullable)
quantity (integer)
unit_price_cents (integer)
total_cents (integer)
product_snapshot (json) - store product details at time of purchase
variant_snapshot (json, nullable)
is_bundled (boolean, default: false) - part of ticket bundle
ticket_type_id (FK → ticket_types, nullable) - if bundled with ticket
created_at, updated_at

Indexes: order_id, product_id
```

### 10. Event Product Associations (`shop_event_products`)

```
id (uuid, PK)
event_id (FK → events)
product_id (FK → shop_products)
association_type (enum: upsell, bundle)
ticket_type_id (FK → ticket_types, nullable) - for bundles
quantity_included (integer, default: 1) - for bundles
sort_order (integer, default: 0)
is_active (boolean, default: true)
created_at, updated_at

Unique: (event_id, product_id, association_type, ticket_type_id)
```

### 11. Shipping Zones Table (`shop_shipping_zones`)

```
id (uuid, PK)
tenant_id (FK → tenants)
name (string)
countries (json) - array of country codes
regions (json, nullable) - array of state/region codes
is_default (boolean, default: false)
is_active (boolean, default: true)
created_at, updated_at

Indexes: tenant_id
```

### 12. Shipping Methods Table (`shop_shipping_methods`)

```
id (uuid, PK)
zone_id (FK → shop_shipping_zones)
name (string)
description (string, nullable)
provider (string, nullable) - e.g., "dhl", "fedex", "local"
calculation_type (enum: flat, weight_based, price_based, free)
cost_cents (integer, default: 0) - for flat rate
cost_per_kg_cents (integer, nullable) - for weight-based
min_order_cents (integer, nullable) - for free shipping threshold
max_order_cents (integer, nullable) - for price-based tiers
estimated_days_min (integer, nullable)
estimated_days_max (integer, nullable)
is_active (boolean, default: true)
sort_order (integer, default: 0)
created_at, updated_at

Indexes: zone_id
```

### 13. Digital Downloads Table (`shop_digital_downloads`)

```
id (uuid, PK)
order_item_id (FK → shop_order_items)
customer_id (FK → customers)
download_token (string, unique)
download_count (integer, default: 0)
max_downloads (integer, nullable)
expires_at (timestamp, nullable)
last_downloaded_at (timestamp, nullable)
created_at, updated_at

Indexes: download_token, customer_id
```

---

## Models

### Core Models

1. **ShopProduct** (`App\Models\Shop\ShopProduct`)
   - Translatable: title, description, short_description
   - HasMany: variants, orderItems
   - BelongsTo: tenant, category
   - BelongsToMany: attributes
   - HasMany: eventProducts
   - Scopes: active(), inStock(), featured(), byCategory()
   - Methods: getDisplayPrice(), hasVariants(), isInStock()

2. **ShopCategory** (`App\Models\Shop\ShopCategory`)
   - Translatable: name, description
   - BelongsTo: tenant, parent
   - HasMany: children, products
   - Scopes: active(), root()

3. **ShopAttribute** (`App\Models\Shop\ShopAttribute`)
   - Translatable: name
   - HasMany: values
   - BelongsToMany: products

4. **ShopAttributeValue** (`App\Models\Shop\ShopAttributeValue`)
   - Translatable: value
   - BelongsTo: attribute
   - BelongsToMany: variants

5. **ShopProductVariant** (`App\Models\Shop\ShopProductVariant`)
   - BelongsTo: product
   - BelongsToMany: attributeValues
   - Methods: getDisplayPrice(), isInStock(), getAttributeLabel()

6. **ShopOrder** (`App\Models\Shop\ShopOrder`)
   - BelongsTo: tenant, customer, event
   - HasMany: items
   - Methods: generateOrderNumber(), calculateTotals()

7. **ShopOrderItem** (`App\Models\Shop\ShopOrderItem`)
   - BelongsTo: order, product, variant, ticketType

8. **ShopEventProduct** (`App\Models\Shop\ShopEventProduct`)
   - BelongsTo: event, product, ticketType

9. **ShopShippingZone** (`App\Models\Shop\ShopShippingZone`)
   - HasMany: methods
   - Methods: matchesAddress()

10. **ShopShippingMethod** (`App\Models\Shop\ShopShippingMethod`)
    - BelongsTo: zone
    - Methods: calculateCost(), isAvailableForOrder()

11. **ShopDigitalDownload** (`App\Models\Shop\ShopDigitalDownload`)
    - BelongsTo: orderItem, customer
    - Methods: canDownload(), incrementDownload()

---

## Filament Resources & Pages

### Resources (App\Filament\Tenant\Resources)

1. **ShopProductResource**
   - Pages: List, Create, Edit
   - Sections:
     - Basic Info (title, slug, description, category)
     - Pricing (price, sale price, cost)
     - Inventory (SKU, stock, tracking)
     - Media (image, gallery)
     - Digital Product (file, download limits)
     - Attributes & Variants
     - SEO
   - Relations: Variants, Event Associations

2. **ShopCategoryResource**
   - Pages: List, Create, Edit
   - Tree view for nested categories
   - Drag-and-drop reordering

3. **ShopAttributeResource**
   - Pages: List, Create, Edit
   - Inline values management
   - Color picker for color-type attributes

4. **ShopOrderResource**
   - Pages: List, View
   - Order fulfillment actions
   - Status transitions
   - Shipping label generation (future)
   - Refund processing

### Pages (App\Filament\Tenant\Pages)

1. **ShopSettings** - General shop configuration
   - Store name, description
   - Default currency
   - Tax settings
   - Order number prefix
   - Email notifications

2. **ShopShippingSettings** - Shipping configuration
   - Shipping zones management
   - Shipping methods per zone
   - Free shipping thresholds
   - Provider integrations

3. **ShopInventory** - Stock management dashboard
   - Low stock alerts
   - Bulk stock updates
   - Stock movement history

---

## API Endpoints

### Public API (TenantClient)

**Products**
- `GET /api/shop/products` - List products (filterable, paginated)
- `GET /api/shop/products/{slug}` - Product details with variants
- `GET /api/shop/categories` - List categories
- `GET /api/shop/categories/{slug}` - Category with products

**Cart** (extend existing CartController or create ShopCartController)
- `GET /api/shop/cart` - Get shop cart
- `POST /api/shop/cart/items` - Add product to cart
- `PUT /api/shop/cart/items/{id}` - Update quantity
- `DELETE /api/shop/cart/items/{id}` - Remove item
- `POST /api/shop/cart/shipping` - Calculate shipping
- `POST /api/shop/cart/coupon` - Apply coupon

**Checkout**
- `POST /api/shop/checkout/init` - Initialize checkout
- `POST /api/shop/checkout/submit` - Submit order
- `GET /api/shop/checkout/shipping-methods` - Available shipping

**Orders**
- `GET /api/shop/orders` - Customer's orders
- `GET /api/shop/orders/{number}` - Order details

**Downloads**
- `GET /api/shop/downloads/{token}` - Download digital product

---

## Integrations

### 1. Coupon System Integration

Update existing `CouponCode` model to support shop products:

- `applicable_products` (json) - Array of shop_product UUIDs
- `applicable_categories` (json) - Array of shop_category UUIDs
- Update `isValidForProduct($productId)` method
- Update `calculateDiscount()` to handle product-based discounts

**New discount types for shop:**
- `free_product` - Include free product with purchase
- `bundle_discount` - Discount on product bundles

### 2. Event Integration

**Event Form Extension:**
- Add "Shop Products" section to EventResource form
- Two association types:
  1. **Upsell Products**: Products shown during checkout for cross-selling
  2. **Bundle Products**: Products included with specific ticket types

**Checkout Integration:**
- Show upsell products after ticket selection
- Auto-add bundle products to cart when ticket added
- Combined payment processing

### 3. Payment Integration

- Use existing `TenantPaymentConfig` and `PaymentProcessorFactory`
- Single checkout flow for mixed cart (tickets + products)
- Support split payments (future)

### 4. Inventory Management

- Real-time stock updates
- Low stock notifications (email + dashboard)
- Stock reservation during checkout (15-minute hold)
- Stock release on abandoned carts

---

## Microservice Registration

### 1. Create Microservice Record

```php
Microservice::create([
    'name' => ['en' => 'Shop', 'ro' => 'Magazin'],
    'slug' => 'shop',
    'description' => ['en' => 'Sell physical and digital products...'],
    'icon' => 'heroicon-o-shopping-bag',
    'price' => 29.00,
    'currency' => 'EUR',
    'billing_cycle' => 'monthly',
]);
```

### 2. Configuration Schema

Settings stored in `tenant_microservice.configuration`:

```json
{
  "store_name": "My Store",
  "store_description": "",
  "currency": "RON",
  "tax_rate": 19,
  "tax_included": true,
  "order_prefix": "SH",
  "low_stock_threshold": 5,
  "enable_reviews": false,
  "enable_wishlist": false,
  "checkout_mode": "combined",
  "notification_email": "orders@example.com",
  "notification_events": ["new_order", "low_stock", "out_of_stock"]
}
```

### 3. MicroserviceSettings Extension

Add `shop` case to `MicroserviceSettings.php`:

```php
'shop' => $this->getShopSchema(),
```

---

## Additional Features (My Suggestions)

### 1. Product Reviews & Ratings
- Allow customers to review purchased products
- Display average ratings on product pages
- Moderation queue for reviews

### 2. Wishlist
- Save products for later
- Share wishlist functionality
- Back-in-stock notifications

### 3. Product Bundles
- Create custom bundles with discounted pricing
- Bundle-only products

### 4. Related Products
- Automatic suggestions based on category
- Manual "frequently bought together" associations

### 5. Stock Alerts
- Customer notification when out-of-stock item is available
- Pre-order functionality for upcoming products

### 6. Gift Cards (Future)
- Digital gift cards as products
- Gift card redemption at checkout

### 7. Analytics Dashboard
- Sales by product/category
- Inventory turnover
- Customer purchase patterns
- Revenue from event bundles vs direct sales

### 8. Multi-Currency Support
- Display prices in customer's currency
- Convert at checkout

### 9. Product Import/Export
- CSV import for bulk product creation
- Export for inventory management

### 10. Abandoned Cart Recovery
- Email reminders for abandoned carts
- Discount incentives to complete purchase

---

## Implementation Order

### Phase 1: Core Infrastructure
1. Database migrations
2. Models with relationships
3. Microservice registration

### Phase 2: Admin Interface
4. ShopCategoryResource
5. ShopAttributeResource
6. ShopProductResource
7. ShopOrderResource
8. ShopSettings page
9. ShopShippingSettings page

### Phase 3: API & Frontend
10. Products API endpoints
11. Cart API (shop items)
12. Checkout integration
13. Digital downloads

### Phase 4: Integrations
14. Coupon system integration
15. Event product associations
16. Combined checkout flow

### Phase 5: Enhancements
17. Inventory management dashboard
18. Low stock notifications
19. Analytics dashboard

---

## Files to Create

### Migrations (14 files)
- `create_shop_categories_table.php`
- `create_shop_attributes_table.php`
- `create_shop_attribute_values_table.php`
- `create_shop_products_table.php`
- `create_shop_product_attribute_table.php`
- `create_shop_product_variants_table.php`
- `create_shop_variant_attribute_value_table.php`
- `create_shop_orders_table.php`
- `create_shop_order_items_table.php`
- `create_shop_event_products_table.php`
- `create_shop_shipping_zones_table.php`
- `create_shop_shipping_methods_table.php`
- `create_shop_digital_downloads_table.php`
- `add_shop_fields_to_coupon_codes_table.php`

### Models (13 files)
- `App\Models\Shop\ShopCategory.php`
- `App\Models\Shop\ShopAttribute.php`
- `App\Models\Shop\ShopAttributeValue.php`
- `App\Models\Shop\ShopProduct.php`
- `App\Models\Shop\ShopProductVariant.php`
- `App\Models\Shop\ShopOrder.php`
- `App\Models\Shop\ShopOrderItem.php`
- `App\Models\Shop\ShopEventProduct.php`
- `App\Models\Shop\ShopShippingZone.php`
- `App\Models\Shop\ShopShippingMethod.php`
- `App\Models\Shop\ShopDigitalDownload.php`
- `App\Models\Shop\ShopCart.php` (session-based)
- `App\Models\Shop\ShopCartItem.php`

### Filament Resources (4 files + pages)
- `App\Filament\Tenant\Resources\ShopProductResource.php`
- `App\Filament\Tenant\Resources\ShopProductResource\Pages\*.php`
- `App\Filament\Tenant\Resources\ShopCategoryResource.php`
- `App\Filament\Tenant\Resources\ShopAttributeResource.php`
- `App\Filament\Tenant\Resources\ShopOrderResource.php`

### Filament Pages (3 files)
- `App\Filament\Tenant\Pages\ShopSettings.php`
- `App\Filament\Tenant\Pages\ShopShippingSettings.php`
- `App\Filament\Tenant\Pages\ShopInventory.php`

### API Controllers (4 files)
- `App\Http\Controllers\Api\TenantClient\ShopProductController.php`
- `App\Http\Controllers\Api\TenantClient\ShopCartController.php`
- `App\Http\Controllers\Api\TenantClient\ShopCheckoutController.php`
- `App\Http\Controllers\Api\TenantClient\ShopDownloadController.php`

### Services (3 files)
- `App\Services\Shop\ShopCartService.php`
- `App\Services\Shop\ShopCheckoutService.php`
- `App\Services\Shop\ShopInventoryService.php`

### Notifications (3 files)
- `App\Notifications\Shop\NewShopOrderNotification.php`
- `App\Notifications\Shop\LowStockNotification.php`
- `App\Notifications\Shop\OrderShippedNotification.php`

### Views (for admin/emails)
- `resources/views/filament/tenant/pages/shop-settings.blade.php`
- `resources/views/filament/tenant/pages/shop-shipping-settings.blade.php`
- `resources/views/filament/tenant/pages/shop-inventory.blade.php`
- `resources/views/emails/shop/new-order.blade.php`
- `resources/views/emails/shop/order-shipped.blade.php`

---

## Estimated Scope

- **Migrations**: 14 files
- **Models**: 13 files
- **Resources**: 4 resources with pages
- **Pages**: 3 custom pages
- **Controllers**: 4 API controllers
- **Services**: 3 service classes
- **Notifications**: 3 notification classes
- **Views**: 5+ blade files
- **Event integration**: Modify EventResource

**Total: ~50-60 files**

---

## Questions for Clarification

1. Should digital products support multiple file attachments?
2. Should there be product-level tax overrides?
3. Do you want inventory sync with external systems (future)?
4. Should the shop have its own separate checkout page or always combined with tickets?
5. Do you want subscription products (recurring payments)?

---

This plan follows all existing architectural patterns in your codebase and integrates seamlessly with the existing coupon system, payment processors, and event management.
