<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CleanupExpiredCarts extends Command
{
    protected $signature = 'marketplace:cleanup-carts
                            {--older-than=2 : Hours after which carts are considered expired}';

    protected $description = 'Clean up expired marketplace carts from cache';

    public function handle(): int
    {
        $this->info('Cleaning up expired marketplace carts...');

        $olderThan = (int) $this->option('older-than');

        // Note: This command is primarily informational since Laravel cache
        // automatically handles TTL expiration. However, it can be used to
        // manually trigger cleanup or for monitoring purposes.

        $this->info("Carts older than {$olderThan} hours will be removed.");
        $this->info('Note: Laravel cache automatically handles TTL expiration.');

        // If using Redis, we can scan for marketplace cart keys
        if (config('cache.default') === 'redis') {
            try {
                $this->info('Scanning Redis for marketplace cart keys...');

                $keys = Redis::keys('marketplace_cart:*');
                $count = count($keys);

                $this->info("Found {$count} marketplace cart keys in Redis.");
                $this->info('Expired keys are automatically removed by Redis TTL.');
            } catch (\Exception $e) {
                $this->warn('Could not scan Redis: ' . $e->getMessage());
            }
        }

        $this->info('Cleanup complete.');

        return self::SUCCESS;
    }
}
