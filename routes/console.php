<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic invoice generation for tenants
Schedule::command('invoices:generate-tenant')
    ->dailyAt('02:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Tenant invoices generated successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to generate tenant invoices');
    });

// Schedule overdue invoice reminders (daily at 9 AM)
Schedule::command('invoices:send-overdue-reminders')
    ->dailyAt('09:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Overdue invoice reminders sent successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to send overdue invoice reminders');
    });

// Schedule invoice status transition from 'new' to 'outstanding' (daily at 1 AM)
Schedule::command('invoices:transition-new --grace-days=3')
    ->dailyAt('01:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Invoice status transitions completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to transition invoice statuses');
    });

/*
|--------------------------------------------------------------------------
| Microservices Scheduled Tasks
|--------------------------------------------------------------------------
*/

// WhatsApp: Process scheduled reminders (every 10 minutes)
Schedule::call(function () {
    $service = app(\App\Services\WhatsApp\WhatsAppService::class);
    $result = $service->processScheduledReminders(50);
    \Log::info('WhatsApp reminders processed', $result);
})->everyTenMinutes();

// eFactura: Process queue (every 5 minutes)
Schedule::call(function () {
    $service = app(\App\Services\EFactura\EFacturaService::class);
    $result = $service->processQueue(10);
    \Log::info('eFactura queue processed', $result);
})->everyFiveMinutes();

// eFactura: Poll pending submissions (every 10 minutes)
Schedule::call(function () {
    $service = app(\App\Services\EFactura\EFacturaService::class);
    $result = $service->pollPending(20);
    \Log::info('eFactura polling completed', $result);
})->everyTenMinutes();

// Tenant microservices: Check expirations and send alerts (daily at 8 AM)
Schedule::call(function () {
    $expiringSoon = \DB::table('tenant_microservices')
        ->where('status', 'active')
        ->whereBetween('expires_at', [now(), now()->addDays(7)])
        ->get();

    $notificationService = app(\App\Services\NotificationService::class);

    foreach ($expiringSoon as $subscription) {
        \Log::info('Microservice expiring soon', [
            'tenant_id' => $subscription->tenant_id,
            'microservice_id' => $subscription->microservice_id,
            'expires_at' => $subscription->expires_at,
        ]);

        // Send notification to tenant
        $notificationService->notifyMicroserviceExpiring(
            $subscription->tenant_id,
            (array) $subscription
        );
    }
})->dailyAt('08:00')->timezone('Europe/Bucharest');

// Tenant microservices: Auto-suspend expired subscriptions (daily at midnight)
Schedule::call(function () {
    $expired = \DB::table('tenant_microservices')
        ->where('status', 'active')
        ->where('expires_at', '<', now())
        ->get();

    $notificationService = app(\App\Services\NotificationService::class);

    foreach ($expired as $subscription) {
        \DB::table('tenant_microservices')
            ->where('id', $subscription->id)
            ->update(['status' => 'suspended']);

        \Log::info('Microservice auto-suspended', [
            'tenant_id' => $subscription->tenant_id,
            'microservice_id' => $subscription->microservice_id,
        ]);

        // Send notification to tenant
        $notificationService->notifyMicroserviceSuspended(
            $subscription->tenant_id,
            (array) $subscription
        );
    }
})->daily()->timezone('Europe/Bucharest');

// Webhooks: Retry failed deliveries (every 10 minutes)
Schedule::call(function () {
    $webhookService = app(\App\Services\Webhooks\WebhookService::class);
    $result = $webhookService->processRetries();
    \Log::info('Webhook retries processed', $result);
})->everyTenMinutes();

// Metrics: Daily cleanup of old metrics (daily at 3 AM)
Schedule::call(function () {
    $metricsService = app(\App\Services\Metrics\MetricsService::class);
    $deleted = $metricsService->cleanup();
    \Log::info('Metrics cleanup completed', ['deleted' => $deleted]);
})->dailyAt('03:00')->timezone('Europe/Bucharest');

// Health: Monitor system health and send alerts (every 5 minutes)
Schedule::call(function () {
    $healthService = app(\App\Services\Health\HealthCheckService::class);
    $health = $healthService->checkAll();

    if ($health['status'] === 'unhealthy') {
        \Log::critical('System health check failed', $health);

        // Send alert notification
        $alertService = app(\App\Services\Alerts\AlertService::class);
        $alertService->sendHealthAlert($health);
    } elseif ($health['status'] === 'degraded') {
        \Log::warning('System health degraded', $health);
    }
})->everyFiveMinutes();

// Services Status: Log service status for uptime tracking (every 5 minutes)
Schedule::command('services:check-status')
    ->everyFiveMinutes()
    ->onSuccess(function () {
        \Log::info('Services status check completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to check services status');
    });

// Cache: Warm up global caches (every hour)
Schedule::call(function () {
    if (config('microservices.cache.enabled', true)) {
        $cacheService = app(\App\Services\Cache\MicroservicesCacheService::class);
        $cacheService->warmGlobalCache();
        \Log::info('Global cache warmed');
    }
})->hourly();

// Audit: Cleanup old audit logs (daily at 4 AM)
Schedule::call(function () {
    $auditService = app(\App\Services\Audit\AuditService::class);
    $retentionDays = config('microservices.audit.retention_days', 365);
    $deleted = $auditService->cleanup($retentionDays);
    \Log::info('Audit logs cleanup completed', ['deleted' => $deleted]);
})->dailyAt('04:00')->timezone('Europe/Bucharest');

// API Usage: Cleanup old usage records (weekly on Sunday at 5 AM)
Schedule::call(function () {
    if (config('microservices.api.track_detailed_usage', false)) {
        $apiKeyService = app(\App\Services\Api\TenantApiKeyService::class);
        $retentionDays = config('microservices.api.usage_retention_days', 90);
        $deleted = $apiKeyService->cleanupUsage($retentionDays);
        \Log::info('API usage cleanup completed', ['deleted' => $deleted]);
    }
})->weeklyOn(0, '05:00')->timezone('Europe/Bucharest');

/*
|--------------------------------------------------------------------------
| Promo Codes Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Promo Codes: Auto-expire codes (every hour)
Schedule::command('promo:expire')
    ->hourly()
    ->onSuccess(function () {
        \Log::info('Promo codes expiration check completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to expire promo codes');
    });

// Promo Codes: Alert for codes expiring soon (daily at 9 AM)
Schedule::command('promo:alert-expiring --days=7')
    ->dailyAt('09:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Promo code expiration alerts sent');
    })
    ->onFailure(function () {
        \Log::error('Failed to send promo code alerts');
    });

// Promo Codes: Cleanup old usage records (weekly on Sunday at 6 AM)
Schedule::command('promo:cleanup --days=365')
    ->weeklyOn(0, '06:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Promo code cleanup completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to cleanup promo code records');
    });

/*
|--------------------------------------------------------------------------
| Exchange Rates Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Fetch daily exchange rates (daily at 10 AM - after ECB publishes)
Schedule::command('exchange-rates:fetch')
    ->dailyAt('10:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Exchange rates fetched successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to fetch exchange rates');
    });

/*
|--------------------------------------------------------------------------
| Artist Social Stats Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Update artist social media stats (Monday and Thursday at 3 AM)
Schedule::command('artists:update-social-stats')
    ->weekly()->days([1, 4])->at('03:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Artist social stats updated successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to update artist social stats');
    });
