# Affiliate Tracking & Commissions

## Short Presentation

Grow your sales network with Affiliate Tracking & Commissions. Partner with influencers, bloggers, venues, and promoters who bring new customers to your events. Track every referral and reward partners fairly with automated commission management.

Last-click attribution with a 90-day cookie window ensures affiliates get credit for sales they influence. Whether customers click a link today and buy next week, or use a special coupon code, the conversion is tracked and attributed correctly.

Set commission rates that work for your business. Percentage of sale, fixed amount per ticket, or tiered rates that reward high performers - configure whatever makes sense for each affiliate relationship.

Automatic deduplication prevents double-counting conversions. The self-purchase guard stops affiliates from gaming the system with their own purchases. Fair rules protect your margins while rewarding genuine promotion.

Separate dashboards serve both sides. Affiliates see their clicks, conversions, and pending earnings in real-time. You see the full picture: which affiliates drive results, conversion rates by channel, and total commission liability.

Export data for accounting with a click. Track conversion status through pending, approved, and paid stages. Reverse commissions when refunds happen.

Turn your customers into promoters. Affiliate Tracking makes word-of-mouth measurable and scalable.

---

## Features

### Tracking
- Last-click attribution with 90-day cookie window
- Support for both link and coupon-based tracking
- Real-time click and conversion tracking
- Automatic deduplication of conversions
- Self-purchase guard to prevent fraud

### Commissions
- Configurable commission rates (percentage or fixed)
- Conversion status management (pending/approved/reversed)
- Automatic commission calculation

### Reporting
- Detailed analytics and reporting dashboards
- Separate dashboards for tenants and affiliates
- CSV export for accounting

---

## Technical Documentation

### Configuration

```php
'affiliate_tracking' => [
    'cookie_window' => 90, // days
    'deduplication' => true,
    'self_purchase_guard' => true,
]
```
