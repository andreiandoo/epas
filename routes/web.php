<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\ArtistPublicController;
use App\Http\Controllers\Public\VenueController as PublicVenueController;
use App\Http\Controllers\Public\LocationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\MicroserviceMarketplaceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TenantPaymentWebhookController;
use App\Http\Controllers\StatusController;

Route::pattern('locale', 'en|ro|de|fr|es');

// DEBUG: Check auth status (NO auth middleware - shows if user is logged in)
Route::middleware(['web'])->get('/check-auth', function() {
    return response()->json([
        'is_authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'user_email' => auth()->user()?->email,
        'user_role' => auth()->user()?->role,
        'session_id' => session()->getId(),
        'session_driver' => config('session.driver'),
        'has_session_cookie' => request()->hasCookie(config('session.cookie')),
        'session_data' => session()->all(),
    ]);
})->name('check.auth');

// DEBUG: Test route to bypass Filament and test middleware
Route::middleware(['web', 'auth'])->get('/test-admin-access', function() {
    $user = auth()->user();
    return response()->json([
        'success' => true,
        'message' => 'Middleware auth passed successfully!',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'can_access_panel' => $user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')),
    ]);
})->name('test.admin.access');

// Admin Global Search API (requires authentication)
Route::middleware(['web', 'auth'])->get('/admin/api/global-search', [GlobalSearchController::class, 'search'])->name('admin.api.global-search');

// DEBUG: Test session and cookies
Route::middleware(['web'])->get('/test-session', function() {
    session(['test_key' => 'test_value_' . time()]);
    return response()->json([
        'session_id' => session()->getId(),
        'test_key' => session('test_key'),
        'all_session' => session()->all(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'cookie_name' => config('session.cookie'),
        'session_driver' => config('session.driver'),
    ])->withCookie(cookie('manual-test', 'manual-value', 120));
});

Route::middleware(['web'])->get('/test-session-read', function() {
    return response()->json([
        'session_id' => session()->getId(),
        'test_key' => session('test_key'),
        'all_session' => session()->all(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'manual_test_cookie' => request()->cookie('manual-test'),
    ]);
});

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

// Microservices Store Routes
Route::prefix('store')->middleware(['web'])->group(function () {
    // Public pages
    Route::get('/', [MicroserviceMarketplaceController::class, 'index'])
        ->name('store.index');

    Route::get('/microservice/{slug}', [MicroserviceMarketplaceController::class, 'show'])
        ->name('store.show');

    // Cart management (session-based)
    Route::post('/cart/add', [MicroserviceMarketplaceController::class, 'addToCart'])
        ->name('store.cart.add');

    Route::post('/cart/remove', [MicroserviceMarketplaceController::class, 'removeFromCart'])
        ->name('store.cart.remove');

    Route::get('/cart', [MicroserviceMarketplaceController::class, 'cart'])
        ->name('store.cart');

    // Checkout (requires auth + tenant role)
    Route::middleware(['auth'])->group(function () {
        Route::get('/checkout', [MicroserviceMarketplaceController::class, 'checkoutPage'])
            ->name('store.checkout');

        Route::post('/checkout/process', [MicroserviceMarketplaceController::class, 'processCheckout'])
            ->name('store.checkout.process');
    });

    Route::get('/payment/success', [MicroserviceMarketplaceController::class, 'success'])
        ->name('store.payment.success');

    Route::get('/payment/cancel', [MicroserviceMarketplaceController::class, 'cancel'])
        ->name('store.payment.cancel');
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

// Admin Domain Management Routes (TEMPORARILY DISABLED - testing 403 fix)
// TODO: Re-enable with proper auth middleware after fixing 403 issue
// Route::middleware(['web'])->prefix('admin')->group(function () {
//     Route::post('/tenants/{tenantId}/domains', [DomainController::class, 'store'])->name('admin.tenants.domains.store');
//     Route::post('/domains/{domainId}/toggle-active', [DomainController::class, 'toggleActive'])->name('admin.domains.toggle-active');
//     Route::get('/tenants/{tenantId}/domains/{domain}/login-as-admin', [DomainController::class, 'loginAsAdmin'])->name('tenant.login-as-admin');
// });

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
