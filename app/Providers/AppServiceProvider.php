<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Consent Service for tracking
        $this->app->singleton(
            \App\Services\Tracking\ConsentServiceInterface::class,
            \App\Services\Tracking\SessionConsentService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $helpers = app_path('Support/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        // Register the marketplace-mail notification channel. Notifications
        // that return ['marketplace-mail'] from via() route through the
        // active marketplace's configured SMTP transport instead of the
        // global Laravel mailer (no localhost leakage).
        \Illuminate\Support\Facades\Notification::extend('marketplace-mail', function ($app) {
            return new \App\Notifications\Channels\MarketplaceMailChannel();
        });

        // Ensure all upload directories exist on the public disk
        $this->ensureUploadDirectories();

        // Define API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Increase table search debounce to 2s across all panels
        Table::configureUsing(function (Table $table): void {
            $table->searchDebounce('2000ms');
        });

        // PostgreSQL: make LIKE case-insensitive and accent-insensitive
        if (config('database.default') === 'pgsql' || (app()->bound('db') && \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql')) {
            \Illuminate\Database\Query\Builder::macro('whereLikeUnaccent', function (string $column, string $value) {
                return $this->whereRaw("unaccent(lower({$column}::text)) LIKE unaccent(lower(?))", ["%{$value}%"]);
            });
        }

        // Register observers
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\Artist::observe(\App\Observers\ArtistObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\Event::observe(\App\Observers\EventObserver::class);
        \App\Models\Event::observe(\App\Observers\MarketplaceEventObserver::class);
        \App\Models\MarketplaceCustomer::observe(\App\Observers\MarketplaceCustomerObserver::class);
        \App\Models\MarketplaceOrganizer::observe(\App\Observers\MarketplaceOrganizerObserver::class);
        \App\Models\MediaLibrary::observe(\App\Observers\MediaLibraryObserver::class);
        \App\Models\MarketplacePayout::observe(\App\Observers\MarketplacePayoutObserver::class);
        \App\Models\Venue::observe(\App\Observers\VenueObserver::class);
        \App\Models\FestivalEdition::observe(\App\Observers\FestivalEditionObserver::class);

        // Register microservices event listeners
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\OrderConfirmed::class,
            \App\Listeners\SendOrderConfirmationListener::class,
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentCaptured::class,
            [
                \App\Listeners\SubmitEFacturaListener::class,
                \App\Listeners\IssueInvoiceListener::class,
            ]
        );
    }

    /**
     * Ensure all required upload directories exist on the public disk.
     * Uses a stamp file so directory checks only run once per deploy.
     */
    protected function ensureUploadDirectories(): void
    {
        $stampFile = storage_path('app/.upload-dirs-created');
        if (file_exists($stampFile)) {
            return;
        }

        $publicDisk = storage_path('app/public');

        $directories = [
            'artists',
            'artists/logos',
            'artists/portraits',
            'avatars',
            'badges',
            'blog-images',
            'blog-og-images',
            'cities',
            'cities/covers',
            'contracts',
            'counties',
            'downloads',
            'event-categories',
            'events/featured',
            'events/featured/homepage',
            'events/hero',
            'events/posters',
            'gift-card-designs',
            'imports',
            'organizer-documents',
            'seating/backgrounds',
            'seating/zones',
            'shop/categories',
            'shop-products',
            'shop-products/gallery',
            'shop-variants',
            'temp-imports',
            'tenant-branding',
            'venues',
            'venues/gallery',
            'venues/videos',
        ];

        foreach ($directories as $dir) {
            $path = $publicDisk . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
        }

        // Write stamp so we skip on subsequent requests
        @file_put_contents($stampFile, date('Y-m-d H:i:s'));
    }
}
