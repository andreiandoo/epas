# Microservice Branch Migration Plan

## Overview
This document outlines the segmented migration strategy for moving updates from `claude/microservice-purchase-activation-01PdLz8NHUKT6XKEvAPjUzER` to `core-main`.

**Total Changes:** 280 files, 47,072 lines added
**Source Branch:** claude/microservice-purchase-activation-01PdLz8NHUKT6XKEvAPjUzER
**Target Branch:** core-main

---

## Migration Packs (In Order)

### 🔵 Pack 1: Foundation & Core Infrastructure
**Branch:** `claude/migration-pack-01-foundation`
**Priority:** CRITICAL - Must be merged first
**Dependencies:** None

**Database Migrations:**
- `2025_11_16_100001_create_microservices_table.php`
- `2025_11_16_100002_create_tenant_microservices_table.php`
- `2025_11_16_110000_add_stripe_config_to_settings.php`
- `2025_11_16_120000_add_vat_settings_to_settings.php`
- `2025_11_16_200000_create_tenant_api_keys_table.php`
- `2025_11_16_200001_create_audit_logs_table.php`
- `2025_11_16_210000_create_tenant_notifications_table.php`
- `2025_11_16_220000_create_tenant_webhooks_table.php`
- `2025_11_16_230000_create_feature_flags_table.php`
- `2025_11_16_240000_create_microservice_metrics_table.php`
- `2025_11_16_200002_add_microservices_performance_indexes.php`

**Key Components:**
- Core microservices infrastructure
- Tenant API authentication system
- Audit logging service
- Webhook delivery system
- Feature flags service
- Health check service
- Metrics & monitoring
- Alert system
- Security middleware (admin auth, rate limiting, webhook verification)
- Microservices cache service

**Files (~80 files):**
- `app/Services/Api/TenantApiKeyService.php`
- `app/Services/Audit/AuditService.php`
- `app/Services/FeatureFlags/FeatureFlagService.php`
- `app/Services/Health/HealthCheckService.php`
- `app/Services/Metrics/MetricsService.php`
- `app/Services/Alerts/AlertService.php`
- `app/Services/Cache/MicroservicesCacheService.php`
- `app/Services/Webhooks/WebhookService.php`
- `app/Services/Webhooks/WebhookSignatureVerifier.php`
- `app/Services/NotificationService.php`
- `app/Http/Middleware/AuthenticateAdmin.php`
- `app/Http/Middleware/AuthenticateTenantApi.php`
- `app/Http/Middleware/AddRateLimitHeaders.php`
- `app/Http/Middleware/VerifyWebhookSignature.php`
- `app/Http/Controllers/Admin/*Controller.php`
- `app/Http/Controllers/Api/HealthController.php`
- `app/Http/Controllers/Api/FeatureFlagController.php`
- `app/Http/Controllers/Api/MicroservicesController.php`
- `app/Http/Controllers/Api/WebhookController.php`
- `app/Http/Controllers/Api/V1/Tenant/*Controller.php`
- `app/Http/Controllers/StatusController.php`
- `app/Http/Controllers/MicroserviceMarketplaceController.php`
- `app/Console/Commands/GenerateTenantApiKey.php`
- `app/Console/Commands/CheckSystemHealth.php`
- `app/Console/Commands/WarmMicroservicesCache.php`
- `app/Console/Commands/ViewAuditLogs.php`
- `app/Console/Commands/Microservices/*.php`
- `app/Models/TenantNotification.php`
- `app/Models/Setting.php`
- `app/Filament/Resources/Microservices/*`
- `app/Filament/Resources/Settings/Pages/ManageSettings.php`
- `app/Providers/MicroservicesServiceProvider.php`
- `config/microservices.php`
- `routes/api.php` (core API routes)
- `routes/web.php` (status, marketplace routes)
- `resources/views/status.blade.php`
- `resources/views/marketplace/*.blade.php`
- `public/docs/README.md`
- `public/docs/api-docs.json`
- `docs/SECURITY.md`
- Email templates for alerts

**Testing:**
- Run migrations
- Test API authentication
- Verify health checks work
- Test webhook signature verification
- Verify admin authentication

---

### 🟢 Pack 2: Payment Infrastructure
**Branch:** `claude/migration-pack-02-payments`
**Priority:** HIGH
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_130000_add_payment_processor_to_tenants.php`
- `2025_11_16_130001_create_tenant_payment_configs_table.php`

**Key Components:**
- Stripe integration service
- Payment processor factory (Stripe, PayU, Netopia, Euplatesc)
- Tenant payment configuration
- Payment webhooks

**Files (~25 files):**
- `app/Services/StripeService.php`
- `app/Services/PaymentProcessors/*`
- `app/Http/Controllers/StripeWebhookController.php`
- `app/Http/Controllers/TenantPaymentWebhookController.php`
- `app/Models/TenantPaymentConfig.php`
- `app/Filament/Resources/Tenants/Pages/ManagePaymentConfig.php`
- `app/Mail/MicroservicePurchaseConfirmation.php`
- `resources/views/emails/microservice-purchase-confirmation.blade.php`
- `resources/views/invoices/microservice-purchase.blade.php`
- `database/seeders/MicroservicePurchaseEmailTemplateSeeder.php`
- `.env.example` (payment-related vars)
- `STRIPE_INTEGRATION_README.md`
- `TENANT_PAYMENT_PROCESSORS_README.md`

**Testing:**
- Test Stripe webhook handling
- Verify payment processor factory
- Test tenant payment configuration

---

### 🟡 Pack 3: Affiliate Tracking System
**Branch:** `claude/migration-pack-03-affiliates`
**Priority:** MEDIUM
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_100000_create_tenant_configs_table.php`
- `2025_11_16_100001_create_affiliates_table.php`
- `2025_11_16_100002_create_affiliate_links_table.php`
- `2025_11_16_100003_create_affiliate_coupons_table.php`
- `2025_11_16_100004_create_affiliate_conversions_table.php`
- `2025_11_16_100005_create_affiliate_clicks_table.php`
- `2025_11_16_100006_add_affiliate_config_to_tenant_microservice.php`

**Key Components:**
- Affiliate tracking service
- Affiliate models (links, clicks, conversions, coupons)
- Affiliate admin interface

**Files (~15 files):**
- `app/Services/AffiliateTrackingService.php`
- `app/Models/Affiliate.php`
- `app/Models/AffiliateLink.php`
- `app/Models/AffiliateClick.php`
- `app/Models/AffiliateConversion.php`
- `app/Models/AffiliateCoupon.php`
- `app/Http/Controllers/Api/AffiliateController.php`
- `app/Filament/Resources/Affiliates/*`
- `resources/views/filament/resources/affiliates/pages/view-affiliate-stats.blade.php`
- `database/seeders/AffiliateTrackingMicroserviceSeeder.php`
- `tests/Feature/AffiliateTrackingTest.php`
- `AFFILIATE_TRACKING_README.md`

**Testing:**
- Test affiliate link creation
- Verify click tracking
- Test conversion tracking

---

### 🟣 Pack 4: Tracking & Pixels Manager
**Branch:** `claude/migration-pack-04-tracking`
**Priority:** MEDIUM
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_140000_create_tracking_integrations_table.php`

**Key Components:**
- Tracking integration service
- Support for GA4, GTM, Meta Pixel, TikTok Pixel
- GDPR consent management
- Script injection system

**Files (~20 files):**
- `app/Models/TrackingIntegration.php`
- `app/Services/Tracking/*`
- `app/Http/Controllers/Api/TrackingController.php`
- `app/Filament/Resources/TrackingIntegrations/*`
- `database/seeders/TrackingPixelsMicroserviceSeeder.php`
- `TRACKING_PIXELS_README.md`

**Testing:**
- Test tracking integration creation
- Verify script injection
- Test consent management

---

### 🔴 Pack 5: Ticket Customizer
**Branch:** `claude/migration-pack-05-ticket-customizer`
**Priority:** MEDIUM
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_150000_create_ticket_templates_table.php`

**Key Components:**
- Ticket template system
- Variable service for dynamic content
- Preview generator
- Template validator
- React component for frontend

**Files (~30 files):**
- `app/Models/TicketTemplate.php`
- `app/Services/TicketCustomizer/*`
- `app/Http/Controllers/Api/TicketTemplateController.php`
- `app/Filament/Resources/TicketTemplates/*`
- `resources/js/components/TicketCustomizer/*`
- `database/seeders/TicketCustomizerMicroserviceSeeder.php`
- `TICKET_CUSTOMIZER_README.md`

**Testing:**
- Test template creation
- Verify variable substitution
- Test preview generation

---

### 🟠 Pack 6: Invitations System
**Branch:** `claude/migration-pack-06-invitations`
**Priority:** MEDIUM
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_160000_create_inv_batches_table.php`
- `2025_11_16_160001_create_inv_invites_table.php`
- `2025_11_16_160002_create_inv_logs_table.php`

**Key Components:**
- Invitation batch management
- Email service for invitations
- Download service
- Tracking service
- Ticket issue adapter

**Files (~25 files):**
- `app/Models/Invite.php`
- `app/Models/InviteBatch.php`
- `app/Models/InviteLog.php`
- `app/Services/Invitations/*`
- `app/Http/Controllers/Api/InviteController.php`
- `app/Jobs/SendInvitationEmailJob.php`
- `resources/views/emails/invitation.blade.php`
- `database/seeders/InvitationsMicroserviceSeeder.php`
- `INVITATIONS_README.md`

**Testing:**
- Test batch creation
- Verify email sending
- Test invitation tracking

---

### 🟤 Pack 7: Ticket Insurance
**Branch:** `claude/migration-pack-07-insurance`
**Priority:** LOW
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_170000_create_ti_configs_table.php`
- `2025_11_16_170001_create_ti_policies_table.php`
- `2025_11_16_170002_create_ti_events_table.php`

**Key Components:**
- Insurance service
- Policy management
- Event tracking
- Mock insurer adapter

**Files (~15 files):**
- `app/Models/InsuranceConfig.php`
- `app/Models/InsurancePolicy.php`
- `app/Models/InsuranceEvent.php`
- `app/Services/Insurance/*`
- `app/Http/Controllers/Api/InsuranceController.php`
- `database/seeders/TicketInsuranceMicroserviceSeeder.php`
- `TICKET_INSURANCE_README.md`

**Testing:**
- Test policy creation
- Verify insurance calculations

---

### 🔵 Pack 8: Accounting Connectors
**Branch:** `claude/migration-pack-08-accounting`
**Priority:** LOW
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_180000_create_acc_connectors_table.php`
- `2025_11_16_180001_create_acc_mappings_table.php`
- `2025_11_16_180002_create_acc_jobs_table.php`

**Key Components:**
- Accounting service
- SmartBill adapter
- Mock adapter for testing

**Files (~15 files):**
- `app/Services/Accounting/*`
- `app/Http/Controllers/Api/AccountingController.php`
- `app/Filament/Resources/Settings/Pages/ManageConnections.php`
- `resources/views/filament/resources/settings/pages/manage-connections.blade.php`
- `database/seeders/AccountingConnectorsMicroserviceSeeder.php`
- `ACCOUNTING_CONNECTORS_README.md`

**Testing:**
- Test SmartBill integration
- Verify accounting sync

---

### 🟢 Pack 9: eFactura Romania
**Branch:** `claude/migration-pack-09-efactura`
**Priority:** LOW (Romania-specific)
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_190000_create_anaf_queue_table.php`

**Key Components:**
- ANAF integration
- eFactura service
- Queue management
- Mock ANAF adapter

**Files (~20 files):**
- `app/Models/AnafQueue.php`
- `app/Services/EFactura/*`
- `app/Http/Controllers/Api/EFacturaController.php`
- `app/Listeners/IssueInvoiceListener.php`
- `app/Listeners/SubmitEFacturaListener.php`
- `database/seeders/EFacturaMicroserviceSeeder.php`
- `tests/Feature/EFacturaMicroserviceTest.php`
- `EFACTURA_README.md`

**Testing:**
- Test ANAF adapter
- Verify invoice submission

---

### 🟡 Pack 10: WhatsApp Notifications
**Branch:** `claude/migration-pack-10-whatsapp`
**Priority:** MEDIUM
**Dependencies:** Pack 1

**Database Migrations:**
- `2025_11_16_200000_create_wa_optin_table.php`
- `2025_11_16_200001_create_wa_templates_table.php`
- `2025_11_16_200002_create_wa_messages_table.php`
- `2025_11_16_200003_create_wa_schedules_table.php`

**Key Components:**
- WhatsApp service
- Twilio adapter
- Template management
- Opt-in management
- Message scheduling

**Files (~25 files):**
- `app/Models/WhatsAppMessage.php`
- `app/Models/WhatsAppOptIn.php`
- `app/Models/WhatsAppSchedule.php`
- `app/Models/WhatsAppTemplate.php`
- `app/Services/WhatsApp/*`
- `app/Http/Controllers/Api/WhatsAppController.php`
- `database/seeders/WhatsAppMicroserviceSeeder.php`
- `tests/Feature/WhatsAppMicroserviceTest.php`
- `WHATSAPP_README.md`

**Testing:**
- Test Twilio integration
- Verify message sending
- Test opt-in management

---

### 🟣 Pack 11: Promo Codes & Vouchers
**Branch:** `claude/migration-pack-11-promocodes`
**Priority:** HIGH (if needed for sales)
**Dependencies:** Pack 1, requires orders table

**Database Migrations:**
- `2025_11_17_000000_create_promo_codes_table.php`
- `2025_11_17_000001_create_promo_code_usage_table.php`
- `2025_11_17_000002_add_promo_code_to_orders_table.php`
- `2025_11_17_000003_create_promo_code_metrics_table.php`
- `2025_11_17_000004_create_promo_code_templates_table.php`
- `2025_11_17_000005_add_categories_and_referral_to_promo_codes.php`

**Key Components:**
- Promo code service
- Validation & calculation
- Usage tracking & analytics
- Template system
- Stacking support
- Import/export functionality

**Files (~40 files):**
- `app/Services/PromoCodes/*`
- `app/Http/Controllers/Api/PromoCodeController.php`
- `app/Http/Middleware/AutoApplyBestPromoCode.php`
- `app/Console/Commands/CreatePromoCode.php`
- `app/Console/Commands/ListPromoCodes.php`
- `app/Console/Commands/ExpirePromoCodes.php`
- `app/Console/Commands/AlertExpiringPromoCodes.php`
- `app/Console/Commands/CleanupOldPromoCodeUsage.php`
- `app/Events/PromoCodes/*`
- `app/Listeners/PromoCodes/*`
- `database/seeders/FeatureFlagsSeeder.php`
- `docs/PROMO_CODES.md`

**Testing:**
- Test code validation
- Verify discount calculations
- Test stacking logic
- Verify usage tracking

---

## Shared/Global Updates

These files are modified across multiple packs and need careful attention:

- `app/Providers/AppServiceProvider.php` - Service bindings
- `app/Providers/EventServiceProvider.php` - Event listeners
- `app/Models/Tenant.php` - Tenant relationships
- `bootstrap/app.php` - Middleware registration
- `bootstrap/providers.php` - Provider registration
- `composer.json` - Dependencies
- `routes/api.php` - API routes (segmented per pack)
- `routes/console.php` - Console commands
- `routes/web.php` - Web routes
- `.env.example` - Environment variables
- `app/Support/helpers.php` - Helper functions

---

## Migration Order & Strategy

### Phase 1: Foundation (Week 1)
1. ✅ Pack 1: Foundation & Core Infrastructure
2. ✅ Pack 2: Payment Infrastructure

**Deploy to staging, run migrations, verify API authentication and health checks**

### Phase 2: Core Features (Week 2-3)
3. ✅ Pack 11: Promo Codes (if needed urgently)
4. ✅ Pack 3: Affiliate Tracking
5. ✅ Pack 4: Tracking & Pixels

**Deploy each pack individually, test thoroughly**

### Phase 3: Microservices (Week 3-4)
6. ✅ Pack 5: Ticket Customizer
7. ✅ Pack 6: Invitations
8. ✅ Pack 10: WhatsApp Notifications

**Deploy in parallel if possible, independent services**

### Phase 4: Optional Features (Week 4-5)
9. ✅ Pack 7: Ticket Insurance
10. ✅ Pack 8: Accounting Connectors
11. ✅ Pack 9: eFactura (Romania only)

**Deploy as needed based on customer requirements**

---

## Pre-Migration Checklist

Before merging each pack to `core-main`:

- [ ] Review all migration files for conflicts
- [ ] Check for table/column name collisions
- [ ] Verify foreign key constraints
- [ ] Test migrations on clean database
- [ ] Run automated tests
- [ ] Manual QA testing
- [ ] Review security implications
- [ ] Update documentation
- [ ] Prepare rollback plan
- [ ] Notify team of deployment

---

## Post-Migration Tasks

After each pack is merged:

1. **Database:**
   - Run migrations: `php artisan migrate`
   - Run seeders if applicable
   - Verify data integrity

2. **Cache:**
   - Clear application cache: `php artisan cache:clear`
   - Clear config cache: `php artisan config:clear`
   - Clear route cache: `php artisan route:clear`
   - Warm microservices cache: `php artisan microservices:warm-cache`

3. **Testing:**
   - Run test suite: `php artisan test`
   - Manual smoke tests
   - API endpoint testing

4. **Monitoring:**
   - Check system health: `php artisan system:health-check`
   - Monitor logs for errors
   - Verify metrics collection

---

## Rollback Strategy

If issues arise after merging a pack:

1. **Immediate:** Revert the merge commit
2. **Database:** Run `php artisan migrate:rollback --step=X` (X = number of migrations in pack)
3. **Cache:** Clear all caches
4. **Investigation:** Review logs, identify issue
5. **Fix:** Address issue in migration branch
6. **Retry:** Test thoroughly before re-merging

---

## Notes

- Each pack is **independent** after Pack 1
- Packs 3-10 can be merged in **any order** after packs 1-2
- Pack 11 requires the **orders table** to exist
- All packs include comprehensive **README files** and **documentation**
- Most packs include **test files** for verification
- **Mock adapters** are provided for all external services
- Consider **feature flags** to gradually enable functionality

---

## Contact & Support

For questions about specific packs or migration issues, refer to:
- Individual README files in each pack
- `docs/SECURITY.md` for security considerations
- `public/docs/README.md` for API documentation
- `IMPLEMENTATION_GUIDE.md` and `INSTALLATION_GUIDE.md` on the source branch

---

**Generated:** 2025-11-19
**Source Branch:** claude/microservice-purchase-activation-01PdLz8NHUKT6XKEvAPjUzER
**Target Branch:** core-main
