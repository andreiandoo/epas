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

        // System error recorder — single shared instance used by the log
        // listener, exception reporter, observers and the backfill command.
        $this->app->singleton(\App\Logging\SystemErrorRecorder::class, function ($app) {
            return new \App\Logging\SystemErrorRecorder(
                new \App\Logging\ErrorClassifier(),
                new \App\Logging\RequestContextEnricher(),
            );
        });
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

        // Register support-ticket polymorphic aliases (organizer/customer/
        // staff) without strict mode — using ::morphMap() instead of
        // ::enforceMorphMap() so other polymorphic relations in the app
        // (Spatie ActivityLog subject/causer, MarketplaceNotification, etc.)
        // that store full class names keep working as before. enforceMorphMap
        // turns on requireMorphMap site-wide, which broke every unmapped
        // polymorphic on this deploy.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(
            (array) config('support.morph_map', [])
        );

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
        \App\Models\Order::observe(\App\Observers\FacebookCapiOrderObserver::class);
        \App\Models\Event::observe(\App\Observers\EventObserver::class);
        \App\Models\Event::observe(\App\Observers\MarketplaceEventObserver::class);
        \App\Models\MarketplaceCustomer::observe(\App\Observers\MarketplaceCustomerObserver::class);
        \App\Models\MarketplaceOrganizer::observe(\App\Observers\MarketplaceOrganizerObserver::class);
        \App\Models\MediaLibrary::observe(\App\Observers\MediaLibraryObserver::class);
        \App\Models\MarketplacePayout::observe(\App\Observers\MarketplacePayoutObserver::class);
        \App\Models\Venue::observe(\App\Observers\VenueObserver::class);
        \App\Models\FestivalEdition::observe(\App\Observers\FestivalEditionObserver::class);
        \App\Models\Coupon\CouponCode::observe(\App\Observers\CouponCodeObserver::class);

        // System-error mirroring observers: capture business-domain failures
        // (email send failure, queue job failure, payment status flips) into
        // the system_errors dashboard alongside Log:: entries.
        \App\Models\MarketplaceEmailLog::observe(\App\Observers\MarketplaceEmailLogObserver::class);
        if (class_exists(\App\Models\EmailLog::class)) {
            \App\Models\EmailLog::observe(\App\Observers\EmailLogObserver::class);
        }

        // Mirror application logs into system_errors. Keeps existing file
        // logging untouched; this is a parallel sink for the admin error
        // dashboard. Filtered by level (warning+) and channel allowlist.
        $this->bootSystemErrorsLogListener();

        // Mirror failed queue jobs into system_errors as a single source of truth.
        $this->bootSystemErrorsQueueListener();

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

    /**
     * Subscribe to Laravel's MessageLogged event and mirror anything at
     * the configured capture level (default WARNING) into system_errors.
     *
     * The recorder swallows all errors internally so a logging-row failure
     * never disrupts the original log call.
     */
    protected function bootSystemErrorsLogListener(): void
    {
        $allowedChannels = (array) config('system_errors.channels', []);
        $minLevel = (int) config('system_errors.capture_level', 300);

        \Illuminate\Support\Facades\Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use ($allowedChannels, $minLevel) {
            // Channel allow-list (empty list = capture all)
            if (!empty($allowedChannels) && !in_array($event->level, [], true)) {
                $channelName = property_exists($event, 'channel') ? $event->channel : null;
                if ($channelName && !in_array($channelName, $allowedChannels, true)) {
                    return;
                }
            }

            // Translate textual level to Monolog numeric level
            $numericLevel = match (strtolower((string) $event->level)) {
                'emergency' => 600,
                'alert' => 550,
                'critical' => 500,
                'error' => 400,
                'warning' => 300,
                'notice' => 250,
                'info' => 200,
                'debug' => 100,
                default => 0,
            };
            if ($numericLevel < $minLevel) {
                return;
            }

            $context = is_array($event->context) ? $event->context : [];
            $exception = $context['exception'] ?? null;
            unset($context['exception']);

            /** @var \App\Logging\SystemErrorRecorder $recorder */
            $recorder = app(\App\Logging\SystemErrorRecorder::class);
            $recorder->record([
                'level' => $numericLevel,
                'level_name' => \App\Logging\SystemErrorRecorder::levelName($numericLevel),
                'channel' => $event->channel ?? null,
                'source' => 'log',
                'message' => (string) $event->message,
                'context' => $context,
                'exception_class' => $exception instanceof \Throwable ? $exception::class : null,
                'exception_file' => $exception instanceof \Throwable ? $exception->getFile() : null,
                'exception_line' => $exception instanceof \Throwable ? $exception->getLine() : null,
                'stack_trace' => $exception instanceof \Throwable ? $exception->getTraceAsString() : null,
            ]);
        });
    }

    /**
     * Capture queue-job failures separately. Laravel writes failed_jobs
     * rows on its own; we mirror them so the dashboard treats them the
     * same as everything else.
     */
    protected function bootSystemErrorsQueueListener(): void
    {
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            try {
                /** @var \App\Logging\SystemErrorRecorder $recorder */
                $recorder = app(\App\Logging\SystemErrorRecorder::class);
                $recorder->recordThrowable(
                    $event->exception,
                    level: 400,
                    channel: 'queue',
                    source: 'failed_job',
                    context: [
                        'job_id' => $event->job->getJobId(),
                        'job_name' => $event->job->resolveName(),
                        'connection' => $event->connectionName,
                        'queue' => $event->job->getQueue(),
                        'attempts' => $event->job->attempts(),
                    ],
                );
            } catch (\Throwable $e) {
                @error_log('[SystemErrors] queue listener failed: ' . $e->getMessage());
            }
        });
    }
}
