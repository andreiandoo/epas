<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Tics\TicsListingsService;
use Illuminate\Console\Command;

/**
 * Reîmprospătează din tics.ro pe ce alte platforme se vând evenimentele viitoare publicate
 * ale marketplace-urilor urmărite (services.tics.platforms).
 */
class SyncTicsListings extends Command
{
    protected $signature = 'tics:sync-listings {--marketplace= : doar acest marketplace_client_id} {--event= : doar acest eveniment}';

    protected $description = 'Sincronizează din tics.ro listările evenimentelor pe alte platforme de ticketing';

    public function handle(TicsListingsService $tics): int
    {
        if (! $tics->enabled()) {
            $this->warn('tics nu e configurat (TICS_INTERNAL_API_KEY).');

            return self::SUCCESS;
        }

        $marketplaceIds = array_keys(config('services.tics.platforms', []));
        if ($this->option('marketplace')) {
            $marketplaceIds = array_values(array_intersect($marketplaceIds, [(int) $this->option('marketplace')]));
        }
        if (empty($marketplaceIds)) {
            $this->warn('Niciun marketplace urmărit.');

            return self::SUCCESS;
        }

        $query = Event::query()->whereIn('marketplace_client_id', $marketplaceIds);
        if ($this->option('event')) {
            $query->whereKey((int) $this->option('event'));
        } else {
            $query->where('is_published', true)->upcoming();
        }

        $total = 0;
        $failed = 0;
        $query->select(['id', 'slug', 'marketplace_client_id'])
            ->orderBy('id')
            ->chunkById(500, function ($events) use ($tics, &$total, &$failed) {
                $total += $tics->sync($events);
                $failed += $tics->failedRequests;
            });

        $this->info("Evenimente actualizate: {$total}");
        if ($failed > 0) {
            $this->error("Cereri eșuate către tics: {$failed}. Ultima eroare: {$tics->lastError}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
