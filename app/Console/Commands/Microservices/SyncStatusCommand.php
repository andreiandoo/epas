<?php

namespace App\Console\Commands\Microservices;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStatusCommand extends Command
{
    protected $signature = 'microservices:sync-status {--tenant=}';

    protected $description = 'Sync microservice status for all or specific tenant';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $this->info('Syncing microservice statuses...');

        $query = DB::table('tenant_microservices')
            ->whereIn('status', ['active', 'trial']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $subscriptions = $query->get();

        $expired = 0;
        $expiring = 0;
        $active = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->expires_at && now()->isAfter($subscription->expires_at)) {
                // Expired
                DB::table('tenant_microservices')
                    ->where('id', $subscription->id)
                    ->update([
                        'status' => 'suspended',
                        'updated_at' => now(),
                    ]);
                $expired++;
            } elseif ($subscription->expires_at && now()->diffInDays($subscription->expires_at) <= 7) {
                // Expiring soon
                $expiring++;
            } else {
                $active++;
            }
        }

        $this->info("✓ Sync complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Active', $active],
                ['Expiring Soon (7 days)', $expiring],
                ['Expired & Suspended', $expired],
            ]
        );

        return self::SUCCESS;
    }
}
