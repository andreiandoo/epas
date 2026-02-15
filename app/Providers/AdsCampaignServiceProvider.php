<?php

namespace App\Providers;

use App\Jobs\AdsCampaign\GenerateAdsReports;
use App\Jobs\AdsCampaign\OptimizeAdsCampaigns;
use App\Jobs\AdsCampaign\SyncAdsCampaignMetrics;
use App\Jobs\AdsCampaign\SyncAdsAudienceSegments;
use App\Services\AdsCampaign\AdsCampaignManager;
use App\Services\AdsCampaign\BudgetAllocator;
use App\Services\AdsCampaign\CampaignOptimizer;
use App\Services\AdsCampaign\FacebookMarketingService;
use App\Services\AdsCampaign\GoogleAdsCampaignService;
use App\Services\AdsCampaign\MetricsAggregator;
use App\Services\AdsCampaign\ReportGenerator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AdsCampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/ads-campaign.php'), 'ads-campaign'
        );

        // Register singleton services
        $this->app->singleton(FacebookMarketingService::class);
        $this->app->singleton(GoogleAdsCampaignService::class);
        $this->app->singleton(BudgetAllocator::class);

        $this->app->singleton(MetricsAggregator::class, function ($app) {
            return new MetricsAggregator(
                $app->make(FacebookMarketingService::class),
                $app->make(GoogleAdsCampaignService::class),
            );
        });

        $this->app->singleton(CampaignOptimizer::class, function ($app) {
            return new CampaignOptimizer(
                $app->make(FacebookMarketingService::class),
                $app->make(GoogleAdsCampaignService::class),
                $app->make(BudgetAllocator::class),
            );
        });

        $this->app->singleton(ReportGenerator::class, function ($app) {
            return new ReportGenerator(
                $app->make(MetricsAggregator::class),
            );
        });

        $this->app->singleton(AdsCampaignManager::class, function ($app) {
            return new AdsCampaignManager(
                $app->make(FacebookMarketingService::class),
                $app->make(GoogleAdsCampaignService::class),
                $app->make(MetricsAggregator::class),
                $app->make(CampaignOptimizer::class),
                $app->make(BudgetAllocator::class),
            );
        });
    }

    public function boot(): void
    {
        if (!config('ads-campaign.enabled', true)) {
            return;
        }

        // Schedule recurring jobs
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Sync metrics from ad platforms every hour
            $schedule->job(new SyncAdsCampaignMetrics())
                ->hourly()
                ->name('ads:sync-metrics')
                ->withoutOverlapping()
                ->onOneServer();

            // Run optimization engine every 6 hours
            $schedule->job(new OptimizeAdsCampaigns())
                ->everySixHours()
                ->name('ads:optimize')
                ->withoutOverlapping()
                ->onOneServer();

            // Generate daily reports at 8 AM
            $schedule->job(new GenerateAdsReports())
                ->dailyAt(config('ads-campaign.scheduling.daily_report_time', '08:00'))
                ->name('ads:reports')
                ->withoutOverlapping()
                ->onOneServer();

            // Sync audience segments daily at 3 AM
            $schedule->job(new SyncAdsAudienceSegments())
                ->dailyAt('03:00')
                ->name('ads:audience-sync')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
