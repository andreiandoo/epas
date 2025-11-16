<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\WhatsApp\Adapters\MockBspAdapter;
use App\Services\WhatsApp\Adapters\TwilioAdapter;
use App\Services\EFactura\EFacturaService;
use App\Services\EFactura\Adapters\MockAnafAdapter;
use App\Services\EFactura\Adapters\AnafAdapter;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\Adapters\MockAccountingAdapter;
use App\Services\Accounting\Adapters\SmartBillAdapter;
use App\Services\Insurance\InsuranceService;
use App\Services\Insurance\Adapters\MockInsurerAdapter;

/**
 * Microservices Service Provider
 *
 * Registers all microservice services as singletons with their adapters
 */
class MicroservicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register WhatsApp Service
        $this->app->singleton(WhatsAppService::class, function ($app) {
            $service = new WhatsAppService();

            // Register mock adapter for development/testing
            $service->registerAdapter('mock', new MockBspAdapter());

            // Register Twilio adapter (recommended production BSP)
            $service->registerAdapter('twilio', new TwilioAdapter());

            // Future: Register 360dialog adapter when credentials available
            // if (config('services.whatsapp.360dialog.enabled')) {
            //     $service->registerAdapter('360dialog', new ThreeSixZeroDialogAdapter());
            // }

            return $service;
        });

        // Register eFactura Service
        $this->app->singleton(EFacturaService::class, function ($app) {
            $service = new EFacturaService();

            // Register mock adapter for development/testing
            $service->registerAdapter('mock', new MockAnafAdapter());

            // Register real ANAF adapter for production
            $service->registerAdapter('anaf', new AnafAdapter());

            return $service;
        });

        // Register Accounting Service
        $this->app->singleton(AccountingService::class, function ($app) {
            $service = new AccountingService();

            // Register mock adapter for development/testing
            $service->registerAdapter('mock', new MockAccountingAdapter());

            // Register SmartBill adapter for production (Romanian accounting)
            $service->registerAdapter('smartbill', new SmartBillAdapter());

            // Future: Register other accounting adapters
            // if (config('services.accounting.fgo.enabled')) {
            //     $service->registerAdapter('fgo', new FgoAdapter());
            // }

            return $service;
        });

        // Register Insurance Service
        $this->app->singleton(InsuranceService::class, function ($app) {
            $service = new InsuranceService();

            // Register mock adapter for development/testing
            $service->registerAdapter('mock', new MockInsurerAdapter());

            // Register real insurance adapters
            // if (config('services.insurance.provider.enabled')) {
            //     $service->registerAdapter('provider', new RealInsurerAdapter());
            // }

            return $service;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
