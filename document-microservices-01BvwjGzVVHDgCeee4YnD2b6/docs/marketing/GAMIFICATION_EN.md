# ⭐ Gamification Microservice

## Overview

Build customer loyalty and increase repeat purchases with a powerful points-based rewards system. The Gamification microservice transforms casual buyers into loyal fans by rewarding purchases, referrals, and special occasions with redeemable points.

**Pricing:** €15/month per tenant

---

## Key Features

### Points Earning System
- **Purchase Rewards**: Customers earn a percentage of every order as points
- **Signup Bonus**: Welcome new customers with bonus points
- **Birthday Bonus**: Celebrate customers with special birthday points
- **Referral Program**: Reward both referrers and new customers
- **Custom Actions**: Define custom point-earning activities

### Points Redemption
- **Flexible Redemption**: Use points as payment during checkout
- **Configurable Limits**: Set minimum points to redeem and maximum discount percentage
- **Order Caps**: Optional per-order redemption limits
- **Instant Discount**: Points convert to real currency value

### Customer Tiers
- **Loyalty Levels**: Create Bronze, Silver, Gold, Platinum tiers (or custom names)
- **Points Multipliers**: Higher tiers earn bonus points on purchases
- **Visual Badges**: Each tier has customizable colors and icons
- **Automatic Progression**: Customers advance tiers based on total points earned

### Referral System
- **Unique Referral Codes**: Each customer gets a personal referral code
- **Shareable Links**: Easy-to-share referral URLs
- **Dual Rewards**: Both referrer and referred customer earn points
- **Tracking Dashboard**: Monitor referral performance and conversions

### Points Expiration
- **Configurable Expiry**: Set points to expire after a specific period
- **Inactivity Rules**: Optional expiration based on account inactivity
- **Transparent Display**: Show expiration dates to customers
- **Automated Processing**: Daily maintenance handles point expiration

---

## How Points Work

### Earning Points

| Action | Default Points | Configurable |
|--------|---------------|--------------|
| Purchase | 5% of order value | Yes |
| Sign Up | 50 points | Yes |
| Birthday | 100 points | Yes |
| Referral (Referrer) | 200 points | Yes |
| Referral (New Customer) | 100 points | Yes |

### Redeeming Points

Example configuration:
- 1 point = 0.01 RON (1 cent)
- Minimum redemption: 100 points (1 RON)
- Maximum discount: 50% of order total

A customer with 500 points can redeem up to 5 RON off their next purchase (limited to 50% of the order).

---

## Admin Panel Features

### Gamification Settings
Configure all aspects of your loyalty program:
- **Point Value**: Set how much each point is worth
- **Earning Percentage**: Define points earned per purchase
- **Redemption Rules**: Set minimum and maximum redemption values
- **Bonus Points**: Configure signup, birthday, and referral bonuses
- **Expiration Settings**: Set point validity periods
- **Display Settings**: Customize points name and icon

### Customer Management
View and manage customer loyalty data:
- Individual point balances
- Transaction history
- Tier status
- Referral performance

### Analytics Dashboard
Track your loyalty program performance:
- Total points issued
- Points redeemed
- Active loyalty members
- Referral conversion rates

---

## Customer Experience

### Points Dashboard
Customers can view:
- Current points balance
- Points value in currency
- Transaction history
- Current tier status
- Available redemption amount
- Referral code and link

### Checkout Integration
During checkout, eligible customers see:
- Available points balance
- Maximum redeemable amount
- Point value of current order
- One-click redemption option

### Ways to Earn Page
Educate customers with a dedicated page showing:
- All point-earning opportunities
- Current tier benefits
- Referral program details
- Upcoming point expirations

---

## API Endpoints

### Configuration
- `GET /api/gamification/config` - Get loyalty program settings

### Customer Balance
- `GET /api/gamification/balance` - Get customer's points summary
- `GET /api/gamification/history` - Get transaction history

### Redemption
- `POST /api/gamification/check-redemption` - Check redemption eligibility
- `POST /api/gamification/redeem` - Apply points at checkout

### Referrals
- `GET /api/gamification/referral` - Get referral code and stats
- `POST /api/gamification/track-referral/{code}` - Track referral click

### Information
- `GET /api/gamification/how-to-earn` - Get all ways to earn points

---

## Configuration Options

### Point Value Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Point Value | Currency value per point | 0.01 |
| Currency | Points redemption currency | RON |
| Earn Percentage | % of order converted to points | 5% |
| Earn on Subtotal | Calculate on subtotal vs total | Yes |
| Min Order for Earning | Minimum order to earn points | 0 |

### Redemption Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Min Redeem Points | Minimum points to redeem | 100 |
| Max Redeem Percentage | Maximum % of order payable with points | 50% |
| Max Points Per Order | Maximum points per transaction | Unlimited |

### Bonus Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Signup Bonus | Points for new registration | 50 |
| Birthday Bonus | Points on customer's birthday | 100 |
| Referral Bonus (Referrer) | Points for successful referral | 200 |
| Referral Bonus (Referred) | Points for new referred customer | 100 |

### Expiration Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Points Expire Days | Days until points expire | Never |
| Expire on Inactivity | Expire points if account inactive | No |
| Inactivity Days | Days of inactivity before expiration | 365 |

### Display Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Points Name (Plural) | Display name for points | puncte |
| Points Name (Singular) | Singular form | punct |
| Icon | Display icon | star |

---

## Customer Tiers Example

| Tier | Min Points | Multiplier | Benefits |
|------|------------|------------|----------|
| Bronze | 0 | 1.0x | Standard earning |
| Silver | 1,000 | 1.25x | 25% bonus points |
| Gold | 5,000 | 1.5x | 50% bonus points |
| Platinum | 15,000 | 2.0x | Double points |

---

## Use Cases

### Event Venues
Reward repeat attendees and encourage them to bring friends through the referral program.

### Festivals
Build year-over-year loyalty with points that carry over between annual events.

### Theaters & Cinemas
Encourage frequent visits with points on every ticket and concession purchase.

### Conference Organizers
Reward early registration and referrals to drive attendance.

---

## Business Benefits

- **Increased Retention**: Loyal customers return more frequently
- **Higher Order Values**: Customers spend more to earn or redeem points
- **Word of Mouth**: Referral program drives organic growth
- **Customer Data**: Better understanding of purchase patterns
- **Competitive Edge**: Stand out with a modern loyalty program

---

## Getting Started

1. **Activate the Microservice**: Enable Gamification in your tenant settings
2. **Configure Point Values**: Set your earning rate and point value
3. **Set Redemption Rules**: Define minimum and maximum redemption limits
4. **Configure Bonuses**: Set up signup, birthday, and referral bonuses
5. **Create Tiers** (Optional): Define loyalty tiers with multipliers
6. **Launch**: Start rewarding your customers!

---

## Automated Maintenance

The system automatically handles:
- Daily point expiration processing
- Birthday bonus distribution
- Tier level calculations
- Referral tracking and attribution

Run maintenance manually:
```bash
php artisan gamification:maintenance
```

---

## Support

For assistance with the Gamification microservice, contact your platform administrator or refer to the technical documentation.
