# EPAS Platform - Complete Installation & Setup Guide

This document provides step-by-step instructions for installing and configuring the EPAS platform, including all microservices and payment integrations.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & NPM
- MySQL/PostgreSQL database
- Web server (Apache/Nginx)
- Git

## Initial Platform Setup

### 1. Clone and Install

```bash
# Clone the repository
git clone <repository-url> epas
cd epas

# Checkout the appropriate branch
git checkout <branch-name>

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install
```

### 2. Environment Configuration

```bash
# Copy the environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Database Configuration

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=epas
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Run Migrations

```bash
php artisan migrate
```

This will create all necessary tables including:
- Core tables (users, tenants, settings, etc.)
- Microservices tables
- Affiliate tracking tables
- Stripe configuration tables
- Invoice tables

### 5. Seed Initial Data

```bash
# Seed core settings
php artisan db:seed

# Seed specific seeders
php artisan db:seed --class=EventTypesAndGenresSeeder
php artisan db:seed --class=ArtistTypesAndGenresSeeder
php artisan db:seed --class=EmailTemplatesSeeder
php artisan db:seed --class=InvoiceEmailTemplatesSeeder
```

### 6. Build Frontend Assets

```bash
npm run build

# For development with hot reload
npm run dev
```

### 7. Create Admin User

```bash
php artisan make:filament-user
```

### 8. Start the Application

```bash
# Development
php artisan serve

# Production (configure your web server to point to /public)
```

Access the admin panel at: `http://your-domain.com/admin`

---

## Microservices Setup

### Affiliate Tracking & Commissions Microservice

#### 1. Run Affiliate Migrations

Already included in main migrations, but specifically creates:
- `affiliates` table
- `affiliate_links` table
- `affiliate_coupons` table
- `affiliate_conversions` table
- `affiliate_clicks` table

#### 2. Seed Affiliate Microservice

```bash
php artisan db:seed --class=AffiliateTrackingMicroserviceSeeder
```

This creates the "Affiliate Tracking & Commissions" microservice entry with:
- Name: Affiliate Tracking & Commissions
- Price: 10.00 EUR (one-time payment)
- All features configured

#### 3. Activate for a Tenant

In the admin panel:
1. Navigate to **Microservices**
2. See "Affiliate Tracking & Commissions" in the list
3. Go to **Tenants** and select a tenant
4. Attach the microservice to the tenant (this will be done via Stripe payment)

#### 4. Configure Affiliate Settings

For each tenant using the microservice, they can configure:
- Cookie name (default: `aff_ref`)
- Cookie duration (default: 90 days)
- Commission type (percent or fixed)
- Commission value
- Self-purchase guard (enable/disable)

#### 5. Testing Affiliate System

```bash
# Run affiliate tracking tests
php artisan test --filter=AffiliateTrackingTest
```

---

## Stripe Payment Integration Setup

### 1. Prerequisites

Stripe SDK is already in composer.json:
```json
"stripe/stripe-php": "^15.0"
```

Ensure it's installed:
```bash
composer install
```

### 2. Run Stripe Migrations

```bash
# Already included in main migrations
php artisan migrate
```

This adds:
- `stripe_mode` (test/live)
- `stripe_test_public_key`
- `stripe_test_secret_key`
- `stripe_live_public_key`
- `stripe_live_secret_key`
- `stripe_webhook_secret`
- `vat_enabled` (boolean)
- `vat_rate` (decimal, default 21%)

### 3. Seed Email Template for Purchase Confirmation

```bash
php artisan db:seed --class=MicroservicePurchaseEmailTemplateSeeder
```

This creates the editable email template in **Email Templates** admin section.

### 4. Configure Stripe in Admin Panel

#### Get Your Stripe API Keys

1. Log in to [Stripe Dashboard](https://dashboard.stripe.com)
2. Go to **Developers** → **API keys**
3. Copy your keys:
   - **Test Publishable Key**: `pk_test_...`
   - **Test Secret Key**: `sk_test_...`
   - **Live Publishable Key**: `pk_live_...`
   - **Live Secret Key**: `sk_live_...`

#### Add Keys to Application

1. Go to **Admin Panel** → **Settings** → **Connections**
2. In the **Stripe Payment Gateway** section:
   - Select **Stripe Mode** (Test or Live)
   - Enter **Test Mode Keys**
   - Enter **Live Mode Keys** (when ready for production)
3. Click **Test Stripe Connection** to verify
4. Configure **VAT Settings**:
   - Enable/Disable VAT
   - Set VAT Rate (default 21% for Romania)
5. Click **Save Configuration**

### 5. Configure Stripe Webhooks

#### Create Webhook Endpoint

1. Go to [Stripe Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. Enter endpoint URL: `https://yourdomain.com/webhooks/stripe`
4. Select events to listen for:
   - `checkout.session.completed`
   - `invoice.paid`
   - `customer.subscription.created`
   - `customer.subscription.deleted`
5. Click **Add endpoint**

#### Add Webhook Secret

1. After creating the endpoint, Stripe will show a **Signing secret**
2. Copy the secret (starts with `whsec_...`)
3. In Admin Panel → **Settings** → **Connections**
4. Paste it in **Webhook Signing Secret** field
5. Save configuration

### 6. Test the Payment Flow

#### Test Mode (Recommended First)

1. Ensure **Stripe Mode** is set to **Test**
2. Visit the marketplace: `/micro/marketplace?tenant_id=1`
3. Select a microservice
4. Click **Proceed to Checkout**
5. Use a Stripe test card:
   - **Success**: `4242 4242 4242 4242`
   - **Decline**: `4000 0000 0000 0002`
   - Any future expiry date (e.g., 12/34)
   - Any 3-digit CVC (e.g., 123)
6. Complete the payment
7. Verify:
   - Redirect to success page
   - Microservice activated for tenant
   - Invoice generated
   - Email sent with PDF

#### Test Webhooks Locally

For local development:

```bash
# Install Stripe CLI
https://stripe.com/docs/stripe-cli

# Login
stripe login

# Forward webhooks to local server
stripe listen --forward-to http://localhost:8000/webhooks/stripe

# Copy the webhook signing secret and add to Settings
```

### 7. Go Live

When ready for production:

1. Go to **Settings** → **Connections**
2. Switch **Stripe Mode** to **Live**
3. Ensure **Live Mode Keys** are configured
4. Update webhook endpoint in Stripe Dashboard to use production URL
5. Test with real payment (small amount first)

---

## Customization Options

### Editing Email Templates

1. Go to **Admin Panel** → **Email Templates**
2. Find **Microservice Purchase Confirmation**
3. Edit the HTML/Text content
4. Available placeholders:
   - `{{tenant_name}}` - Tenant name
   - `{{tenant_email}}` - Tenant email
   - `{{invoice_number}}` - Invoice number
   - `{{invoice_date}}` - Invoice date
   - `{{invoice_amount}}` - Total amount
   - `{{invoice_currency}}` - Currency code
   - `{{microservices_list}}` - List of purchased microservices
   - `{{microservices_count}}` - Number of microservices
   - `{{stripe_session_id}}` - Stripe session ID
   - `{{payment_date}}` - Payment date and time
   - `{{admin_url}}` - Admin panel URL

### Adjusting Microservice Pricing

1. Go to **Admin Panel** → **Microservices**
2. Click on a microservice to edit
3. Update the **Price** field
4. Select **Pricing Model**:
   - One-time Payment
   - Monthly Subscription
   - Yearly Subscription
5. Save changes

### Viewing Tenants Using Microservices

1. Go to **Admin Panel** → **Microservices**
2. Click on the **Active Tenants** count
3. View detailed list with activation dates

---

## Troubleshooting

### Migrations Fail

```bash
# Reset and re-run migrations (WARNING: destroys data)
php artisan migrate:fresh

# Or rollback and migrate again
php artisan migrate:rollback
php artisan migrate
```

### Stripe Connection Fails

**Check:**
- API keys are correct
- Mode (test/live) matches the keys
- No extra spaces in keys
- Internet connectivity

**Test manually:**
```bash
# In tinker
php artisan tinker

# Test Stripe
\Stripe\Stripe::setApiKey('your_secret_key');
$account = \Stripe\Account::retrieve();
echo $account->email;
```

### Webhooks Not Working

**Check:**
- Webhook URL is accessible publicly
- Webhook signing secret is correct
- Events are selected in Stripe Dashboard
- Review logs: `storage/logs/laravel.log`

**Debug webhooks:**
```bash
# View logs in real-time
tail -f storage/logs/laravel.log
```

### Emails Not Sending

**Check:**
- Mail configuration in `.env`
- SMTP credentials are correct
- Email template exists (run seeder if missing)

**Test email:**
```bash
php artisan tinker

Mail::raw('Test email', function($msg) {
    $msg->to('your@email.com')->subject('Test');
});
```

### Invoice Not Generated

**Check:**
- Webhook processed successfully (check logs)
- Invoice settings configured (prefix, series, etc.)
- Settings table has been seeded

### VAT Not Applying

**Check:**
- VAT is enabled in **Settings** → **Connections** → **VAT Configuration**
- VAT rate is set (default 21%)
- Clear cache: `php artisan config:clear`

---

## File Permissions

Ensure proper permissions:

```bash
# Storage and cache directories
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Or for development
chmod -R 777 storage bootstrap/cache
```

---

## Cron Jobs

Add to crontab for scheduled tasks:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Queue Workers

For processing background jobs:

```bash
# Development
php artisan queue:work

# Production (with supervisor)
# Create /etc/supervisor/conf.d/epas-worker.conf
[program:epas-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path-to-your-project/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path-to-your-project/storage/logs/worker.log

# Reload supervisor
supervisorctl reread
supervisorctl update
supervisorctl start epas-worker:*
```

---

## Security Checklist

- [ ] Change default `.env` values
- [ ] Use HTTPS in production
- [ ] Configure firewall
- [ ] Set proper file permissions
- [ ] Enable CSRF protection
- [ ] Use strong database passwords
- [ ] Regularly update dependencies
- [ ] Enable application logging
- [ ] Configure backup system
- [ ] Use encrypted database connections
- [ ] Set up monitoring/alerts

---

## Backup & Restore

### Database Backup

```bash
# Export database
mysqldump -u username -p epas > epas_backup.sql

# Import database
mysql -u username -p epas < epas_backup.sql
```

### Full Application Backup

```bash
# Backup files
tar -czf epas_files_backup.tar.gz /path/to/epas

# Exclude node_modules and vendor
tar -czf epas_backup.tar.gz --exclude='node_modules' --exclude='vendor' /path/to/epas
```

---

## Performance Optimization

### Cache Configuration

```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Clear all caches
php artisan optimize:clear
```

### Database Optimization

```bash
# Optimize tables
php artisan db:optimize

# Create database indexes
# Add indexes to frequently queried columns in migrations
```

---

## Tenant Payment Processor Integration

The platform allows tenants to integrate their own payment processors for processing customer payments on their websites.

### Supported Processors

- **Stripe**: Global payment processor
- **Netopia** (mobilPay): Romanian payment gateway
- **EuPlatesc**: Romanian/Eastern European processor
- **PayU**: Eastern European payment gateway

### Database Migrations

```bash
# Run tenant payment processor migrations
php artisan migrate

# These migrations add:
# - payment_processor field to tenants table
# - payment_processor_mode field (test/live)
# - tenant_payment_configs table for encrypted API credentials
```

### Configuration Flow

1. **During Registration**:
   - Tenants select their preferred payment processor in Step 2 (Company Information)
   - Payment processor cannot be changed after registration

2. **After Registration**:
   - Tenants navigate to: Admin Panel → Tenants → [Tenant] → Payment Config
   - Configure processor-specific API credentials
   - Set mode (test or live)
   - Test connection

3. **Set Up Webhooks**:
   - Copy webhook URL from admin panel
   - Add to payment processor's dashboard

### For Each Processor

**Stripe:**
- Get API keys from https://dashboard.stripe.com/apikeys
- Configure webhook at https://dashboard.stripe.com/webhooks

**Netopia:**
- Get credentials from https://netopia-payments.com
- Upload RSA key pair

**EuPlatesc:**
- Get Merchant ID and Secret Key from https://euplatesc.ro

**PayU:**
- Get Merchant Code and Secret Key from https://payu.ro

### Important Notes

- All API keys are encrypted in the database
- Always start in test mode
- Webhooks must use HTTPS in production
- See `TENANT_PAYMENT_PROCESSORS_README.md` for complete documentation

---

## Support & Documentation

- **Stripe Documentation**: https://stripe.com/docs
- **Affiliate Tracking README**: `AFFILIATE_TRACKING_README.md`
- **Stripe Integration README**: `STRIPE_INTEGRATION_README.md`
- **Tenant Payment Processors README**: `TENANT_PAYMENT_PROCESSORS_README.md`
- **Application Logs**: `storage/logs/laravel.log`

---

## Version Information

- **Laravel**: 12.x
- **PHP**: 8.2+
- **Filament**: 4.x
- **Stripe PHP SDK**: 15.x

---

## Quick Reference Commands

```bash
# Start development server
php artisan serve

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear caches
php artisan optimize:clear

# Run tests
php artisan test

# View logs
tail -f storage/logs/laravel.log

# Create admin user
php artisan make:filament-user

# Tinker (interactive shell)
php artisan tinker
```

---

## Summary Checklist

Complete Installation:
- [ ] Clone repository
- [ ] Install dependencies (composer & npm)
- [ ] Configure .env file
- [ ] Run migrations
- [ ] Seed database
- [ ] Build assets
- [ ] Create admin user

Stripe Setup:
- [ ] Get Stripe API keys
- [ ] Configure Stripe in admin panel
- [ ] Set up webhooks
- [ ] Configure VAT settings
- [ ] Test with test mode
- [ ] Seed purchase email template

Microservices:
- [ ] Seed affiliate microservice
- [ ] Test affiliate tracking
- [ ] Configure microservice pricing

Tenant Payment Processors:
- [ ] Run payment processor migrations
- [ ] Test tenant registration with processor selection
- [ ] Configure test processor credentials in admin
- [ ] Test payment creation and webhook handling
- [ ] Review TENANT_PAYMENT_PROCESSORS_README.md

Go Live:
- [ ] Switch Stripe to live mode
- [ ] Update webhook to production URL
- [ ] Test real payment
- [ ] Configure backups
- [ ] Set up monitoring

---

**Last Updated**: 2025-11-16
**Version**: 1.0.0
