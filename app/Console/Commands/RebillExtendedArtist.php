<?php

namespace App\Console\Commands;

use App\Models\MarketplaceArtistAccountMicroservice;
use Illuminate\Console\Command;

/**
 * Pentru abonamentele Extended Artist self_purchase a căror expires_at a trecut:
 * MARCHEAZĂ ca expired (fallback safe).
 *
 * NU declanșează automat re-charge prin Netopia — token-izare recurring în
 * platformă nu există încă. Conform planului Faza 1, fluxul real e:
 *   - artistul primește email cu link de re-plată înainte de expirare
 *   - dacă nu plătește la timp, accesul se taie aici
 *
 * Cand sistemul de re-charge automat va fi implementat, această comandă
 * va încerca întâi auto-charge și abia apoi (la eșec) va expira.
 *
 * Rândurile cancelate dar încă în perioada plătită rămân ATÂT TIMP CÂT
 * expires_at e în viitor — la depasire devin expired aici.
 */
class RebillExtendedArtist extends Command
{
    protected $signature = 'extended-artist:rebill-expired';

    protected $description = 'Expire Extended Artist subscriptions whose paid period has ended (placeholder for future auto-charge)';

    public function handle(): int
    {
        $now = now();

        $rows = MarketplaceArtistAccountMicroservice::query()
            ->whereIn('status', [
                MarketplaceArtistAccountMicroservice::STATUS_ACTIVE,
                MarketplaceArtistAccountMicroservice::STATUS_CANCELLED,
            ])
            ->where('granted_by', MarketplaceArtistAccountMicroservice::GRANTED_SELF_PURCHASE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No Extended Artist subscriptions to expire/rebill');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($rows as $row) {
            // TODO: cand integram tokenize-recurring, aici e locul:
            //   1. Try to charge stored payment method
            //   2. On success: create new ServiceOrder + extend expires_at
            //   3. On failure: mark expired + notify
            //
            // Pana atunci, doar marcam expired daca nu s-a re-platit manual.
            $row->update([
                'status' => MarketplaceArtistAccountMicroservice::STATUS_EXPIRED,
            ]);
            $count++;
        }

        $this->info("Expired Extended Artist self-purchase subscriptions: {$count}");
        return Command::SUCCESS;
    }
}
