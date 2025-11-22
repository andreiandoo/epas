<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Public\SeatingController;
use App\Http\Controllers\Api\PublicDataController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\TicketTemplateController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\EFacturaController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\MicroservicesController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\FeatureFlagController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TenantClient\DomainVerificationController;
use App\Http\Controllers\Api\TenantClient\PackageController;
use App\Http\Controllers\Api\TenantClient\BootstrapController;
use App\Http\Controllers\Api\TenantClient\EventsController;
use App\Http\Controllers\Api\TenantClient\AuthController;
use App\Http\Controllers\Api\TenantClient\CartController;
use App\Http\Controllers\Api\TenantClient\CheckoutController;
use App\Http\Controllers\Api\TenantClient\AdminController;
use App\Http\Controllers\Api\DocSearchController;

/*
|--------------------------------------------------------------------------
| Documentation Search API (Public - no auth required)
|--------------------------------------------------------------------------
*/

Route::prefix('docs')->group(function () {
    Route::get('/autocomplete', [DocSearchController::class, 'autocomplete'])->name('api.docs.autocomplete');
    Route::get('/search', [DocSearchController::class, 'search'])->name('api.docs.search');
    Route::get('/suggestions', [DocSearchController::class, 'suggestions'])->name('api.docs.suggestions');
});

/*
|--------------------------------------------------------------------------
| Public Data API Routes (for WordPress/external integrations)
|--------------------------------------------------------------------------
|
| Secured with API key authentication
|
*/

Route::prefix('v1/public')->middleware(['api.key'])->group(function () {
    Route::get('/stats', [PublicDataController::class, 'stats'])->name('api.public.stats');
    Route::get('/venues', [PublicDataController::class, 'venues'])->name('api.public.venues');
    Route::get('/venues/{slug}', [PublicDataController::class, 'venue'])->name('api.public.venue');
    Route::get('/artists', [PublicDataController::class, 'artists'])->name('api.public.artists');
    Route::get('/artists/{slug}', [PublicDataController::class, 'artist'])->name('api.public.artist');
    Route::get('/artists/{slug}/stats', [PublicDataController::class, 'artistStats'])->name('api.public.artist.stats');
    Route::get('/artists/{slug}/youtube', [PublicDataController::class, 'artistYoutubeStats'])->name('api.public.artist.youtube');
    Route::get('/artists/{slug}/spotify', [PublicDataController::class, 'artistSpotifyStats'])->name('api.public.artist.spotify');
    Route::get('/tenants', [PublicDataController::class, 'tenants'])->name('api.public.tenants');
    Route::get('/tenants/{slug}', [PublicDataController::class, 'tenant'])->name('api.public.tenant');
    Route::get('/events', [PublicDataController::class, 'events'])->name('api.public.events');
    Route::get('/events/{slug}', [PublicDataController::class, 'event'])->name('api.public.event');
});

/*
|--------------------------------------------------------------------------
| Seating API Routes - Public
|--------------------------------------------------------------------------
|
| Public-facing seating APIs with rate limiting and session management
|
*/

Route::prefix('public')->middleware(['seating.session'])->group(function () {

    // Get seating layout and metadata for an event
    Route::get('/events/{eventId}/seating', [SeatingController::class, 'getSeating'])
        ->name('api.seating.get-layout')
        ->middleware('throttle:seating_query');

    // Get seat availability and pricing
    Route::get('/events/{eventId}/seats', [SeatingController::class, 'getSeats'])
        ->name('api.seating.get-seats')
        ->middleware('throttle:seating_query');

    // Hold seats for session (10 minute TTL)
    Route::post('/seats/hold', [SeatingController::class, 'holdSeats'])
        ->name('api.seating.hold')
        ->middleware('throttle:seating_hold');

    // Release held seats
    Route::delete('/seats/hold', [SeatingController::class, 'releaseSeats'])
        ->name('api.seating.release')
        ->middleware('throttle:seating_release');

    // Confirm purchase (mark as sold)
    Route::post('/seats/confirm', [SeatingController::class, 'confirmPurchase'])
        ->name('api.seating.confirm')
        ->middleware('throttle:seating_confirm');

    // Get session's current holds
    Route::get('/seats/holds', [SeatingController::class, 'getSessionHolds'])
        ->name('api.seating.get-holds')
        ->middleware('throttle:seating_query');
});

/*
|--------------------------------------------------------------------------
| Affiliate Tracking API Routes
|--------------------------------------------------------------------------
|
| API endpoints for affiliate tracking and management
|
*/

Route::prefix('affiliates')->middleware(['throttle:api'])->group(function () {
    // Tenant admin endpoints
    Route::post('/', [AffiliateController::class, 'store'])
        ->name('api.affiliates.create');

    Route::get('/{id}/dashboard', [AffiliateController::class, 'dashboard'])
        ->name('api.affiliates.dashboard');

    Route::get('/{id}/export', [AffiliateController::class, 'export'])
        ->name('api.affiliates.export');

    // Affiliate self-service endpoint
    Route::get('/me', [AffiliateController::class, 'me'])
        ->name('api.affiliates.me');

    // Tracking endpoints
    Route::post('/track-click', [AffiliateController::class, 'trackClick'])
        ->name('api.affiliates.track-click');

    Route::post('/attribute-order', [AffiliateController::class, 'attributeOrder'])
        ->name('api.affiliates.attribute-order');

    Route::post('/confirm-order', [AffiliateController::class, 'confirmOrder'])
        ->name('api.affiliates.confirm-order');

    Route::post('/approve-order', [AffiliateController::class, 'approveOrder'])
        ->name('api.affiliates.approve-order');

    Route::post('/reverse-order', [AffiliateController::class, 'reverseOrder'])
        ->name('api.affiliates.reverse-order');
});

/*
|--------------------------------------------------------------------------
| Tracking & Pixels API Routes
|--------------------------------------------------------------------------
|
| API endpoints for tracking configuration and consent management
|
*/

Route::prefix('tracking')->middleware(['throttle:api'])->group(function () {
    // Get tracking configuration
    Route::get('/config', [TrackingController::class, 'getConfig'])
        ->name('api.tracking.config');

    // Consent management
    Route::get('/consent', [TrackingController::class, 'getConsent'])
        ->name('api.tracking.consent.get');

    Route::post('/consent', [TrackingController::class, 'updateConsent'])
        ->name('api.tracking.consent.update');

    Route::post('/consent/revoke', [TrackingController::class, 'revokeConsent'])
        ->name('api.tracking.consent.revoke');

    // Admin: Manage integrations
    Route::post('/integrations', [TrackingController::class, 'storeIntegration'])
        ->name('api.tracking.integrations.store');

    // Debug: Preview script injection
    Route::get('/debug/preview', [TrackingController::class, 'debugPreview'])
        ->name('api.tracking.debug.preview');
});

/*
|--------------------------------------------------------------------------
| Ticket Template API Routes
|--------------------------------------------------------------------------
|
| API endpoints for ticket template customizer (WYSIWYG editor)
|
*/

Route::prefix('tickets/templates')->middleware(['throttle:api'])->group(function () {
    // Get available variables and sample data
    Route::get('/variables', [TicketTemplateController::class, 'getVariables'])
        ->name('api.tickets.templates.variables');

    // Validate template JSON
    Route::post('/validate', [TicketTemplateController::class, 'validate'])
        ->name('api.tickets.templates.validate');

    // Generate preview image
    Route::post('/preview', [TicketTemplateController::class, 'preview'])
        ->name('api.tickets.templates.preview');

    // Get preset dimensions
    Route::get('/presets', [TicketTemplateController::class, 'getPresets'])
        ->name('api.tickets.templates.presets');

    // List templates for a tenant
    Route::get('/', [TicketTemplateController::class, 'index'])
        ->name('api.tickets.templates.index');

    // Create a new template
    Route::post('/', [TicketTemplateController::class, 'store'])
        ->name('api.tickets.templates.store');

    // Get a specific template
    Route::get('/{id}', [TicketTemplateController::class, 'show'])
        ->name('api.tickets.templates.show');

    // Update a template
    Route::put('/{id}', [TicketTemplateController::class, 'update'])
        ->name('api.tickets.templates.update');

    // Delete a template
    Route::delete('/{id}', [TicketTemplateController::class, 'destroy'])
        ->name('api.tickets.templates.destroy');

    // Set template as default
    Route::post('/{id}/set-default', [TicketTemplateController::class, 'setDefault'])
        ->name('api.tickets.templates.set-default');

    // Create a new version of a template
    Route::post('/{id}/create-version', [TicketTemplateController::class, 'createVersion'])
        ->name('api.tickets.templates.create-version');
});

/*
|--------------------------------------------------------------------------
| Invitations API Routes
|--------------------------------------------------------------------------
|
| API endpoints for invitation management (zero-value tickets)
|
*/

Route::prefix('inv')->middleware(['throttle:api'])->group(function () {
    // Batch management
    Route::post('/batch', [InviteController::class, 'createBatch'])
        ->name('api.inv.batch.create');

    Route::post('/batch/import', [InviteController::class, 'importRecipients'])
        ->name('api.inv.batch.import');

    Route::post('/batch/render', [InviteController::class, 'renderBatch'])
        ->name('api.inv.batch.render');

    Route::get('/batch/{id}/export', [InviteController::class, 'exportBatch'])
        ->name('api.inv.batch.export');

    Route::get('/batch/{id}/download-zip', [InviteController::class, 'downloadBatchZip'])
        ->name('api.inv.batch.download-zip');

    // Email sending
    Route::post('/send', [InviteController::class, 'sendEmails'])
        ->name('api.inv.send');

    // Individual invite management
    Route::get('/{id}', [InviteController::class, 'getInvite'])
        ->name('api.inv.get');

    Route::post('/{id}/void', [InviteController::class, 'voidInvite'])
        ->name('api.inv.void');

    Route::post('/{id}/resend', [InviteController::class, 'resend'])
        ->name('api.inv.resend');

    // Download (signed URL - public access)
    Route::get('/{id}/download', [InviteController::class, 'download'])
        ->name('api.inv.download')
        ->middleware(['signed']);

    // Tracking webhooks
    Route::post('/webhook/open', [InviteController::class, 'trackOpen'])
        ->name('api.inv.webhook.open');
});

/*
|--------------------------------------------------------------------------
| Ticket Insurance API Routes
|--------------------------------------------------------------------------
|
| API endpoints for ticket insurance (optional checkout add-on)
|
*/

Route::prefix('ti')->middleware(['throttle:api'])->group(function () {
    // Quote endpoint
    Route::get('/quote', [InsuranceController::class, 'quote'])
        ->name('api.ti.quote');

    // Policy issuance
    Route::post('/issue', [InsuranceController::class, 'issue'])
        ->name('api.ti.issue');

    // Policy sync with provider
    Route::post('/sync', [InsuranceController::class, 'sync'])
        ->name('api.ti.sync');

    // List policies
    Route::get('/policies', [InsuranceController::class, 'listPolicies'])
        ->name('api.ti.policies');

    // Statistics
    Route::get('/stats', [InsuranceController::class, 'stats'])
        ->name('api.ti.stats');

    // Void policy
    Route::post('/{id}/void', [InsuranceController::class, 'void'])
        ->name('api.ti.void');

    // Refund policy
    Route::post('/{id}/refund', [InsuranceController::class, 'refund'])
        ->name('api.ti.refund');
});

/*
|--------------------------------------------------------------------------
| Accounting Connectors API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('acc')->middleware(['throttle:api'])->group(function () {
    Route::post('/connect', [AccountingController::class, 'connect'])
        ->name('api.acc.connect');

    Route::post('/map', [AccountingController::class, 'map'])
        ->name('api.acc.map');

    Route::post('/issue', [AccountingController::class, 'issue'])
        ->name('api.acc.issue');

    Route::post('/credit', [AccountingController::class, 'credit'])
        ->name('api.acc.credit');
});

/*
|--------------------------------------------------------------------------
| eFactura (RO) API Routes
|--------------------------------------------------------------------------
|
| API endpoints for automatic submission of invoices to ANAF SPV
|
*/

Route::prefix('efactura')->middleware(['throttle:api'])->group(function () {
    // Queue invoice for submission
    Route::post('/submit', [EFacturaController::class, 'submit'])
        ->name('api.efactura.submit');

    // Manual retry
    Route::post('/retry', [EFacturaController::class, 'retry'])
        ->name('api.efactura.retry');

    // Poll status
    Route::post('/poll', [EFacturaController::class, 'poll'])
        ->name('api.efactura.poll');

    // Get status
    Route::get('/status/{queueId}', [EFacturaController::class, 'status'])
        ->name('api.efactura.status');

    // Download receipt
    Route::get('/download/{queueId}', [EFacturaController::class, 'download'])
        ->name('api.efactura.download');

    // Statistics
    Route::get('/stats/{tenantId}', [EFacturaController::class, 'stats'])
        ->name('api.efactura.stats');

    // Queue list
    Route::get('/queue/{tenantId}', [EFacturaController::class, 'queueList'])
        ->name('api.efactura.queue');
});

/*
|--------------------------------------------------------------------------
| WhatsApp Notifications API Routes
|--------------------------------------------------------------------------
|
| API endpoints for WhatsApp messaging: confirmations, reminders, promos
|
*/

Route::prefix('wa')->middleware(['throttle:api'])->group(function () {
    // Template management
    Route::post('/templates', [WhatsAppController::class, 'storeTemplate'])
        ->name('api.wa.templates.store');

    // Send messages
    Route::post('/send/confirm', [WhatsAppController::class, 'sendConfirmation'])
        ->name('api.wa.send.confirm');

    Route::post('/schedule/reminders', [WhatsAppController::class, 'scheduleReminders'])
        ->name('api.wa.schedule.reminders');

    Route::post('/send/promo', [WhatsAppController::class, 'sendPromo'])
        ->name('api.wa.send.promo');

    // Webhook (with signature verification)
    Route::post('/webhook', [WhatsAppController::class, 'webhook'])
        ->name('api.wa.webhook')
        ->middleware('webhook.verify:twilio')
        ->withoutMiddleware(['throttle:api']); // Webhooks should not be throttled

    // Opt-in management
    Route::post('/optin', [WhatsAppController::class, 'manageOptIn'])
        ->name('api.wa.optin');

    // Statistics and lists
    Route::get('/stats/{tenantId}', [WhatsAppController::class, 'stats'])
        ->name('api.wa.stats');

    Route::get('/messages/{tenantId}', [WhatsAppController::class, 'listMessages'])
        ->name('api.wa.messages');

    Route::get('/schedules/{tenantId}', [WhatsAppController::class, 'listSchedules'])
        ->name('api.wa.schedules');
});

/*
|--------------------------------------------------------------------------
| Microservices Management API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing microservices, webhooks, feature flags, and notifications
|
*/

Route::prefix('microservices')->middleware(['throttle:api'])->group(function () {
    // List all available microservices
    Route::get('/', [MicroservicesController::class, 'index'])
        ->name('api.microservices.index');
    
    // Get tenant's active microservices
    Route::get('/tenant/{tenantId}', [MicroservicesController::class, 'tenantMicroservices'])
        ->name('api.microservices.tenant');
    
    // Activate microservice
    Route::post('/activate', [MicroservicesController::class, 'activate'])
        ->name('api.microservices.activate');
    
    // Deactivate microservice
    Route::post('/tenant/{tenantId}/{microserviceSlug}/deactivate', [MicroservicesController::class, 'deactivate'])
        ->name('api.microservices.deactivate');
});

/*
|--------------------------------------------------------------------------
| Webhooks Management API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->middleware(['throttle:api'])->group(function () {
    Route::get('/{tenantId}', [WebhookController::class, 'index'])
        ->name('api.webhooks.index');
    
    Route::post('/{tenantId}', [WebhookController::class, 'store'])
        ->name('api.webhooks.store');
    
    Route::put('/{tenantId}/{webhookId}', [WebhookController::class, 'update'])
        ->name('api.webhooks.update');
    
    Route::delete('/{tenantId}/{webhookId}', [WebhookController::class, 'destroy'])
        ->name('api.webhooks.destroy');
    
    Route::get('/{tenantId}/{webhookId}/deliveries', [WebhookController::class, 'deliveries'])
        ->name('api.webhooks.deliveries');
    
    Route::post('/{tenantId}/deliveries/{deliveryId}/retry', [WebhookController::class, 'retryDelivery'])
        ->name('api.webhooks.retry');
});

/*
|--------------------------------------------------------------------------
| Feature Flags API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('feature-flags')->middleware(['throttle:api'])->group(function () {
    Route::get('/', [FeatureFlagController::class, 'index'])
        ->name('api.feature-flags.index');
    
    Route::post('/', [FeatureFlagController::class, 'store'])
        ->name('api.feature-flags.store');
    
    Route::put('/{featureKey}', [FeatureFlagController::class, 'update'])
        ->name('api.feature-flags.update');
    
    Route::post('/{featureKey}/enable', [FeatureFlagController::class, 'enable'])
        ->name('api.feature-flags.enable');
    
    Route::post('/{featureKey}/disable', [FeatureFlagController::class, 'disable'])
        ->name('api.feature-flags.disable');
    
    Route::get('/{featureKey}/check', [FeatureFlagController::class, 'check'])
        ->name('api.feature-flags.check');
    
    Route::post('/{featureKey}/tenant/{tenantId}/enable', [FeatureFlagController::class, 'enableForTenant'])
        ->name('api.feature-flags.enable-tenant');
    
    Route::post('/{featureKey}/tenant/{tenantId}/disable', [FeatureFlagController::class, 'disableForTenant'])
        ->name('api.feature-flags.disable-tenant');
});

/*
|--------------------------------------------------------------------------
| Notifications API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('notifications')->middleware(['throttle:api'])->group(function () {
    Route::get('/{tenantId}', [NotificationController::class, 'index'])
        ->name('api.notifications.index');
    
    Route::post('/{tenantId}/{notificationId}/read', [NotificationController::class, 'markAsRead'])
        ->name('api.notifications.mark-read');
    
    Route::post('/{tenantId}/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('api.notifications.mark-all-read');
    
    Route::delete('/{tenantId}/{notificationId}', [NotificationController::class, 'destroy'])
        ->name('api.notifications.destroy');
});

/*
|--------------------------------------------------------------------------
| Promo Codes API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing promo/voucher codes
|
*/

Route::prefix('promo-codes')->middleware(['throttle:api'])->group(function () {
    // Tenant admin endpoints
    Route::get('/{tenantId}', [App\Http\Controllers\Api\PromoCodeController::class, 'index'])
        ->name('api.promo-codes.index');

    Route::post('/{tenantId}', [App\Http\Controllers\Api\PromoCodeController::class, 'store'])
        ->name('api.promo-codes.store');

    Route::get('/{id}/show', [App\Http\Controllers\Api\PromoCodeController::class, 'show'])
        ->name('api.promo-codes.show');

    Route::put('/{id}', [App\Http\Controllers\Api\PromoCodeController::class, 'update'])
        ->name('api.promo-codes.update');

    Route::post('/{id}/deactivate', [App\Http\Controllers\Api\PromoCodeController::class, 'deactivate'])
        ->name('api.promo-codes.deactivate');

    Route::delete('/{id}', [App\Http\Controllers\Api\PromoCodeController::class, 'destroy'])
        ->name('api.promo-codes.destroy');

    Route::get('/{id}/stats', [App\Http\Controllers\Api\PromoCodeController::class, 'stats'])
        ->name('api.promo-codes.stats');

    Route::post('/{id}/duplicate', [App\Http\Controllers\Api\PromoCodeController::class, 'duplicate'])
        ->name('api.promo-codes.duplicate');

    // Usage history and analytics
    Route::get('/{id}/usage-history', [App\Http\Controllers\Api\PromoCodeController::class, 'usageHistory'])
        ->name('api.promo-codes.usage-history');

    Route::get('/{id}/fraud-detection', [App\Http\Controllers\Api\PromoCodeController::class, 'fraudDetection'])
        ->name('api.promo-codes.fraud-detection');

    Route::get('/{id}/usage-timeline', [App\Http\Controllers\Api\PromoCodeController::class, 'usageTimeline'])
        ->name('api.promo-codes.usage-timeline');

    // Export/Import
    Route::get('/{tenantId}/export', [App\Http\Controllers\Api\PromoCodeController::class, 'export'])
        ->name('api.promo-codes.export');

    Route::post('/{tenantId}/import', [App\Http\Controllers\Api\PromoCodeController::class, 'import'])
        ->name('api.promo-codes.import');

    Route::get('/{id}/export-usage', [App\Http\Controllers\Api\PromoCodeController::class, 'exportUsage'])
        ->name('api.promo-codes.export-usage');

    // Bulk operations
    Route::post('/{tenantId}/bulk-create', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkCreate'])
        ->name('api.promo-codes.bulk-create');

    Route::post('/bulk-activate', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkActivate'])
        ->name('api.promo-codes.bulk-activate');

    Route::post('/bulk-deactivate', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkDeactivate'])
        ->name('api.promo-codes.bulk-deactivate');

    Route::post('/bulk-delete', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkDelete'])
        ->name('api.promo-codes.bulk-delete');

    // Public endpoint for validating codes
    Route::post('/{tenantId}/validate', [App\Http\Controllers\Api\PromoCodeController::class, 'validate'])
        ->name('api.promo-codes.validate')
        ->withoutMiddleware(['throttle:api'])
        ->middleware('throttle:60,1'); // 60 requests per minute
});

/*
|--------------------------------------------------------------------------
| Health Check API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', [App\Http\Controllers\Api\HealthController::class, 'index'])
    ->name('api.health')
    ->withoutMiddleware(['throttle:api']);

Route::get('/ping', [App\Http\Controllers\Api\HealthController::class, 'ping'])
    ->name('api.ping')
    ->withoutMiddleware(['throttle:api']);

/*
|--------------------------------------------------------------------------
| Tenant API Routes (v1)
|--------------------------------------------------------------------------
|
| Authenticated tenant API endpoints with API key authentication
|
*/

Route::prefix('v1/tenant')->middleware(['api.tenant', 'throttle:api'])->group(function () {
    // API Key Management
    Route::prefix('api-keys')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'index'])
            ->name('api.v1.tenant.api-keys.index');

        Route::post('/', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'store'])
            ->name('api.v1.tenant.api-keys.store');

        Route::get('/scopes', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'scopes'])
            ->name('api.v1.tenant.api-keys.scopes');

        Route::get('/{keyId}', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'show'])
            ->name('api.v1.tenant.api-keys.show');

        Route::put('/{keyId}', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'update'])
            ->name('api.v1.tenant.api-keys.update');

        Route::delete('/{keyId}', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'destroy'])
            ->name('api.v1.tenant.api-keys.destroy');

        Route::get('/{keyId}/usage', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'usage'])
            ->name('api.v1.tenant.api-keys.usage');
    });

    // Audit Logs
    Route::prefix('audit')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\AuditController::class, 'index'])
            ->name('api.v1.tenant.audit.index');

        Route::get('/actions', [App\Http\Controllers\Api\V1\Tenant\AuditController::class, 'actions'])
            ->name('api.v1.tenant.audit.actions');
    });

    // Health Monitoring
    Route::prefix('health')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\HealthController::class, 'index'])
            ->name('api.v1.tenant.health.index');

        Route::get('/{service}', [App\Http\Controllers\Api\V1\Tenant\HealthController::class, 'show'])
            ->name('api.v1.tenant.health.show');
    });

    // Usage Metrics
    Route::prefix('metrics')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\MetricsController::class, 'index'])
            ->name('api.v1.tenant.metrics.index');

        Route::get('/summary', [App\Http\Controllers\Api\V1\Tenant\MetricsController::class, 'summary'])
            ->name('api.v1.tenant.metrics.summary');

        Route::get('/breakdown', [App\Http\Controllers\Api\V1\Tenant\MetricsController::class, 'breakdown'])
            ->name('api.v1.tenant.metrics.breakdown');
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\SubscriptionController::class, 'index'])
            ->name('api.v1.tenant.subscriptions.index');

        Route::get('/catalog', [App\Http\Controllers\Api\V1\Tenant\SubscriptionController::class, 'catalog'])
            ->name('api.v1.tenant.subscriptions.catalog');

        Route::get('/{subscriptionId}', [App\Http\Controllers\Api\V1\Tenant\SubscriptionController::class, 'show'])
            ->name('api.v1.tenant.subscriptions.show');
    });
});

/*
|--------------------------------------------------------------------------
| Domain Verification API Routes
|--------------------------------------------------------------------------
|
| API endpoints for domain ownership verification
|
*/

Route::prefix('tenant/domains')->middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    // Initiate domain verification
    Route::post('/{domain}/verify/initiate', [DomainVerificationController::class, 'initiate'])
        ->name('api.tenant.domains.verify.initiate');

    // Get verification status
    Route::get('/{domain}/verify/status', [DomainVerificationController::class, 'status'])
        ->name('api.tenant.domains.verify.status');

    // Trigger verification check
    Route::post('/{domain}/verify', [DomainVerificationController::class, 'verify'])
        ->name('api.tenant.domains.verify');

    // Get instructions for a verification method
    Route::get('/{domain}/verify/instructions', [DomainVerificationController::class, 'instructions'])
        ->name('api.tenant.domains.verify.instructions');

    // Schedule background verification
    Route::post('/{domain}/verify/schedule', [DomainVerificationController::class, 'scheduleVerification'])
        ->name('api.tenant.domains.verify.schedule');

    // Package management
    Route::post('/{domain}/package/generate', [PackageController::class, 'generate'])
        ->name('api.tenant.domains.package.generate');

    Route::get('/{domain}/package/status', [PackageController::class, 'status'])
        ->name('api.tenant.domains.package.status');

    Route::get('/{domain}/packages', [PackageController::class, 'list'])
        ->name('api.tenant.domains.packages');
});

/*
|--------------------------------------------------------------------------
| Package Download Routes (Public with hash verification)
|--------------------------------------------------------------------------
*/

Route::get('/tenant/package/{package}/download/{hash}', [PackageController::class, 'download'])
    ->name('api.tenant.package.download')
    ->middleware('throttle:60,1');

Route::get('/tenant/package/{package}/install-code', [PackageController::class, 'installCode'])
    ->name('api.tenant.package.install-code')
    ->middleware(['throttle:api', 'auth:sanctum']);

Route::post('/tenant/package/{package}/invalidate', [PackageController::class, 'invalidate'])
    ->name('api.tenant.package.invalidate')
    ->middleware(['throttle:api', 'auth:sanctum']);

/*
|--------------------------------------------------------------------------
| Tenant Client API Routes
|--------------------------------------------------------------------------
|
| API endpoints called by the tenant client package (deployed on tenant domains)
| These use custom middleware for request verification and signature checking
|
*/

Route::prefix('tenant-client')->middleware(['throttle:api', 'tenant.client'])->group(function () {
    // Bootstrap
    Route::get('/bootstrap', [BootstrapController::class, 'index'])
        ->name('api.tenant-client.bootstrap');

    // Auth (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->name('api.tenant-client.auth.register');
        Route::post('/login', [AuthController::class, 'login'])
            ->name('api.tenant-client.auth.login');
        Route::get('/me', [AuthController::class, 'me'])
            ->name('api.tenant-client.auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('api.tenant-client.auth.logout');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('api.tenant-client.auth.forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->name('api.tenant-client.auth.reset-password');
        Route::post('/super-login', [AuthController::class, 'superAdminLogin'])
            ->name('api.tenant-client.auth.super-login');
    });

    // Events (public)
    Route::prefix('events')->group(function () {
        Route::get('/', [EventsController::class, 'index'])
            ->name('api.tenant-client.events.index');
        Route::get('/{slug}', [EventsController::class, 'show'])
            ->name('api.tenant-client.events.show');
        Route::get('/{slug}/tickets', [EventsController::class, 'tickets'])
            ->name('api.tenant-client.events.tickets');
        Route::get('/{slug}/seating', [EventsController::class, 'seating'])
            ->name('api.tenant-client.events.seating');
    });

    // Cart
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index'])
            ->name('api.tenant-client.cart.index');
        Route::post('/items', [CartController::class, 'addItem'])
            ->name('api.tenant-client.cart.add');
        Route::put('/items/{itemId}', [CartController::class, 'updateItem'])
            ->name('api.tenant-client.cart.update');
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem'])
            ->name('api.tenant-client.cart.remove');
        Route::delete('/', [CartController::class, 'clear'])
            ->name('api.tenant-client.cart.clear');
        Route::post('/promo-code', [CartController::class, 'applyPromoCode'])
            ->name('api.tenant-client.cart.promo-code.apply');
        Route::delete('/promo-code', [CartController::class, 'removePromoCode'])
            ->name('api.tenant-client.cart.promo-code.remove');
    });

    // Checkout
    Route::prefix('checkout')->group(function () {
        Route::post('/init', [CheckoutController::class, 'init'])
            ->name('api.tenant-client.checkout.init');
        Route::post('/submit', [CheckoutController::class, 'submit'])
            ->name('api.tenant-client.checkout.submit');
        Route::get('/order/{orderId}', [CheckoutController::class, 'orderStatus'])
            ->name('api.tenant-client.checkout.order-status');
        Route::post('/insurance-quote', [CheckoutController::class, 'insuranceQuote'])
            ->name('api.tenant-client.checkout.insurance-quote');
    });

    // Admin (requires admin auth)
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])
            ->name('api.tenant-client.admin.dashboard');

        // Events management
        Route::get('/events', [AdminController::class, 'events'])
            ->name('api.tenant-client.admin.events');
        Route::post('/events', [AdminController::class, 'createEvent'])
            ->name('api.tenant-client.admin.events.create');
        Route::put('/events/{eventId}', [AdminController::class, 'updateEvent'])
            ->name('api.tenant-client.admin.events.update');

        // Orders
        Route::get('/orders', [AdminController::class, 'orders'])
            ->name('api.tenant-client.admin.orders');
        Route::get('/orders/{orderId}', [AdminController::class, 'orderDetail'])
            ->name('api.tenant-client.admin.orders.detail');

        // Customers
        Route::get('/customers', [AdminController::class, 'customers'])
            ->name('api.tenant-client.admin.customers');

        // Settings
        Route::get('/settings', [AdminController::class, 'settings'])
            ->name('api.tenant-client.admin.settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])
            ->name('api.tenant-client.admin.settings.update');
    });
});

// Payment callbacks (no tenant.client middleware - called by payment processors)
Route::post('/tenant-client/checkout/callback/{provider}', [CheckoutController::class, 'paymentCallback'])
    ->name('api.tenant-client.checkout.callback')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Administrative endpoints for system monitoring and management
| Note: Add authentication middleware as needed
|
*/

Route::prefix('admin')->middleware(['throttle:api', 'admin.auth'])->group(function () {
    // System Health
    Route::prefix('health')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\SystemHealthController::class, 'index'])
            ->name('api.admin.health.index');

        Route::get('/history', [App\Http\Controllers\Admin\SystemHealthController::class, 'history'])
            ->name('api.admin.health.history');

        Route::get('/{service}', [App\Http\Controllers\Admin\SystemHealthController::class, 'service'])
            ->name('api.admin.health.service');
    });

    // Alert Management
    Route::prefix('alerts')->group(function () {
        Route::get('/config', [App\Http\Controllers\Admin\AlertController::class, 'config'])
            ->name('api.admin.alerts.config');

        Route::post('/test', [App\Http\Controllers\Admin\AlertController::class, 'test'])
            ->name('api.admin.alerts.test');
    });

    // Audit Logs
    Route::prefix('audit')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AuditController::class, 'index'])
            ->name('api.admin.audit.index');

        Route::get('/stats', [App\Http\Controllers\Admin\AuditController::class, 'stats'])
            ->name('api.admin.audit.stats');

        Route::get('/export', [App\Http\Controllers\Admin\AuditController::class, 'export'])
            ->name('api.admin.audit.export');
    });

    // API Usage Monitoring
    Route::prefix('api-usage')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ApiUsageController::class, 'index'])
            ->name('api.admin.api-usage.index');

        Route::get('/by-tenant', [App\Http\Controllers\Admin\ApiUsageController::class, 'byTenant'])
            ->name('api.admin.api-usage.by-tenant');

        Route::get('/rate-limit-violations', [App\Http\Controllers\Admin\ApiUsageController::class, 'rateLimitViolations'])
            ->name('api.admin.api-usage.rate-limit-violations');

        Route::get('/{keyId}', [App\Http\Controllers\Admin\ApiUsageController::class, 'show'])
            ->name('api.admin.api-usage.show');
    });
});
