<?php

namespace App\Console\Commands;

use App\Models\MarketplaceArtistAccountMicroservice;
use Illuminate\Console\Command;

/**
 * Marchează ca expired trial-urile Extended Artist a căror trial_ends_at
 * a trecut. Rulează zilnic (sau mai des dacă vrei feedback rapid în portal
 * artist).
 *
 * Nu trimite emailuri aici (separare responsabilitate). Cand sistemul de
 * notificari va fi activ, va fi un listener pe model event.
 */
class ExpireExtendedArtistTrials extends Command
{
    protected $signature = 'extended-artist:expire-trials';

    protected $description = 'Mark expired Extended Artist trials as status=expired';

    public function handle(): int
    {
        $now = now();

        $rows = MarketplaceArtistAccountMicroservice::query()
            ->where('status', MarketplaceArtistAccountMicroservice::STATUS_TRIAL)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No Extended Artist trials to expire');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($rows as $row) {
            $row->update([
                'status' => MarketplaceArtistAccountMicroservice::STATUS_EXPIRED,
            ]);
            $count++;
        }

        $this->info("Expired Extended Artist trials: {$count}");
        return Command::SUCCESS;
    }
}
