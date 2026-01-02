# EPAS/Tixello - Complete Implementation Plan

## Overview

This document provides detailed technical specifications for implementing all identified feature gaps and improvements. Each section includes database migrations, models, services, controllers, API routes, and admin UI components.

**Estimated Files to Create/Modify:** 400+
**New Database Tables:** 45+
**New API Endpoints:** 120+

---

# PHASE 1: Critical Improvements (Priority: Immediate)

---

## 1.1 Email Verification in Onboarding

### Database Migrations

```php
// 2026_01_03_000001_add_email_verification_to_users.php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('email_verified_at')->nullable()->after('email');
    $table->string('email_verification_token', 64)->nullable();
    $table->timestamp('email_verification_sent_at')->nullable();
});

// 2026_01_03_000002_add_email_verification_to_customers.php
Schema::table('customers', function (Blueprint $table) {
    $table->timestamp('email_verified_at')->nullable();
    $table->string('email_verification_token', 64)->nullable();
    $table->timestamp('email_verification_sent_at')->nullable();
});
```

### Files to Create/Modify

| Type | Path | Action |
|------|------|--------|
| Service | `app/Services/Auth/EmailVerificationService.php` | Create |
| Controller | `app/Http/Controllers/Auth/EmailVerificationController.php` | Create |
| Mail | `app/Mail/VerifyEmailMail.php` | Create |
| View | `resources/views/emails/verify-email.blade.php` | Create |
| Controller | `app/Http/Controllers/OnboardingController.php` | Modify |
| Routes | `routes/web.php` | Modify |

### Service Implementation

```php
// app/Services/Auth/EmailVerificationService.php
class EmailVerificationService
{
    public function sendVerificationEmail(User|Customer $user): void;
    public function verify(string $token): bool;
    public function resendVerification(User|Customer $user): void;
    public function isTokenExpired(string $token): bool;
    public function revokeToken(User|Customer $user): void;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/verify-email/send` | Send verification email |
| POST | `/api/auth/verify-email/verify` | Verify email with token |
| POST | `/api/auth/verify-email/resend` | Resend verification email |
| GET | `/verify-email/{token}` | Web verification link |

### Configuration

```php
// config/auth.php additions
'verification' => [
    'expire' => 60, // minutes
    'throttle' => 6, // max attempts per hour
],
```

---

## 1.2 Password Reset Flow

### Database Migrations

```php
// 2026_01_03_000003_create_password_resets_table.php
Schema::create('password_resets', function (Blueprint $table) {
    $table->string('email')->index();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});

// For customers (separate table)
// 2026_01_03_000004_create_customer_password_resets_table.php
Schema::create('customer_password_resets', function (Blueprint $table) {
    $table->string('email')->index();
    $table->string('token');
    $table->foreignId('tenant_id')->constrained();
    $table->timestamp('created_at')->nullable();
});
```

### Files to Create/Modify

| Type | Path | Action |
|------|------|--------|
| Service | `app/Services/Auth/PasswordResetService.php` | Create |
| Controller | `app/Http/Controllers/Auth/PasswordResetController.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/PasswordResetController.php` | Create |
| Mail | `app/Mail/ResetPasswordMail.php` | Create |
| View | `resources/views/emails/reset-password.blade.php` | Create |
| View | `resources/views/auth/reset-password.blade.php` | Create |
| Request | `app/Http/Requests/Auth/ForgotPasswordRequest.php` | Create |
| Request | `app/Http/Requests/Auth/ResetPasswordRequest.php` | Create |

### Service Implementation

```php
// app/Services/Auth/PasswordResetService.php
class PasswordResetService
{
    public function sendResetLink(string $email, ?int $tenantId = null): void;
    public function validateToken(string $email, string $token): bool;
    public function reset(string $email, string $token, string $password): bool;
    public function deleteExpiredTokens(): int;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/forgot-password` | Request password reset |
| POST | `/api/auth/reset-password` | Reset password with token |
| POST | `/api/tenant-client/auth/forgot-password` | Customer password reset |
| POST | `/api/tenant-client/auth/reset-password` | Customer reset with token |

---

## 1.3 Payment Confirmation Emails

### Files to Create/Modify

| Type | Path | Action |
|------|------|--------|
| Mail | `app/Mail/PaymentConfirmationMail.php` | Create |
| Mail | `app/Mail/TenantPaymentNotificationMail.php` | Create |
| View | `resources/views/emails/payment-confirmation.blade.php` | Create |
| View | `resources/views/emails/tenant-payment-notification.blade.php` | Create |
| Listener | `app/Listeners/SendPaymentConfirmationEmail.php` | Create |
| Controller | `app/Http/Controllers/Webhooks/StripeWebhookController.php` | Modify |
| Service | `app/Services/OrderEmailService.php` | Modify |

### Event/Listener Registration

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    PaymentCaptured::class => [
        SendPaymentConfirmationEmail::class,
        GenerateEFactura::class, // existing
        SendTenantPaymentNotification::class,
    ],
];
```

### Email Templates Content

```php
// Payment Confirmation includes:
// - Order details (ID, date, items)
// - Payment amount and method
// - Ticket download links
// - Event details
// - QR codes for entry

// Tenant Notification includes:
// - Order summary
// - Commission breakdown
// - Customer info
// - Payment processor details
```

---

## 1.4 Dynamic Pricing Activation

### Files to Modify

| Type | Path | Action |
|------|------|--------|
| Config | `config/seating.php` | Modify (enable by default) |
| Service | `app/Services/Seating/DynamicPricingService.php` | Modify |
| Service | `app/Services/Seating/PricingStrategyService.php` | Create |
| Controller | `app/Http/Controllers/Api/DynamicPricingController.php` | Create |
| Resource | `app/Filament/Resources/DynamicPricingRuleResource.php` | Modify |
| Test | `tests/Feature/DynamicPricingTest.php` | Create |
| Test | `tests/Unit/DynamicPricingServiceTest.php` | Create |

### New Pricing Strategies

```php
// app/Services/Seating/Strategies/
├── DemandBasedStrategy.php      // Price based on sales velocity
├── TimeBasedStrategy.php        // Price based on time to event
├── InventoryBasedStrategy.php   // Price based on remaining inventory
├── CompetitorBasedStrategy.php  // Price based on competitor data
└── CombinedStrategy.php         // Weighted combination of above
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events/{id}/pricing/current` | Get current dynamic price |
| GET | `/api/events/{id}/pricing/history` | Price change history |
| POST | `/api/admin/pricing/simulate` | Simulate pricing changes |
| GET | `/api/admin/pricing/analytics` | Pricing performance analytics |

### Dashboard Widget

```php
// app/Filament/Widgets/DynamicPricingWidget.php
// Shows:
// - Current prices vs base prices
// - Revenue impact
// - Price change frequency
// - Demand indicators
```

---

## 1.5 WhatsApp Cloud API Completion

### Database Migrations

```php
// 2026_01_03_000005_enhance_whatsapp_messages.php
Schema::table('whatsapp_messages', function (Blueprint $table) {
    $table->string('provider')->default('twilio'); // twilio, cloud_api
    $table->json('interactive_data')->nullable();
    $table->string('media_url')->nullable();
    $table->string('media_type')->nullable();
    $table->json('webhook_data')->nullable();
});

// 2026_01_03_000006_create_whatsapp_conversations_table.php
Schema::create('whatsapp_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->string('phone_number');
    $table->enum('status', ['open', 'closed', 'expired']);
    $table->timestamp('expires_at');
    $table->timestamps();
});
```

### Files to Create/Modify

| Type | Path | Action |
|------|------|--------|
| Adapter | `app/Services/WhatsApp/Adapters/CloudApiAdapter.php` | Modify |
| Controller | `app/Http/Controllers/Webhooks/WhatsAppCloudWebhookController.php` | Modify |
| Service | `app/Services/WhatsApp/WhatsAppConversationService.php` | Create |
| Service | `app/Services/WhatsApp/WhatsAppMediaService.php` | Create |
| Service | `app/Services/WhatsApp/WhatsAppInteractiveService.php` | Create |
| Model | `app/Models/WhatsAppConversation.php` | Create |

### Cloud API Features to Complete

```php
// Interactive Message Types
class WhatsAppInteractiveService
{
    public function sendButtonMessage(string $phone, string $body, array $buttons): void;
    public function sendListMessage(string $phone, string $body, array $sections): void;
    public function sendProductMessage(string $phone, string $catalogId, string $productId): void;
}

// Media Handling
class WhatsAppMediaService
{
    public function sendImage(string $phone, string $imageUrl, ?string $caption = null): void;
    public function sendDocument(string $phone, string $documentUrl, string $filename): void;
    public function downloadMedia(string $mediaId): string;
}

// Webhook Signature Verification
class CloudApiAdapter
{
    public function verifyWebhookSignature(Request $request): bool;
    public function routeInboundMessage(array $payload): void;
}
```

---

# PHASE 2: High-Value New Features

---

## 2.1 Social Authentication (OAuth)

### Database Migrations

```php
// 2026_01_03_000010_create_social_accounts_table.php
Schema::create('social_accounts', function (Blueprint $table) {
    $table->id();
    $table->morphs('authenticatable'); // User or Customer
    $table->string('provider'); // google, facebook, apple
    $table->string('provider_id');
    $table->string('provider_token', 1000)->nullable();
    $table->string('provider_refresh_token', 1000)->nullable();
    $table->timestamp('token_expires_at')->nullable();
    $table->json('provider_data')->nullable();
    $table->timestamps();

    $table->unique(['provider', 'provider_id']);
});

// 2026_01_03_000011_add_social_fields_to_customers.php
Schema::table('customers', function (Blueprint $table) {
    $table->string('avatar_url')->nullable();
    $table->boolean('is_social_login')->default(false);
});
```

### Package Installation

```bash
composer require laravel/socialite
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/SocialAccount.php` | Create |
| Service | `app/Services/Auth/SocialAuthService.php` | Create |
| Controller | `app/Http/Controllers/Auth/SocialAuthController.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/SocialAuthController.php` | Create |
| Config | `config/services.php` | Modify |

### Service Implementation

```php
// app/Services/Auth/SocialAuthService.php
class SocialAuthService
{
    public function redirectToProvider(string $provider): RedirectResponse;
    public function handleProviderCallback(string $provider): User|Customer;
    public function linkAccount(User|Customer $user, string $provider): SocialAccount;
    public function unlinkAccount(User|Customer $user, string $provider): bool;
    public function findByProvider(string $provider, string $providerId): ?SocialAccount;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/auth/social/{provider}` | Initiate OAuth flow |
| GET | `/api/auth/social/{provider}/callback` | OAuth callback |
| POST | `/api/auth/social/link` | Link social account |
| DELETE | `/api/auth/social/{provider}` | Unlink social account |
| GET | `/api/tenant-client/auth/social/{provider}` | Customer OAuth |

### Configuration

```php
// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
'facebook' => [
    'client_id' => env('FACEBOOK_CLIENT_ID'),
    'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    'redirect' => env('FACEBOOK_REDIRECT_URI'),
],
'apple' => [
    'client_id' => env('APPLE_CLIENT_ID'),
    'client_secret' => env('APPLE_CLIENT_SECRET'),
    'redirect' => env('APPLE_REDIRECT_URI'),
],
```

---

## 2.2 Progressive Web App (PWA)

### Package Installation

```bash
npm install vite-plugin-pwa workbox-window
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Config | `vite.config.js` | Modify |
| Manifest | `public/manifest.json` | Create |
| Service Worker | `public/sw.js` | Create |
| View | `resources/views/layouts/pwa.blade.php` | Create |
| Controller | `app/Http/Controllers/PwaController.php` | Create |
| JS | `resources/js/pwa/install-prompt.js` | Create |
| JS | `resources/js/pwa/offline-handler.js` | Create |
| CSS | `resources/css/pwa/splash-screen.css` | Create |

### Manifest Configuration

```json
// public/manifest.json
{
    "name": "Tixello",
    "short_name": "Tixello",
    "description": "Event Ticketing Platform",
    "theme_color": "#6366f1",
    "background_color": "#ffffff",
    "display": "standalone",
    "orientation": "portrait",
    "scope": "/",
    "start_url": "/",
    "icons": [
        {"src": "/icons/icon-72x72.png", "sizes": "72x72", "type": "image/png"},
        {"src": "/icons/icon-96x96.png", "sizes": "96x96", "type": "image/png"},
        {"src": "/icons/icon-128x128.png", "sizes": "128x128", "type": "image/png"},
        {"src": "/icons/icon-144x144.png", "sizes": "144x144", "type": "image/png"},
        {"src": "/icons/icon-152x152.png", "sizes": "152x152", "type": "image/png"},
        {"src": "/icons/icon-192x192.png", "sizes": "192x192", "type": "image/png"},
        {"src": "/icons/icon-384x384.png", "sizes": "384x384", "type": "image/png"},
        {"src": "/icons/icon-512x512.png", "sizes": "512x512", "type": "image/png"}
    ]
}
```

### Service Worker Features

```javascript
// public/sw.js
// - Cache static assets
// - Cache API responses for offline tickets
// - Background sync for orders
// - Push notification handling
// - Offline fallback page
```

### Offline Ticket Access

```php
// app/Http/Controllers/Api/TenantClient/OfflineController.php
class OfflineController
{
    public function getOfflineTickets(Request $request); // Returns tickets for offline storage
    public function syncOfflineData(Request $request);   // Syncs data when back online
}
```

---

## 2.3 Virtual Queue System

### Database Migrations

```php
// 2026_01_03_000020_create_virtual_queue_tables.php
Schema::create('virtual_queues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained();
    $table->foreignId('tenant_id')->constrained();
    $table->timestamp('opens_at');
    $table->timestamp('closes_at')->nullable();
    $table->integer('max_concurrent_users')->default(100);
    $table->integer('session_timeout_minutes')->default(10);
    $table->boolean('is_active')->default(true);
    $table->json('settings')->nullable();
    $table->timestamps();
});

Schema::create('queue_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_queue_id')->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->string('session_id');
    $table->integer('position');
    $table->enum('status', ['waiting', 'active', 'completed', 'expired', 'abandoned']);
    $table->timestamp('entered_at');
    $table->timestamp('activated_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->json('device_fingerprint')->nullable();
    $table->timestamps();

    $table->index(['virtual_queue_id', 'status']);
    $table->index(['session_id']);
});

Schema::create('queue_analytics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_queue_id')->constrained();
    $table->integer('total_entries');
    $table->integer('peak_waiting');
    $table->integer('avg_wait_time_seconds');
    $table->integer('completed_sessions');
    $table->integer('abandoned_sessions');
    $table->integer('expired_sessions');
    $table->decimal('conversion_rate', 5, 2);
    $table->date('date');
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/VirtualQueue.php` | Create |
| Model | `app/Models/QueueEntry.php` | Create |
| Model | `app/Models/QueueAnalytics.php` | Create |
| Service | `app/Services/Queue/VirtualQueueService.php` | Create |
| Service | `app/Services/Queue/QueuePositionService.php` | Create |
| Service | `app/Services/Queue/QueueBotProtectionService.php` | Create |
| Controller | `app/Http/Controllers/Api/QueueController.php` | Create |
| Job | `app/Jobs/ProcessQueueAdvancement.php` | Create |
| Job | `app/Jobs/ExpireQueueSessions.php` | Create |
| Event | `app/Events/QueuePositionUpdated.php` | Create |
| Resource | `app/Filament/Resources/VirtualQueueResource.php` | Create |

### Service Implementation

```php
// app/Services/Queue/VirtualQueueService.php
class VirtualQueueService
{
    public function createQueue(Event $event, array $settings): VirtualQueue;
    public function joinQueue(VirtualQueue $queue, ?Customer $customer, string $sessionId): QueueEntry;
    public function getPosition(QueueEntry $entry): int;
    public function getEstimatedWaitTime(QueueEntry $entry): int; // seconds
    public function activateNext(VirtualQueue $queue): ?QueueEntry;
    public function completeSession(QueueEntry $entry): void;
    public function expireSession(QueueEntry $entry): void;
    public function getQueueStats(VirtualQueue $queue): array;
}

// app/Services/Queue/QueueBotProtectionService.php
class QueueBotProtectionService
{
    public function validateEntry(Request $request): bool;
    public function generateChallenge(): array; // CAPTCHA data
    public function verifyChallenge(string $token, string $response): bool;
    public function checkDeviceFingerprint(array $fingerprint): float; // risk score
    public function isRateLimited(string $ip): bool;
}
```

### WebSocket Events

```php
// Broadcasting for real-time updates
class QueuePositionUpdated implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('queue.' . $this->entry->session_id);
    }

    public function broadcastWith(): array
    {
        return [
            'position' => $this->position,
            'estimated_wait' => $this->estimatedWait,
            'status' => $this->status,
        ];
    }
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/queue/{eventId}/join` | Join the queue |
| GET | `/api/queue/{sessionId}/status` | Get queue position |
| POST | `/api/queue/{sessionId}/heartbeat` | Keep session alive |
| DELETE | `/api/queue/{sessionId}/leave` | Leave the queue |
| POST | `/api/queue/challenge/verify` | Verify CAPTCHA |

---

## 2.4 Calendar Integration

### Database Migrations

```php
// 2026_01_03_000025_create_calendar_sync_table.php
Schema::create('calendar_syncs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('tenant_id')->constrained();
    $table->string('provider'); // google, apple, outlook
    $table->string('external_calendar_id')->nullable();
    $table->string('access_token', 2000)->nullable();
    $table->string('refresh_token', 1000)->nullable();
    $table->timestamp('token_expires_at')->nullable();
    $table->boolean('auto_sync')->default(true);
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();
});

Schema::create('calendar_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('calendar_sync_id')->constrained();
    $table->foreignId('order_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->string('external_event_id')->nullable();
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/CalendarSync.php` | Create |
| Model | `app/Models/CalendarEvent.php` | Create |
| Service | `app/Services/Calendar/CalendarService.php` | Create |
| Service | `app/Services/Calendar/GoogleCalendarAdapter.php` | Create |
| Service | `app/Services/Calendar/AppleCalendarAdapter.php` | Create |
| Service | `app/Services/Calendar/OutlookCalendarAdapter.php` | Create |
| Service | `app/Services/Calendar/IcsGeneratorService.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/CalendarController.php` | Create |

### Service Implementation

```php
// app/Services/Calendar/CalendarService.php
class CalendarService
{
    public function connectCalendar(Customer $customer, string $provider, string $code): CalendarSync;
    public function disconnectCalendar(CalendarSync $sync): void;
    public function syncEvent(Order $order): void;
    public function removeEvent(Order $order): void;
    public function generateIcs(Event $event): string;
    public function generateIcsForOrder(Order $order): string;
}

// app/Services/Calendar/IcsGeneratorService.php
class IcsGeneratorService
{
    public function generate(Event $event, ?Order $order = null): string;
    public function generateMultiple(Collection $events): string;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tenant-client/calendar/providers` | List available providers |
| POST | `/api/tenant-client/calendar/connect/{provider}` | Connect calendar |
| DELETE | `/api/tenant-client/calendar/disconnect/{provider}` | Disconnect calendar |
| POST | `/api/tenant-client/calendar/sync/{orderId}` | Sync order to calendar |
| GET | `/api/tenant-client/calendar/ics/{eventId}` | Download .ics file |
| GET | `/api/tenant-client/orders/{id}/calendar.ics` | Download order .ics |

---

## 2.5 SMS Notifications

### Database Migrations

```php
// 2026_01_03_000030_create_sms_tables.php
Schema::create('sms_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->string('phone_number');
    $table->text('message');
    $table->enum('direction', ['outbound', 'inbound']);
    $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'received']);
    $table->string('provider_message_id')->nullable();
    $table->string('error_code')->nullable();
    $table->text('error_message')->nullable();
    $table->decimal('cost', 10, 4)->nullable();
    $table->string('currency', 3)->default('USD');
    $table->timestamps();

    $table->index(['tenant_id', 'status']);
    $table->index(['phone_number']);
});

Schema::create('sms_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('content');
    $table->json('variables')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('sms_opt_ins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->string('phone_number');
    $table->boolean('marketing_consent')->default(false);
    $table->boolean('transactional_consent')->default(true);
    $table->timestamp('consented_at');
    $table->timestamp('revoked_at')->nullable();
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/SmsMessage.php` | Create |
| Model | `app/Models/SmsTemplate.php` | Create |
| Model | `app/Models/SmsOptIn.php` | Create |
| Service | `app/Services/Sms/SmsService.php` | Create |
| Service | `app/Services/Sms/TwilioSmsAdapter.php` | Create |
| Controller | `app/Http/Controllers/Api/SmsController.php` | Create |
| Controller | `app/Http/Controllers/Webhooks/TwilioSmsWebhookController.php` | Create |
| Job | `app/Jobs/SendSmsMessage.php` | Create |
| Resource | `app/Filament/Resources/SmsTemplateResource.php` | Create |

### Service Implementation

```php
// app/Services/Sms/SmsService.php
class SmsService
{
    public function send(string $phoneNumber, string $message, ?int $tenantId = null): SmsMessage;
    public function sendTemplate(string $phoneNumber, string $templateSlug, array $variables): SmsMessage;
    public function sendBulk(array $phoneNumbers, string $message): Collection;
    public function checkOptIn(string $phoneNumber, int $tenantId, string $type = 'transactional'): bool;
    public function optIn(Customer $customer, string $phoneNumber, array $consents): SmsOptIn;
    public function optOut(Customer $customer): void;
}
```

### Integration with Existing Notifications

```php
// Modify app/Services/NotificationService.php
class NotificationService
{
    protected SmsService $smsService;

    public function sendOrderConfirmation(Order $order): void
    {
        // Existing email logic...

        // Add SMS
        if ($this->shouldSendSms($order->customer, 'order_confirmation')) {
            $this->smsService->sendTemplate(
                $order->customer->phone,
                'order_confirmation',
                ['order_id' => $order->id, 'event_name' => $order->event->name]
            );
        }
    }
}
```

---

# PHASE 3: Market Expansion Features

---

## 3.1 Multi-Language Support (i18n)

### Database Migrations

```php
// 2026_01_03_000040_create_translations_table.php
Schema::create('translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->nullable()->constrained();
    $table->string('locale', 5);
    $table->string('group'); // 'events', 'emails', 'ui', etc.
    $table->string('key');
    $table->text('value');
    $table->timestamps();

    $table->unique(['tenant_id', 'locale', 'group', 'key']);
});

// 2026_01_03_000041_add_translations_to_events.php
Schema::create('event_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->onDelete('cascade');
    $table->string('locale', 5);
    $table->string('name');
    $table->text('description')->nullable();
    $table->text('short_description')->nullable();
    $table->json('meta')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'locale']);
});

// Similar for other translatable models:
// - ticket_type_translations
// - email_template_translations
// - page_translations
// - blog_article_translations

// 2026_01_03_000042_add_locale_preferences.php
Schema::table('customers', function (Blueprint $table) {
    $table->string('preferred_locale', 5)->default('en');
});

Schema::table('tenants', function (Blueprint $table) {
    $table->string('default_locale', 5)->default('en');
    $table->json('supported_locales')->nullable(); // ['en', 'ro', 'es']
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/Translation.php` | Create |
| Trait | `app/Models/Traits/HasTranslations.php` | Create |
| Service | `app/Services/Localization/TranslationService.php` | Create |
| Service | `app/Services/Localization/LocaleDetectionService.php` | Create |
| Middleware | `app/Http/Middleware/SetLocale.php` | Create |
| Command | `app/Console/Commands/ExportTranslations.php` | Create |
| Command | `app/Console/Commands/ImportTranslations.php` | Create |
| Resource | `app/Filament/Resources/TranslationResource.php` | Create |

### Supported Locales

```php
// config/localization.php
return [
    'supported_locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'rtl' => false],
        'ro' => ['name' => 'Romanian', 'native' => 'Română', 'rtl' => false],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'rtl' => false],
        'fr' => ['name' => 'French', 'native' => 'Français', 'rtl' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'rtl' => false],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'rtl' => false],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'rtl' => false],
        'pl' => ['name' => 'Polish', 'native' => 'Polski', 'rtl' => false],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true],
        'he' => ['name' => 'Hebrew', 'native' => 'עברית', 'rtl' => true],
    ],
    'fallback_locale' => 'en',
    'detection_order' => ['query', 'session', 'cookie', 'header', 'browser'],
];
```

### Translation Trait

```php
// app/Models/Traits/HasTranslations.php
trait HasTranslations
{
    public function translations(): HasMany;
    public function translate(string $attribute, ?string $locale = null): ?string;
    public function setTranslation(string $attribute, string $locale, string $value): void;
    public function getTranslatedAttributes(): array;
}
```

### Language Files Structure

```
resources/lang/
├── en/
│   ├── events.php
│   ├── tickets.php
│   ├── checkout.php
│   ├── emails.php
│   ├── errors.php
│   └── ui.php
├── ro/
│   └── ... (same structure)
├── es/
│   └── ...
└── ... (other locales)
```

---

## 3.2 Virtual & Hybrid Events

### Database Migrations

```php
// 2026_01_03_000050_create_virtual_events_tables.php
Schema::create('virtual_event_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['virtual', 'hybrid']);
    $table->enum('stream_provider', ['youtube', 'vimeo', 'custom', 'zoom', 'teams']);
    $table->string('stream_url')->nullable();
    $table->string('stream_key')->nullable();
    $table->string('embed_code', 2000)->nullable();
    $table->boolean('chat_enabled')->default(true);
    $table->boolean('qa_enabled')->default(true);
    $table->boolean('polls_enabled')->default(true);
    $table->boolean('recording_enabled')->default(false);
    $table->integer('max_concurrent_viewers')->nullable();
    $table->json('settings')->nullable();
    $table->timestamps();
});

Schema::create('virtual_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained();
    $table->string('title');
    $table->text('description')->nullable();
    $table->timestamp('starts_at');
    $table->timestamp('ends_at');
    $table->string('stream_url')->nullable();
    $table->string('room_name')->nullable();
    $table->integer('max_attendees')->nullable();
    $table->json('speakers')->nullable();
    $table->boolean('is_breakout')->default(false);
    $table->foreignId('parent_session_id')->nullable()->constrained('virtual_sessions');
    $table->timestamps();
});

Schema::create('virtual_attendees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_session_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('order_id')->constrained();
    $table->timestamp('joined_at')->nullable();
    $table->timestamp('left_at')->nullable();
    $table->integer('watch_time_seconds')->default(0);
    $table->json('interactions')->nullable(); // polls answered, questions asked
    $table->timestamps();
});

Schema::create('virtual_chat_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_session_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->text('message');
    $table->boolean('is_pinned')->default(false);
    $table->boolean('is_deleted')->default(false);
    $table->foreignId('deleted_by')->nullable()->constrained('users');
    $table->timestamps();
});

Schema::create('virtual_polls', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_session_id')->constrained();
    $table->string('question');
    $table->json('options');
    $table->boolean('is_active')->default(false);
    $table->boolean('show_results')->default(false);
    $table->timestamps();
});

Schema::create('virtual_poll_responses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_poll_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->integer('selected_option');
    $table->timestamps();
});

Schema::create('virtual_qa_questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_session_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->text('question');
    $table->text('answer')->nullable();
    $table->foreignId('answered_by')->nullable()->constrained('users');
    $table->timestamp('answered_at')->nullable();
    $table->integer('upvotes')->default(0);
    $table->boolean('is_highlighted')->default(false);
    $table->timestamps();
});

Schema::create('virtual_recordings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_session_id')->constrained();
    $table->string('title');
    $table->string('url');
    $table->string('thumbnail_url')->nullable();
    $table->integer('duration_seconds');
    $table->bigInteger('file_size')->nullable();
    $table->enum('status', ['processing', 'ready', 'failed']);
    $table->boolean('is_public')->default(false);
    $table->timestamp('available_until')->nullable();
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/Virtual/VirtualEventConfig.php` | Create |
| Model | `app/Models/Virtual/VirtualSession.php` | Create |
| Model | `app/Models/Virtual/VirtualAttendee.php` | Create |
| Model | `app/Models/Virtual/VirtualChatMessage.php` | Create |
| Model | `app/Models/Virtual/VirtualPoll.php` | Create |
| Model | `app/Models/Virtual/VirtualPollResponse.php` | Create |
| Model | `app/Models/Virtual/VirtualQaQuestion.php` | Create |
| Model | `app/Models/Virtual/VirtualRecording.php` | Create |
| Service | `app/Services/Virtual/VirtualEventService.php` | Create |
| Service | `app/Services/Virtual/StreamingService.php` | Create |
| Service | `app/Services/Virtual/ChatService.php` | Create |
| Service | `app/Services/Virtual/PollService.php` | Create |
| Service | `app/Services/Virtual/RecordingService.php` | Create |
| Controller | `app/Http/Controllers/Api/VirtualEventController.php` | Create |
| Controller | `app/Http/Controllers/Api/VirtualChatController.php` | Create |
| Resource | `app/Filament/Resources/VirtualEventConfigResource.php` | Create |

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/virtual/events/{id}` | Get virtual event config |
| GET | `/api/virtual/events/{id}/stream` | Get stream URL (authenticated) |
| POST | `/api/virtual/sessions/{id}/join` | Join virtual session |
| POST | `/api/virtual/sessions/{id}/leave` | Leave session |
| GET | `/api/virtual/sessions/{id}/chat` | Get chat messages |
| POST | `/api/virtual/sessions/{id}/chat` | Send chat message |
| GET | `/api/virtual/sessions/{id}/polls` | Get active polls |
| POST | `/api/virtual/polls/{id}/respond` | Respond to poll |
| GET | `/api/virtual/sessions/{id}/qa` | Get Q&A questions |
| POST | `/api/virtual/sessions/{id}/qa` | Submit question |
| POST | `/api/virtual/qa/{id}/upvote` | Upvote question |
| GET | `/api/virtual/recordings/{id}` | Get recording |

---

## 3.3 Ticket Resale Marketplace

### Database Migrations

```php
// 2026_01_03_000060_create_resale_tables.php
Schema::create('resale_listings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('ticket_id')->constrained();
    $table->foreignId('seller_customer_id')->constrained('customers');
    $table->foreignId('event_id')->constrained();
    $table->decimal('asking_price', 10, 2);
    $table->decimal('original_price', 10, 2);
    $table->decimal('platform_fee', 10, 2)->default(0);
    $table->decimal('max_markup_percentage', 5, 2)->default(20);
    $table->enum('status', ['active', 'pending', 'sold', 'cancelled', 'expired']);
    $table->text('seller_notes')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['event_id', 'status']);
});

Schema::create('resale_offers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('resale_listing_id')->constrained();
    $table->foreignId('buyer_customer_id')->constrained('customers');
    $table->decimal('offer_amount', 10, 2);
    $table->enum('status', ['pending', 'accepted', 'rejected', 'expired', 'withdrawn']);
    $table->text('message')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();
});

Schema::create('resale_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('resale_listing_id')->constrained();
    $table->foreignId('resale_offer_id')->nullable()->constrained();
    $table->foreignId('buyer_customer_id')->constrained('customers');
    $table->foreignId('seller_customer_id')->constrained('customers');
    $table->decimal('sale_price', 10, 2);
    $table->decimal('platform_fee', 10, 2);
    $table->decimal('seller_payout', 10, 2);
    $table->string('payment_intent_id')->nullable();
    $table->enum('status', ['pending', 'completed', 'refunded', 'disputed']);
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});

Schema::create('resale_policies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('event_id')->nullable()->constrained();
    $table->boolean('resale_enabled')->default(true);
    $table->decimal('max_markup_percentage', 5, 2)->default(20);
    $table->decimal('platform_fee_percentage', 5, 2)->default(10);
    $table->boolean('offer_to_waitlist_first')->default(true);
    $table->integer('waitlist_priority_hours')->default(24);
    $table->boolean('require_identity_verification')->default(false);
    $table->timestamps();
});

Schema::create('seller_verifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->enum('status', ['pending', 'verified', 'rejected']);
    $table->string('document_type')->nullable();
    $table->string('document_url')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamp('verified_at')->nullable();
    $table->foreignId('verified_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/Resale/ResaleListing.php` | Create |
| Model | `app/Models/Resale/ResaleOffer.php` | Create |
| Model | `app/Models/Resale/ResaleTransaction.php` | Create |
| Model | `app/Models/Resale/ResalePolicy.php` | Create |
| Model | `app/Models/Resale/SellerVerification.php` | Create |
| Service | `app/Services/Resale/ResaleListingService.php` | Create |
| Service | `app/Services/Resale/ResaleOfferService.php` | Create |
| Service | `app/Services/Resale/ResaleTransactionService.php` | Create |
| Service | `app/Services/Resale/ResalePricingService.php` | Create |
| Service | `app/Services/Resale/TicketTransferService.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/ResaleController.php` | Create |
| Resource | `app/Filament/Resources/ResaleListingResource.php` | Create |
| Resource | `app/Filament/Resources/ResalePolicyResource.php` | Create |

### Service Implementation

```php
// app/Services/Resale/ResaleListingService.php
class ResaleListingService
{
    public function createListing(Ticket $ticket, Customer $seller, decimal $askingPrice): ResaleListing;
    public function validatePrice(Ticket $ticket, decimal $price): bool; // Check markup limits
    public function cancelListing(ResaleListing $listing): void;
    public function offerToWaitlist(ResaleListing $listing): void;
    public function getListingsForEvent(Event $event): Collection;
    public function searchListings(array $filters): LengthAwarePaginator;
}

// app/Services/Resale/ResaleTransactionService.php
class ResaleTransactionService
{
    public function purchase(ResaleListing $listing, Customer $buyer): ResaleTransaction;
    public function acceptOffer(ResaleOffer $offer): ResaleTransaction;
    public function processPayment(ResaleTransaction $transaction): void;
    public function transferTicket(ResaleTransaction $transaction): void;
    public function payoutSeller(ResaleTransaction $transaction): void;
    public function handleDispute(ResaleTransaction $transaction, string $reason): void;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tenant-client/resale/listings` | Browse listings |
| POST | `/api/tenant-client/resale/listings` | Create listing |
| GET | `/api/tenant-client/resale/listings/{id}` | Get listing details |
| DELETE | `/api/tenant-client/resale/listings/{id}` | Cancel listing |
| POST | `/api/tenant-client/resale/listings/{id}/purchase` | Buy directly |
| POST | `/api/tenant-client/resale/listings/{id}/offer` | Make offer |
| POST | `/api/tenant-client/resale/offers/{id}/accept` | Accept offer |
| POST | `/api/tenant-client/resale/offers/{id}/reject` | Reject offer |
| GET | `/api/tenant-client/resale/my-listings` | Seller's listings |
| GET | `/api/tenant-client/resale/my-purchases` | Buyer's purchases |

---

## 3.4 Subscription & Season Passes

### Database Migrations

```php
// 2026_01_03_000070_create_subscription_tables.php
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->enum('type', ['subscription', 'season_pass', 'membership']);
    $table->enum('billing_period', ['monthly', 'quarterly', 'yearly', 'one_time']);
    $table->decimal('price', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->integer('trial_days')->default(0);
    $table->json('benefits')->nullable();
    $table->json('included_events')->nullable(); // For season passes
    $table->integer('max_tickets_per_event')->nullable();
    $table->decimal('discount_percentage', 5, 2)->default(0);
    $table->boolean('early_access')->default(false);
    $table->integer('early_access_hours')->default(24);
    $table->boolean('is_active')->default(true);
    $table->integer('max_subscribers')->nullable();
    $table->timestamps();
});

Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_plan_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('tenant_id')->constrained();
    $table->string('stripe_subscription_id')->nullable();
    $table->enum('status', ['active', 'cancelled', 'past_due', 'paused', 'expired', 'trialing']);
    $table->timestamp('current_period_start');
    $table->timestamp('current_period_end');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamp('paused_at')->nullable();
    $table->timestamp('resume_at')->nullable();
    $table->boolean('cancel_at_period_end')->default(false);
    $table->json('metadata')->nullable();
    $table->timestamps();
});

Schema::create('subscription_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_id')->constrained();
    $table->string('stripe_item_id')->nullable();
    $table->string('stripe_price_id')->nullable();
    $table->integer('quantity')->default(1);
    $table->timestamps();
});

Schema::create('subscription_usages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->foreignId('order_id')->constrained();
    $table->integer('tickets_used');
    $table->timestamps();
});

Schema::create('membership_tiers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug');
    $table->integer('level'); // 1=Bronze, 2=Silver, 3=Gold, etc.
    $table->decimal('min_spend', 10, 2)->default(0);
    $table->integer('min_events')->default(0);
    $table->json('benefits')->nullable();
    $table->decimal('discount_percentage', 5, 2)->default(0);
    $table->integer('points_multiplier')->default(1);
    $table->boolean('early_access')->default(false);
    $table->boolean('priority_support')->default(false);
    $table->timestamps();
});

Schema::create('customer_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('membership_tier_id')->constrained();
    $table->foreignId('tenant_id')->constrained();
    $table->decimal('total_spend', 10, 2)->default(0);
    $table->integer('total_events')->default(0);
    $table->timestamp('tier_achieved_at');
    $table->timestamp('tier_expires_at')->nullable();
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/Subscription/SubscriptionPlan.php` | Create |
| Model | `app/Models/Subscription/Subscription.php` | Create |
| Model | `app/Models/Subscription/SubscriptionItem.php` | Create |
| Model | `app/Models/Subscription/SubscriptionUsage.php` | Create |
| Model | `app/Models/Subscription/MembershipTier.php` | Create |
| Model | `app/Models/Subscription/CustomerMembership.php` | Create |
| Service | `app/Services/Subscription/SubscriptionService.php` | Create |
| Service | `app/Services/Subscription/SeasonPassService.php` | Create |
| Service | `app/Services/Subscription/MembershipService.php` | Create |
| Service | `app/Services/Subscription/SubscriptionBillingService.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/SubscriptionController.php` | Create |
| Controller | `app/Http/Controllers/Webhooks/StripeSubscriptionWebhookController.php` | Create |
| Resource | `app/Filament/Resources/SubscriptionPlanResource.php` | Create |
| Resource | `app/Filament/Resources/MembershipTierResource.php` | Create |

### Service Implementation

```php
// app/Services/Subscription/SubscriptionService.php
class SubscriptionService
{
    public function subscribe(Customer $customer, SubscriptionPlan $plan): Subscription;
    public function cancel(Subscription $subscription, bool $immediately = false): void;
    public function pause(Subscription $subscription, ?Carbon $resumeAt = null): void;
    public function resume(Subscription $subscription): void;
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan): void;
    public function checkBenefitEligibility(Subscription $subscription, Event $event): bool;
    public function applySubscriptionDiscount(Order $order): void;
    public function recordUsage(Subscription $subscription, Order $order): void;
}

// app/Services/Subscription/MembershipService.php
class MembershipService
{
    public function calculateTier(Customer $customer): MembershipTier;
    public function upgradeTier(Customer $customer): void;
    public function getBenefits(Customer $customer): array;
    public function checkEarlyAccess(Customer $customer, Event $event): bool;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tenant-client/subscriptions/plans` | List available plans |
| POST | `/api/tenant-client/subscriptions` | Subscribe to plan |
| GET | `/api/tenant-client/subscriptions/my` | Get my subscriptions |
| POST | `/api/tenant-client/subscriptions/{id}/cancel` | Cancel subscription |
| POST | `/api/tenant-client/subscriptions/{id}/pause` | Pause subscription |
| POST | `/api/tenant-client/subscriptions/{id}/resume` | Resume subscription |
| GET | `/api/tenant-client/membership` | Get membership status |
| GET | `/api/tenant-client/membership/benefits` | Get current benefits |

---

## 3.5 Additional Payment Providers

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Service | `app/Services/PaymentProcessors/PayPalService.php` | Create |
| Service | `app/Services/PaymentProcessors/KlarnaService.php` | Create |
| Service | `app/Services/PaymentProcessors/CoinbaseService.php` | Create |
| Controller | `app/Http/Controllers/Webhooks/PayPalWebhookController.php` | Create |
| Controller | `app/Http/Controllers/Webhooks/KlarnaWebhookController.php` | Create |
| Controller | `app/Http/Controllers/Webhooks/CoinbaseWebhookController.php` | Create |
| Config | `config/payment-providers.php` | Modify |

### Payment Provider Interface

```php
// app/Contracts/PaymentProviderInterface.php
interface PaymentProviderInterface
{
    public function createPaymentIntent(Order $order): PaymentIntent;
    public function capturePayment(string $paymentIntentId): PaymentResult;
    public function refund(string $paymentIntentId, ?int $amount = null): RefundResult;
    public function handleWebhook(Request $request): WebhookResult;
    public function getPaymentMethods(Customer $customer): array;
    public function savePaymentMethod(Customer $customer, string $token): PaymentMethod;
}
```

### PayPal Implementation

```php
// app/Services/PaymentProcessors/PayPalService.php
class PayPalService implements PaymentProviderInterface
{
    public function createOrder(Order $order): array;
    public function captureOrder(string $orderId): array;
    public function refundCapture(string $captureId, ?float $amount = null): array;
    public function createSubscription(Customer $customer, SubscriptionPlan $plan): array;
    public function cancelSubscription(string $subscriptionId): void;
}
```

### Klarna (Buy Now, Pay Later)

```php
// app/Services/PaymentProcessors/KlarnaService.php
class KlarnaService implements PaymentProviderInterface
{
    public function createSession(Order $order): array;
    public function createOrder(string $authToken, Order $order): array;
    public function captureOrder(string $orderId): array;
    public function refundOrder(string $orderId, ?float $amount = null): array;
    public function getPaymentOptions(): array; // Pay in 4, Pay in 30, etc.
}
```

---

# PHASE 4: Innovation Features

---

## 4.1 AI-Powered Recommendations

### Database Migrations

```php
// 2026_01_03_000080_create_recommendation_tables.php
Schema::create('customer_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->json('preferred_categories')->nullable();
    $table->json('preferred_venues')->nullable();
    $table->json('preferred_artists')->nullable();
    $table->json('price_range')->nullable();
    $table->json('preferred_days')->nullable();
    $table->json('preferred_times')->nullable();
    $table->decimal('max_distance_km', 8, 2)->nullable();
    $table->timestamps();
});

Schema::create('customer_interactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->enum('interaction_type', ['view', 'wishlist', 'cart_add', 'purchase', 'share', 'review']);
    $table->integer('duration_seconds')->nullable(); // Time spent viewing
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['customer_id', 'interaction_type']);
});

Schema::create('event_similarities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained();
    $table->foreignId('similar_event_id')->constrained('events');
    $table->decimal('similarity_score', 5, 4); // 0.0000 to 1.0000
    $table->string('similarity_reason'); // category, artist, venue, audience
    $table->timestamps();

    $table->unique(['event_id', 'similar_event_id']);
});

Schema::create('recommendation_cache', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('tenant_id')->constrained();
    $table->json('recommended_event_ids');
    $table->json('scores');
    $table->string('algorithm_version');
    $table->timestamp('generated_at');
    $table->timestamp('expires_at');
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/AI/CustomerPreference.php` | Create |
| Model | `app/Models/AI/CustomerInteraction.php` | Create |
| Model | `app/Models/AI/EventSimilarity.php` | Create |
| Model | `app/Models/AI/RecommendationCache.php` | Create |
| Service | `app/Services/AI/RecommendationService.php` | Create |
| Service | `app/Services/AI/CollaborativeFilteringService.php` | Create |
| Service | `app/Services/AI/ContentBasedFilteringService.php` | Create |
| Service | `app/Services/AI/HybridRecommendationService.php` | Create |
| Service | `app/Services/AI/SimilarityCalculationService.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/RecommendationController.php` | Create |
| Job | `app/Jobs/GenerateRecommendations.php` | Create |
| Job | `app/Jobs/CalculateEventSimilarities.php` | Create |
| Command | `app/Console/Commands/RefreshRecommendations.php` | Create |

### Service Implementation

```php
// app/Services/AI/RecommendationService.php
class RecommendationService
{
    public function getRecommendations(Customer $customer, int $limit = 10): Collection;
    public function getSimilarEvents(Event $event, int $limit = 5): Collection;
    public function getPopularEvents(int $tenantId, int $limit = 10): Collection;
    public function getTrendingEvents(int $tenantId, int $limit = 10): Collection;
    public function trackInteraction(Customer $customer, Event $event, string $type): void;
    public function updatePreferences(Customer $customer, array $preferences): void;
    public function refreshRecommendations(Customer $customer): void;
}

// app/Services/AI/CollaborativeFilteringService.php
class CollaborativeFilteringService
{
    public function findSimilarUsers(Customer $customer): Collection;
    public function getEventsFromSimilarUsers(Customer $customer): Collection;
    public function calculateUserSimilarity(Customer $a, Customer $b): float;
}

// app/Services/AI/ContentBasedFilteringService.php
class ContentBasedFilteringService
{
    public function buildEventProfile(Event $event): array;
    public function buildUserProfile(Customer $customer): array;
    public function calculateMatchScore(array $userProfile, array $eventProfile): float;
    public function getMatchingEvents(Customer $customer): Collection;
}
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tenant-client/recommendations` | Get personalized recommendations |
| GET | `/api/tenant-client/recommendations/similar/{eventId}` | Similar events |
| GET | `/api/tenant-client/recommendations/popular` | Popular events |
| GET | `/api/tenant-client/recommendations/trending` | Trending events |
| POST | `/api/tenant-client/recommendations/track` | Track interaction |
| PUT | `/api/tenant-client/preferences` | Update preferences |

---

## 4.2 AI Chatbot

### Database Migrations

```php
// 2026_01_03_000090_create_chatbot_tables.php
Schema::create('chatbot_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->string('session_id');
    $table->enum('status', ['active', 'resolved', 'escalated', 'abandoned']);
    $table->string('language', 5)->default('en');
    $table->json('context')->nullable();
    $table->timestamp('last_message_at');
    $table->timestamps();
});

Schema::create('chatbot_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('chatbot_conversation_id')->constrained();
    $table->enum('role', ['user', 'assistant', 'system']);
    $table->text('content');
    $table->json('metadata')->nullable(); // intent, confidence, entities
    $table->string('intent')->nullable();
    $table->decimal('confidence', 5, 4)->nullable();
    $table->timestamps();
});

Schema::create('chatbot_intents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->nullable()->constrained();
    $table->string('name');
    $table->string('slug');
    $table->json('training_phrases');
    $table->json('responses');
    $table->string('action')->nullable(); // API action to trigger
    $table->json('required_entities')->nullable();
    $table->boolean('requires_handoff')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('chatbot_entities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->nullable()->constrained();
    $table->string('name');
    $table->string('type'); // event, date, ticket_type, order_id, etc.
    $table->json('synonyms')->nullable();
    $table->json('patterns')->nullable(); // Regex patterns
    $table->timestamps();
});

Schema::create('chatbot_handoffs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('chatbot_conversation_id')->constrained();
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->enum('status', ['pending', 'in_progress', 'resolved']);
    $table->string('reason');
    $table->text('notes')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/Chatbot/ChatbotConversation.php` | Create |
| Model | `app/Models/Chatbot/ChatbotMessage.php` | Create |
| Model | `app/Models/Chatbot/ChatbotIntent.php` | Create |
| Model | `app/Models/Chatbot/ChatbotEntity.php` | Create |
| Model | `app/Models/Chatbot/ChatbotHandoff.php` | Create |
| Service | `app/Services/Chatbot/ChatbotService.php` | Create |
| Service | `app/Services/Chatbot/IntentRecognitionService.php` | Create |
| Service | `app/Services/Chatbot/EntityExtractionService.php` | Create |
| Service | `app/Services/Chatbot/ResponseGenerationService.php` | Create |
| Service | `app/Services/Chatbot/ActionExecutionService.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/ChatbotController.php` | Create |
| Resource | `app/Filament/Resources/ChatbotIntentResource.php` | Create |
| Resource | `app/Filament/Resources/ChatbotConversationResource.php` | Create |

### Service Implementation

```php
// app/Services/Chatbot/ChatbotService.php
class ChatbotService
{
    public function processMessage(string $sessionId, string $message, ?Customer $customer): ChatbotMessage;
    public function startConversation(?Customer $customer): ChatbotConversation;
    public function endConversation(ChatbotConversation $conversation): void;
    public function escalateToHuman(ChatbotConversation $conversation, string $reason): ChatbotHandoff;
    public function getConversationHistory(string $sessionId): Collection;
}

// Built-in Intents
$defaultIntents = [
    'event.search' => 'Search for events',
    'event.details' => 'Get event information',
    'order.status' => 'Check order status',
    'order.refund' => 'Request refund',
    'ticket.download' => 'Download tickets',
    'payment.methods' => 'Available payment methods',
    'help.contact' => 'Contact support',
    'greeting' => 'Hello/Hi',
    'goodbye' => 'Bye/Thanks',
];
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/tenant-client/chatbot/message` | Send message |
| GET | `/api/tenant-client/chatbot/history` | Get conversation history |
| POST | `/api/tenant-client/chatbot/start` | Start new conversation |
| POST | `/api/tenant-client/chatbot/end` | End conversation |
| POST | `/api/tenant-client/chatbot/escalate` | Request human support |

---

## 4.3 Two-Factor Authentication

### Database Migrations

```php
// 2026_01_03_000100_add_two_factor_auth.php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('two_factor_enabled')->default(false);
    $table->string('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
});

Schema::table('customers', function (Blueprint $table) {
    $table->boolean('two_factor_enabled')->default(false);
    $table->string('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
});

Schema::create('two_factor_sessions', function (Blueprint $table) {
    $table->id();
    $table->morphs('authenticatable');
    $table->string('ip_address');
    $table->string('user_agent');
    $table->string('device_name')->nullable();
    $table->boolean('is_trusted')->default(false);
    $table->timestamp('trusted_until')->nullable();
    $table->timestamp('last_used_at');
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Service | `app/Services/Auth/TwoFactorAuthService.php` | Create |
| Controller | `app/Http/Controllers/Auth/TwoFactorController.php` | Create |
| Middleware | `app/Http/Middleware/RequireTwoFactor.php` | Create |
| View | `resources/views/auth/two-factor.blade.php` | Create |

### Service Implementation

```php
// app/Services/Auth/TwoFactorAuthService.php
class TwoFactorAuthService
{
    public function enable(User|Customer $user): array; // Returns secret and QR code
    public function confirm(User|Customer $user, string $code): bool;
    public function disable(User|Customer $user, string $code): bool;
    public function verify(User|Customer $user, string $code): bool;
    public function generateRecoveryCodes(User|Customer $user): array;
    public function useRecoveryCode(User|Customer $user, string $code): bool;
    public function trustDevice(User|Customer $user, Request $request, int $days = 30): void;
    public function isDeviceTrusted(User|Customer $user, Request $request): bool;
}
```

---

# PHASE 5: Improvements to Existing Features

---

## 5.1 Gamification Enhancements

### Database Migrations

```php
// 2026_01_03_000110_enhance_gamification.php
Schema::create('achievements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug');
    $table->text('description');
    $table->string('icon')->nullable();
    $table->string('badge_image')->nullable();
    $table->enum('type', ['one_time', 'progressive', 'streak']);
    $table->json('criteria'); // {"type": "purchase_count", "target": 10}
    $table->integer('points_reward')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('customer_achievements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('achievement_id')->constrained();
    $table->integer('progress')->default(0);
    $table->integer('target');
    $table->timestamp('unlocked_at')->nullable();
    $table->timestamps();

    $table->unique(['customer_id', 'achievement_id']);
});

Schema::create('leaderboards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug');
    $table->enum('period', ['all_time', 'yearly', 'monthly', 'weekly']);
    $table->enum('metric', ['points', 'events_attended', 'referrals', 'spend']);
    $table->boolean('is_public')->default(true);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('leaderboard_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leaderboard_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->integer('rank');
    $table->bigInteger('score');
    $table->date('period_start');
    $table->date('period_end');
    $table->timestamps();

    $table->unique(['leaderboard_id', 'customer_id', 'period_start']);
});

Schema::create('streaks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('tenant_id')->constrained();
    $table->string('type'); // event_attendance, purchase, login
    $table->integer('current_count')->default(0);
    $table->integer('longest_count')->default(0);
    $table->date('last_activity_date')->nullable();
    $table->timestamps();
});
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Model | `app/Models/Gamification/Achievement.php` | Create |
| Model | `app/Models/Gamification/CustomerAchievement.php` | Create |
| Model | `app/Models/Gamification/Leaderboard.php` | Create |
| Model | `app/Models/Gamification/LeaderboardEntry.php` | Create |
| Model | `app/Models/Gamification/Streak.php` | Create |
| Service | `app/Services/Gamification/AchievementService.php` | Create |
| Service | `app/Services/Gamification/LeaderboardService.php` | Create |
| Service | `app/Services/Gamification/StreakService.php` | Create |
| Controller | `app/Http/Controllers/Api/TenantClient/GamificationController.php` | Modify |
| Resource | `app/Filament/Resources/AchievementResource.php` | Create |
| Resource | `app/Filament/Resources/LeaderboardResource.php` | Create |
| Job | `app/Jobs/RefreshLeaderboards.php` | Create |

---

## 5.2 Testing Coverage Expansion

### Test Files to Create

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── EmailVerificationTest.php
│   │   ├── PasswordResetTest.php
│   │   ├── SocialAuthTest.php
│   │   └── TwoFactorAuthTest.php
│   ├── Checkout/
│   │   ├── CartTest.php
│   │   ├── CheckoutFlowTest.php
│   │   ├── PaymentTest.php
│   │   └── RefundTest.php
│   ├── Events/
│   │   ├── EventCreationTest.php
│   │   ├── EventListingTest.php
│   │   └── EventSearchTest.php
│   ├── Seating/
│   │   ├── SeatHoldTest.php
│   │   ├── SeatSelectionTest.php
│   │   └── DynamicPricingTest.php
│   ├── Queue/
│   │   ├── VirtualQueueTest.php
│   │   └── QueueBotProtectionTest.php
│   ├── Subscriptions/
│   │   ├── SubscriptionCreationTest.php
│   │   ├── SubscriptionBillingTest.php
│   │   └── MembershipTiersTest.php
│   ├── Resale/
│   │   ├── ResaleListingTest.php
│   │   ├── ResaleOfferTest.php
│   │   └── ResaleTransactionTest.php
│   └── Webhooks/
│       ├── StripeWebhookTest.php
│       ├── PayPalWebhookTest.php
│       └── WhatsAppWebhookTest.php
├── Unit/
│   ├── Services/
│   │   ├── EmailVerificationServiceTest.php
│   │   ├── PasswordResetServiceTest.php
│   │   ├── DynamicPricingServiceTest.php
│   │   ├── RecommendationServiceTest.php
│   │   ├── QueueServiceTest.php
│   │   └── ResaleServiceTest.php
│   └── Models/
│       ├── EventTest.php
│       ├── OrderTest.php
│       └── TicketTest.php
└── Integration/
    ├── StripeIntegrationTest.php
    ├── TwilioIntegrationTest.php
    └── CloudflareIntegrationTest.php
```

### Test Configuration

```php
// phpunit.xml additions
<testsuites>
    <testsuite name="Feature">
        <directory suffix="Test.php">./tests/Feature</directory>
    </testsuite>
    <testsuite name="Unit">
        <directory suffix="Test.php">./tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory suffix="Test.php">./tests/Integration</directory>
    </testsuite>
</testsuites>

<coverage>
    <include>
        <directory suffix=".php">./app</directory>
    </include>
    <report>
        <html outputDirectory="coverage-report"/>
        <text outputFile="coverage.txt"/>
    </report>
</coverage>
```

---

## 5.3 Real-Time Features (WebSockets)

### Package Installation

```bash
composer require laravel/reverb
```

### Configuration

```php
// config/reverb.php
return [
    'apps' => [
        [
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 8080),
            ],
        ],
    ],
];
```

### Channels to Create

```php
// routes/channels.php additions
Broadcast::channel('queue.{sessionId}', function ($user, $sessionId) {
    return true; // Public for queue updates
});

Broadcast::channel('event.{eventId}.seats', function ($user, $eventId) {
    return true; // Public for seat availability
});

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    return $user->orders()->where('id', $orderId)->exists();
});

Broadcast::channel('tenant.{tenantId}.orders', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    return true; // Validate conversation access
});
```

### Events to Create

```php
// app/Events/
├── SeatAvailabilityUpdated.php
├── OrderStatusChanged.php
├── QueuePositionUpdated.php
├── NewOrderReceived.php      // For tenant dashboard
├── ChatMessageReceived.php
├── PollResultsUpdated.php
└── AttendeeCountUpdated.php
```

---

## 5.4 API Documentation with OpenAPI

### Package Installation

```bash
composer require darkaonline/l5-swagger
```

### Files to Create

| Type | Path | Action |
|------|------|--------|
| Config | `config/l5-swagger.php` | Create |
| Controller | `app/Http/Controllers/Api/DocumentationController.php` | Create |
| Views | `resources/views/api-docs/*` | Create |

### Documentation Annotations

```php
/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="EPAS/Tixello API",
 *     description="Event Platform & Analytics System API Documentation"
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API Server"
 * )
 */

// Example endpoint documentation
/**
 * @OA\Get(
 *     path="/events",
 *     tags={"Events"},
 *     summary="List all events",
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/EventCollection")
 *     )
 * )
 */
```

---

# Summary: Files to Create/Modify

## Total New Files by Category

| Category | Count |
|----------|-------|
| Models | 55+ |
| Services | 45+ |
| Controllers | 35+ |
| Migrations | 25+ |
| Jobs | 15+ |
| Events | 12+ |
| Filament Resources | 20+ |
| Tests | 50+ |
| Views/Templates | 30+ |
| Configuration | 10+ |

## Database Tables Summary

| Phase | New Tables |
|-------|------------|
| Phase 1 | 5 |
| Phase 2 | 12 |
| Phase 3 | 25 |
| Phase 4 | 15 |
| Phase 5 | 8 |
| **Total** | **65+** |

## API Endpoints Summary

| Category | Endpoints |
|----------|-----------|
| Auth | 15 |
| Events | 20 |
| Queue | 8 |
| Calendar | 8 |
| SMS | 10 |
| Virtual Events | 20 |
| Resale | 15 |
| Subscriptions | 12 |
| AI/Recommendations | 10 |
| Chatbot | 8 |
| Gamification | 10 |
| **Total** | **136+** |

---

# Implementation Order

1. **Week 1-2**: Phase 1 Critical Improvements
   - Email verification
   - Password reset
   - Payment confirmation emails
   - Dynamic pricing activation
   - WhatsApp Cloud API

2. **Week 3-4**: Phase 2 Part 1
   - Social authentication
   - PWA setup
   - Calendar integration

3. **Week 5-6**: Phase 2 Part 2
   - Virtual queue system
   - SMS notifications

4. **Week 7-10**: Phase 3 Part 1
   - Multi-language support
   - Virtual events foundation

5. **Week 11-14**: Phase 3 Part 2
   - Ticket resale marketplace
   - Subscription system
   - Additional payment providers

6. **Week 15-18**: Phase 4
   - AI recommendations
   - AI chatbot
   - Two-factor authentication

7. **Week 19-22**: Phase 5
   - Gamification enhancements
   - Testing coverage
   - Real-time features
   - API documentation

---

*This implementation plan provides a complete roadmap for enhancing the EPAS/Tixello platform with all identified features and improvements.*
