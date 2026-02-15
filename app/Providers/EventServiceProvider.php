<?php

namespace App\Providers;

use App\Events\OrderConfirmed;
use App\Events\PromoCodes\PromoCodeCreated;
use App\Events\PromoCodes\PromoCodeUsed;
use App\Events\PromoCodes\PromoCodeExpired;
use App\Events\PromoCodes\PromoCodeDepleted;
use App\Events\PromoCodes\PromoCodeUpdated;
use App\Events\PromoCodes\PromoCodeDeactivated;
use App\Listeners\AdsCampaign\OrderConversionListener;
use App\Listeners\PromoCodes\LogPromoCodeActivity;
use App\Listeners\PromoCodes\SendPromoCodeAlerts;
use App\Listeners\PromoCodes\UpdatePromoCodeMetrics;
use App\Listeners\Tax\TaxEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event subscriber mappings for the application.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        TaxEventSubscriber::class,
    ];

    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderConfirmed::class => [
            OrderConversionListener::class,
        ],
        PromoCodeCreated::class => [
            [LogPromoCodeActivity::class, 'handleCreated'],
        ],
        PromoCodeUsed::class => [
            [LogPromoCodeActivity::class, 'handleUsed'],
            UpdatePromoCodeMetrics::class,
        ],
        PromoCodeExpired::class => [
            [LogPromoCodeActivity::class, 'handleExpired'],
            [SendPromoCodeAlerts::class, 'handleExpired'],
        ],
        PromoCodeDepleted::class => [
            [LogPromoCodeActivity::class, 'handleDepleted'],
            [SendPromoCodeAlerts::class, 'handleDepleted'],
        ],
        PromoCodeUpdated::class => [
            [LogPromoCodeActivity::class, 'handleUpdated'],
        ],
        PromoCodeDeactivated::class => [
            [LogPromoCodeActivity::class, 'handleDeactivated'],
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
