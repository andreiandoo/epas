<?php

namespace App\Console\Commands;

use App\Services\Cache\MicroservicesCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmMicroservicesCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-microservices
                            {--tenant= : Warm cache for specific tenant}
                            {--global : Warm global caches only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up microservices caches to improve performance';

    /**
     * Execute the console command.
     */
    public function handle(MicroservicesCacheService $cacheService): int
    {
        if (!config('microservices.cache.enabled', true)) {
            $this->warn('Microservices caching is disabled.');
            return Command::FAILURE;
        }

        $this->info('Warming microservices caches...');
        $this->newLine();

        // Warm global caches
        if ($this->option('global') || !$this->option('tenant')) {
            $this->line('Warming global caches...');
            $cacheService->warmGlobalCache();
            $this->info('✓ Global caches warmed');
        }

        // Warm tenant-specific caches
        if (!$this->option('global')) {
            $tenantId = $this->option('tenant');

            if ($tenantId) {
                $this->line("Warming cache for tenant: {$tenantId}");
                $cacheService->warmTenantCache($tenantId);
                $this->info("✓ Tenant cache warmed: {$tenantId}");
            } else {
                // Warm all active tenants
                $tenants = DB::table('tenants')
                    ->whereNotNull('id')
                    ->limit(100) // Limit to avoid overwhelming the cache
                    ->pluck('id');

                $bar = $this->output->createProgressBar($tenants->count());
                $bar->start();

                foreach ($tenants as $id) {
                    $cacheService->warmTenantCache($id);
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
                $this->info("✓ Warmed caches for {$tenants->count()} tenants");
            }
        }

        $this->newLine();

        // Show cache stats
        $stats = $cacheService->getStats();
        $this->info('Cache Statistics:');
        $this->line("Driver: {$stats['driver']}");

        if (isset($stats['cached_items'])) {
            foreach ($stats['cached_items'] as $prefix => $count) {
                $this->line("  {$prefix}: {$count} items");
            }
            $this->line("  Total: {$stats['total_keys']} items");
        }

        return Command::SUCCESS;
    }
}
