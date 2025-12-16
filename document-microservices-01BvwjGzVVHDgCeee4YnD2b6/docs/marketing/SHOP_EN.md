# 🛒 Shop Microservice

## Overview

Transform your event platform into a complete e-commerce solution. The Shop microservice enables you to sell physical merchandise, digital products, and gift cards alongside your event tickets. Create upsell opportunities, bundle products with tickets, and increase revenue per customer.

**Pricing:** €29/month per tenant

---

## Key Features

### Product Management
- **Physical Products**: Sell merchandise, apparel, accessories with full inventory tracking
- **Digital Products**: Deliver downloadable content with secure, expiring download links
- **Product Variants**: Create size, color, and custom attribute combinations
- **Categories & Organization**: Hierarchical category structure for easy navigation
- **Gallery Support**: Multiple product images with featured image selection

### Inventory & Stock Control
- **Real-time Inventory**: Automatic stock updates on purchase and refund
- **Low Stock Alerts**: Get notified when products are running low
- **Stock Reservation**: 15-minute hold during checkout to prevent overselling
- **Unlimited Stock Option**: Perfect for digital products or made-to-order items

### Flexible Pricing
- **Sale Prices**: Schedule promotional pricing
- **Cost Tracking**: Monitor profit margins with cost-per-item tracking
- **Multi-currency**: Support for RON, EUR, USD, GBP

### Shipping & Fulfillment
- **Shipping Zones**: Define regions with custom shipping rates
- **Multiple Methods**: Flat rate, weight-based, price-based, or free shipping
- **Delivery Estimates**: Show expected delivery times to customers
- **Order Fulfillment**: Track order status from processing to delivered

### Event Integration
- **Upsell Products**: Suggest related products during ticket checkout
- **Bundle Products**: Include merchandise with specific ticket types
- **Combined Checkout**: Single payment for tickets and products

### Gift Cards
- **Digital Gift Cards**: Sell and redeem gift cards in your store
- **Custom Values**: Allow customers to choose gift card amounts
- **Balance Tracking**: Real-time gift card balance management

### Customer Experience
- **Product Reviews**: Collect and display customer reviews
- **Wishlist**: Let customers save products for later
- **Back-in-Stock Notifications**: Alert customers when sold-out items return

---

## Order Management

### Order Workflow
1. **Pending** - Order placed, awaiting payment
2. **Processing** - Payment received, preparing order
3. **Shipped** - Order dispatched with tracking number
4. **Delivered** - Order received by customer
5. **Completed** - Transaction finalized
6. **Cancelled/Refunded** - Order cancelled or refunded

### Features
- Automated order number generation (e.g., SH-2024-00001)
- Detailed order history with timestamps
- Internal notes for staff communication
- Bulk order export for fulfillment

---

## Admin Panel

Access the Shop management through your tenant dashboard:

- **Products** → Create and manage your product catalog
- **Categories** → Organize products into categories
- **Attributes** → Define product attributes (size, color, etc.)
- **Orders** → View and process customer orders
- **Gift Cards** → Manage gift card inventory
- **Shipping** → Configure shipping zones and methods
- **Settings** → General shop configuration

---

## Integration Points

### Payment Processing
Works seamlessly with your existing payment processors (Stripe, PayPal, etc.)

### Coupon System
Full integration with the platform coupon system:
- Product-specific discounts
- Category-wide promotions
- Bundle discounts
- Free product with purchase

### Analytics
Track your shop performance:
- Sales by product and category
- Revenue trends
- Inventory turnover
- Customer purchase patterns

---

## API Endpoints

### Products
- `GET /api/shop/products` - List products with filtering and pagination
- `GET /api/shop/products/{slug}` - Get product details with variants
- `GET /api/shop/categories` - List all categories
- `GET /api/shop/categories/{slug}` - Category with products

### Cart
- `GET /api/shop/cart` - Get current cart
- `POST /api/shop/cart/items` - Add product to cart
- `PUT /api/shop/cart/items/{id}` - Update quantity
- `DELETE /api/shop/cart/items/{id}` - Remove item
- `POST /api/shop/cart/coupon` - Apply coupon code

### Checkout
- `POST /api/shop/checkout/init` - Initialize checkout session
- `POST /api/shop/checkout/submit` - Complete purchase
- `GET /api/shop/checkout/shipping-methods` - Available shipping options

### Orders
- `GET /api/shop/orders` - Customer order history
- `GET /api/shop/orders/{number}` - Order details

### Downloads
- `GET /api/shop/downloads/{token}` - Download digital product

---

## Configuration Options

| Setting | Description | Default |
|---------|-------------|---------|
| Store Name | Display name for your shop | Tenant name |
| Currency | Default currency for products | RON |
| Tax Rate | VAT/Sales tax percentage | 19% |
| Tax Included | Prices include tax | Yes |
| Order Prefix | Prefix for order numbers | SH |
| Low Stock Threshold | Alert when stock falls below | 5 |
| Enable Reviews | Allow product reviews | No |
| Enable Wishlist | Allow wishlist feature | No |
| Checkout Mode | Combined with tickets or separate | Combined |

---

## Use Cases

### Festival Merchandise
Sell official event merchandise, band t-shirts, and memorabilia alongside festival tickets.

### Conference Materials
Offer digital course materials, recordings, and physical workbooks for conference attendees.

### VIP Packages
Create premium bundles that include tickets, merchandise, and exclusive digital content.

### Venue Merchandise
Theaters and venues can sell show programs, posters, and souvenirs.

---

## Getting Started

1. **Activate the Microservice**: Enable Shop in your tenant settings
2. **Configure Settings**: Set up your store name, currency, and tax settings
3. **Add Products**: Create your first products with images and pricing
4. **Set Up Shipping**: Configure shipping zones and methods
5. **Test Checkout**: Place a test order to verify the flow
6. **Go Live**: Start selling!

---

## Support

For assistance with the Shop microservice, contact your platform administrator or refer to the technical documentation.
