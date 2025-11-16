<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\ArtistPublicController;
use App\Http\Controllers\Public\VenueController as PublicVenueController;
use App\Http\Controllers\Public\LocationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\MicroserviceMarketplaceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TenantPaymentWebhookController;
use App\Http\Controllers\StatusController;

Route::pattern('locale', 'en|ro|de|fr|es');

/*
|--------------------------------------------------------------------------
| Microservices Marketplace & Stripe Routes
|--------------------------------------------------------------------------
*/

// Stripe Webhook (must be outside CSRF protection)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Tenant Payment Processor Webhooks
Route::post('/webhooks/tenant-payment/{tenant}/{processor}', [TenantPaymentWebhookController::class, 'handle'])
    ->name('webhooks.tenant-payment')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Microservices Marketplace Routes
Route::prefix('micro')->middleware(['web'])->group(function () {
    Route::get('/marketplace', [MicroserviceMarketplaceController::class, 'index'])
        ->name('micro.marketplace');

    Route::post('/checkout', [MicroserviceMarketplaceController::class, 'checkout'])
        ->name('micro.checkout');

    Route::get('/payment/success', [MicroserviceMarketplaceController::class, 'success'])
        ->name('micro.payment.success');

    Route::get('/payment/cancel', [MicroserviceMarketplaceController::class, 'cancel'])
        ->name('micro.payment.cancel');
});

// Onboarding routes (no locale prefix needed for registration)
Route::get('/register', [OnboardingController::class, 'index'])->name('onboarding.index');
Route::post('/register/step-1', [OnboardingController::class, 'storeStepOne'])->name('onboarding.step1');
Route::post('/register/step-2', [OnboardingController::class, 'storeStepTwo'])->name('onboarding.step2');
Route::post('/register/step-3', [OnboardingController::class, 'storeStepThree'])->name('onboarding.step3');
Route::post('/register/step-4', [OnboardingController::class, 'storeStepFour'])->name('onboarding.step4');
Route::post('/register/lookup-cui', [OnboardingController::class, 'lookupCui'])->name('onboarding.lookup-cui');
Route::get('/register/api/cities/{country}/{state}', [OnboardingController::class, 'getCities'])->name('onboarding.cities');
Route::get('/register/verify/{token}', [OnboardingController::class, 'verify'])->name('onboarding.verify');

// Admin Domain Management Routes (protected by Filament auth)
Route::middleware(['web'])->prefix('admin')->group(function () {
    Route::post('/tenants/{tenantId}/domains', [DomainController::class, 'store'])->name('admin.tenants.domains.store');
    Route::post('/domains/{domainId}/toggle-active', [DomainController::class, 'toggleActive'])->name('admin.domains.toggle-active');
    Route::get('/tenants/{tenantId}/domains/{domain}/login-as-admin', [DomainController::class, 'loginAsAdmin'])->name('tenant.login-as-admin');
});

// Define a helper to register public routes
$registerPublicRoutes = function ($prefix = '') {
    $groupOptions = ['middleware' => 'set.locale'];
    if ($prefix) {
        $groupOptions['prefix'] = $prefix;
    }

    Route::group($groupOptions, function () use ($prefix) {
        Route::get('/', [\App\Http\Controllers\Public\HomeController::class, 'index'])->name($prefix ? '' : 'public.home');
        Route::get('/about', [\App\Http\Controllers\Public\PageController::class, 'about'])->name($prefix ? '' : 'public.about');
        Route::get('/contact', [\App\Http\Controllers\Public\PageController::class, 'contact'])->name($prefix ? '' : 'public.contact');

        Route::get('/events', [\App\Http\Controllers\Public\EventController::class, 'index'])->name($prefix ? '' : 'public.events.index');

        Route::get('/venues', [PublicVenueController::class, 'index'])->name($prefix ? '' : 'public.venues.index');
        Route::get('/venue/{venue}', [PublicVenueController::class, 'show'])->name($prefix ? '' : 'public.venues.show');

        Route::get('/compare/{country}/{slug}', [\App\Http\Controllers\Public\CompareController::class, 'show'])
            ->where('country', '[a-z]{2}')
            ->where('slug', '[a-z0-9\-]+')
            ->name($prefix ? '' : 'public.compare');

        Route::get('/artists', [ArtistPublicController::class, 'index'])->name($prefix ? '' : 'public.artists.index');
        Route::get('/artist/{slug}', [ArtistPublicController::class, 'show'])->name($prefix ? '' : 'public.artist.show');

        // Location API endpoints for filters
        Route::get('/api/countries', [LocationController::class, 'countries'])->name($prefix ? '' : 'public.api.countries');
        Route::get('/api/states/{country}', [LocationController::class, 'states'])->name($prefix ? '' : 'public.api.states');
        Route::get('/api/cities/{country}/{state}', [LocationController::class, 'cities'])->name($prefix ? '' : 'public.api.cities');

        // Taxonomy API endpoints
        Route::get('/api/event-genres/{typeSlug?}', [LocationController::class, 'eventGenresByType'])->name($prefix ? '' : 'public.api.event-genres');
    });
};

// Register routes WITHOUT locale prefix (e.g., /artist/slug)
$registerPublicRoutes('');

// Register routes WITH locale prefix (e.g., /en/artist/slug)
foreach (['en', 'ro', 'de', 'fr', 'es'] as $locale) {
    Route::group(['prefix' => $locale, 'middleware' => 'set.locale'], function () use ($locale) {
        Route::get('/', [\App\Http\Controllers\Public\HomeController::class, 'index']);
        Route::get('/about', [\App\Http\Controllers\Public\PageController::class, 'about']);
        Route::get('/contact', [\App\Http\Controllers\Public\PageController::class, 'contact']);

        Route::get('/events', [\App\Http\Controllers\Public\EventController::class, 'index']);

        Route::get('/venues', [PublicVenueController::class, 'index']);
        Route::get('/venue/{venue}', [PublicVenueController::class, 'show']);

        Route::get('/compare/{country}/{slug}', [\App\Http\Controllers\Public\CompareController::class, 'show'])
            ->where('country', '[a-z]{2}')
            ->where('slug', '[a-z0-9\-]+');

        Route::get('/artists', [ArtistPublicController::class, 'index']);
        Route::get('/artist/{slug}', [ArtistPublicController::class, 'show']);

        // Location API endpoints for filters
        Route::get('/api/countries', [LocationController::class, 'countries']);
        Route::get('/api/states/{country}', [LocationController::class, 'states']);
        Route::get('/api/cities/{country}/{state}', [LocationController::class, 'cities']);

        // Taxonomy API endpoints
        Route::get('/api/event-genres/{typeSlug?}', [LocationController::class, 'eventGenresByType']);
    });
}

// Invoice PDF Routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/invoices/{invoice}/pdf', [App\Http\Controllers\InvoicePdfController::class, 'download'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/pdf/preview', [App\Http\Controllers\InvoicePdfController::class, 'preview'])->name('invoices.pdf.preview');

    // API endpoint for tenant data (used in invoice create/edit forms)
    Route::get('/api/tenants/{tenant}', function (\App\Models\Tenant $tenant) {
        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'company_name' => $tenant->company_name,
            'cui' => $tenant->cui,
            'reg_com' => $tenant->reg_com,
            'contract_number' => $tenant->contract_number,
            'bank_name' => $tenant->bank_name,
            'bank_account' => $tenant->bank_account,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'state' => $tenant->state,
            'country' => $tenant->country,
            'billing_starts_at' => $tenant->billing_starts_at?->toDateString(),
            'billing_cycle_days' => $tenant->billing_cycle_days ?? 30,
        ]);
    })->name('api.tenants.show');
});

/*
|--------------------------------------------------------------------------
| Public Status Page
|--------------------------------------------------------------------------
*/

Route::get('/status', [StatusController::class, 'index'])
    ->name('status');
