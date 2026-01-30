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
| Platform Tracking Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Process pending platform conversions (every 5 minutes)
Schedule::job(new \App\Jobs\ProcessPlatformConversionsJob)
    ->everyFiveMinutes()
    ->onSuccess(function () {
        \Log::info('Platform conversions processing completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to process platform conversions');
    });

// Refresh token alerts for expiring platform ad accounts (daily at 8 AM)
Schedule::call(function () {
    $expiringSoon = \App\Models\Platform\PlatformAdAccount::active()
        ->whereNotNull('token_expires_at')
        ->where('token_expires_at', '<=', now()->addDays(3))
        ->get();

    foreach ($expiringSoon as $account) {
        \Log::warning('Platform ad account token expiring', [
            'account_id' => $account->id,
            'platform' => $account->platform,
            'expires_at' => $account->token_expires_at,
        ]);
    }
})->dailyAt('08:00')->timezone('Europe/Bucharest');

// Clean up old platform tracking sessions (daily at 4:30 AM)
Schedule::call(function () {
    $deleted = \App\Models\Platform\CoreSession::where('last_activity_at', '<', now()->subDays(90))
        ->delete();
    \Log::info('Old platform tracking sessions cleaned up', ['deleted' => $deleted]);
})->dailyAt('04:30')->timezone('Europe/Bucharest');

// Retry failed conversions (every 15 minutes)
Schedule::job(new \App\Jobs\RetryFailedConversionsJob)
    ->everyFifteenMinutes()
    ->onSuccess(function () {
        \Log::info('Failed conversions retry completed');
    })
    ->onFailure(function () {
        \Log::error('Failed conversions retry job failed');
    });

// Calculate RFM scores for customers (daily at 2 AM)
Schedule::job(new \App\Jobs\CalculateRfmScoresJob)
    ->dailyAt('02:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('RFM score calculation completed');
    })
    ->onFailure(function () {
        \Log::error('RFM score calculation failed');
    });

// Sync audiences that need syncing (every hour)
Schedule::call(function () {
    $audiences = \App\Models\Platform\PlatformAudience::active()
        ->needsSync()
        ->get();

    $trackingService = app(\App\Services\Platform\PlatformTrackingService::class);

    foreach ($audiences as $audience) {
        try {
            $trackingService->syncAudience($audience);
            \Log::info('Audience auto-synced', ['audience_id' => $audience->id]);
        } catch (\Exception $e) {
            \Log::error('Audience auto-sync failed', [
                'audience_id' => $audience->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->hourly();

/*
|--------------------------------------------------------------------------
| Platform Analytics Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Calculate cohort retention metrics (daily at 3 AM)
Schedule::job(new \App\Jobs\CalculateCohortMetricsJob('month', 12, 12))
    ->dailyAt('03:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Monthly cohort metrics calculation completed');
    })
    ->onFailure(function () {
        \Log::error('Monthly cohort metrics calculation failed');
    });

// Calculate weekly cohort metrics (every Monday at 3:30 AM)
Schedule::job(new \App\Jobs\CalculateCohortMetricsJob('week', 12, 12))
    ->weeklyOn(1, '03:30')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Weekly cohort metrics calculation completed');
    })
    ->onFailure(function () {
        \Log::error('Weekly cohort metrics calculation failed');
    });

// Calculate customer health scores (daily at 2:30 AM)
Schedule::call(function () {
    $cacheService = app(\App\Services\Platform\AnalyticsCacheService::class);
    $cacheService->calculateAllHealthScores();
    \Log::info('Customer health scores calculated');
})->dailyAt('02:30')->timezone('Europe/Bucharest');

// Calculate churn predictions for at-risk customers (daily at 4 AM)
Schedule::call(function () {
    $churnService = app(\App\Services\Platform\ChurnPredictionService::class);
    $result = $churnService->updateCustomerChurnScores(500);
    \Log::info('Churn predictions calculated', ['updated' => $result['updated'], 'errors' => $result['errors']]);
})->dailyAt('04:00')->timezone('Europe/Bucharest');

// Detect duplicate customers (daily at 5 AM)
Schedule::call(function () {
    $duplicateService = app(\App\Services\Platform\DuplicateDetectionService::class);

    // Find high-confidence duplicates
    $duplicates = $duplicateService->findAllDuplicates(0.85, 100);

    if ($duplicates->count() > 0) {
        \Log::info('Potential duplicate customers detected', [
            'count' => $duplicates->count(),
        ]);
    }
})->dailyAt('05:00')->timezone('Europe/Bucharest');

// Auto-merge definite duplicates (weekly on Sunday at 5:30 AM)
Schedule::call(function () {
    $duplicateService = app(\App\Services\Platform\DuplicateDetectionService::class);
    $result = $duplicateService->autoMergeHighConfidenceDuplicates();
    \Log::info('Auto-merge duplicates completed', $result);
})->weeklyOn(0, '05:30')->timezone('Europe/Bucharest');

// Process GDPR requests (every 30 minutes)
Schedule::call(function () {
    $pendingRequests = \App\Models\Platform\GdprRequest::pending()
        ->orderBy('requested_at')
        ->limit(10)
        ->get();

    foreach ($pendingRequests as $request) {
        try {
            $request->process();
            \Log::info('GDPR request processed', [
                'id' => $request->id,
                'type' => $request->request_type,
            ]);
        } catch (\Exception $e) {
            \Log::error('GDPR request processing failed', [
                'id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->everyThirtyMinutes();

// Data retention cleanup (daily at 5:30 AM)
Schedule::call(function () {
    $policies = \App\Models\Platform\DataRetentionPolicy::active()->get();

    foreach ($policies as $policy) {
        $cutoffDate = $policy->getCutoffDate();
        $deleted = 0;

        switch ($policy->data_type) {
            case 'sessions':
                $deleted = \App\Models\Platform\CoreSession::where('started_at', '<', $cutoffDate)->delete();
                break;
            case 'events':
                $deleted = \App\Models\Platform\CoreCustomerEvent::where('created_at', '<', $cutoffDate)->delete();
                break;
            case 'conversions':
                $deleted = \App\Models\Platform\PlatformConversion::where('conversion_time', '<', $cutoffDate)->delete();
                break;
        }

        if ($deleted > 0) {
            $policy->recordCleanup($deleted);
            \Log::info('Data retention cleanup completed', [
                'data_type' => $policy->data_type,
                'deleted' => $deleted,
            ]);
        }
    }
})->dailyAt('05:30')->timezone('Europe/Bucharest');

// Warm analytics cache (every 2 hours)
Schedule::call(function () {
    $cacheService = app(\App\Services\Platform\AnalyticsCacheService::class);
    $cacheService->warmUp();
    \Log::info('Analytics cache warmed');
})->everyTwoHours();

/*
|--------------------------------------------------------------------------
| Exchange Rates Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Fetch daily exchange rates (daily at 7 AM)
Schedule::command('exchange-rates:fetch')
    ->dailyAt('07:00')
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

/*
|--------------------------------------------------------------------------
| Activity Log Cleanup
|--------------------------------------------------------------------------
*/

// Cleanup old tenant activity logs (daily at 3:30 AM - keep 10 days)
Schedule::command('activitylog:cleanup --days=10')
    ->dailyAt('03:30')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Tenant activity logs cleanup completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to cleanup tenant activity logs');
    });

/*
|--------------------------------------------------------------------------
| Event Analytics Aggregation Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Process real-time analytics (every minute)
// This processes raw tracking events into hourly buckets for dashboard display
Schedule::command('analytics:process-realtime --minutes=5')
    ->everyMinute()
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::debug('Real-time analytics processing completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to process real-time analytics');
    });

// Aggregate hourly data into daily summaries (hourly at :05)
// This ensures hourly data is aggregated into daily buckets
Schedule::command('analytics:aggregate --type=daily')
    ->hourlyAt(5)
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Daily analytics aggregation completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to aggregate daily analytics');
    });

// Full daily aggregation (daily at 1:00 AM for previous day)
Schedule::command('analytics:aggregate --type=daily')
    ->dailyAt('01:00')
    ->timezone('Europe/Bucharest')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Full daily analytics aggregation completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to complete full daily analytics aggregation');
    });

// Aggregate daily data into weekly summaries (every Monday at 2:00 AM)
Schedule::command('analytics:aggregate --type=weekly')
    ->weeklyOn(1, '02:00')
    ->timezone('Europe/Bucharest')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Weekly analytics aggregation completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to aggregate weekly analytics');
    });

// Aggregate daily data into monthly summaries (1st of month at 3:00 AM)
Schedule::command('analytics:aggregate --type=monthly')
    ->monthlyOn(1, '03:00')
    ->timezone('Europe/Bucharest')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Monthly analytics aggregation completed');
    })
    ->onFailure(function () {
        \Log::error('Failed to aggregate monthly analytics');
    });

// Recalculate milestone metrics (every 30 minutes)
// This updates ROI, CAC, ROAS for ad campaign milestones
Schedule::call(function () {
    $service = app(\App\Services\Analytics\MilestoneAttributionService::class);

    // Get all active events with ad campaigns
    $events = \App\Models\Event::whereHas('milestones', function ($q) {
        $q->whereIn('type', \App\Models\EventMilestone::AD_CAMPAIGN_TYPES)
          ->where(function ($inner) {
              $inner->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->subDays(7)); // Include recently ended
          });
    })->get();

    foreach ($events as $event) {
        foreach ($event->milestones()->whereIn('type', \App\Models\EventMilestone::AD_CAMPAIGN_TYPES)->get() as $milestone) {
            $service->updateMilestoneMetrics($milestone);
        }
    }

    \Log::info('Milestone metrics recalculated', ['events_processed' => $events->count()]);
})->everyThirtyMinutes();

/*
|--------------------------------------------------------------------------
| Event Analytics Reports & Goals Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Process scheduled analytics reports (every 5 minutes)
// Checks for due report schedules and sends them to recipients
Schedule::command('analytics:process-reports --type=reports')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Scheduled analytics reports processed');
    })
    ->onFailure(function () {
        \Log::error('Failed to process scheduled analytics reports');
    });

// Process goal alerts (every 15 minutes)
// Checks goal progress and sends threshold notifications
Schedule::command('analytics:process-reports --type=goals')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Goal alerts processed');
    })
    ->onFailure(function () {
        \Log::error('Failed to process goal alerts');
    });

// Cleanup old export files (daily at 4:00 AM)
Schedule::command('analytics:process-reports --type=cleanup')
    ->dailyAt('04:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Old analytics export files cleaned up');
    })
    ->onFailure(function () {
        \Log::error('Failed to cleanup analytics export files');
    });

/*
|--------------------------------------------------------------------------
| Seating Module Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Release expired seat holds (every minute)
// This is the fallback cleanup for when Redis is disabled or as a safety net
Schedule::command('seating:release-expired-holds')
    ->everyMinute()
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::debug('Expired seat holds released');
    })
    ->onFailure(function () {
        \Log::error('Failed to release expired seat holds');
    });
