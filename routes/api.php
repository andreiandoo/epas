<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Public\SeatingController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\TicketTemplateController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\EFacturaController;
use App\Http\Controllers\Api\WhatsAppController;

Route::get('/v1/public/events', function () {
    return response()->json([
        ['id'=>1,'slug'=>'concert-demo','title'=>'Concert Demo'],
        ['id'=>2,'slug'=>'premiera-odeon','title'=>'Premiera Odeon'],
    ]);
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

    // Webhook
    Route::post('/webhook', [WhatsAppController::class, 'webhook'])
        ->name('api.wa.webhook')
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
