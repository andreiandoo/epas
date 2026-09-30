<?php

namespace App\Jobs;

use App\Models\MarketplaceOrganizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Recalculează soldul salvat al unui organizator (available_balance /
 * pending_balance) din date — exact ce face noaptea `balances:reconcile --fix`.
 *
 * De ce: la fiecare vânzare MarketplaceTransaction::recordSale adaugă în sold
 * `total − total × comision organizator`. Formula nu ține cont de comisionul
 * adăugat peste preț (se scade încă o dată: −0,50 lei la o comandă de 140 cu
 * 6% on-top), de asigurare / card cultural (numărate ca bani ai
 * organizatorului) sau de comisionul pe eveniment. Soldul salvat devia cu câțiva
 * lei pe zi până la recalcularea de noapte (org 586: −4,78 lei într-o zi).
 *
 * Rulează la ~1 minut după vânzare; vânzările apropiate pentru același
 * organizator se strâng într-o singură recalculare (unic până la procesare —
 * o vânzare venită în timpul recalculării programează încă una).
 */
class RecomputeOrganizerBalanceJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Jobul de noapte e plasa de siguranță — nu insistăm la eșec.
    public int $tries = 1;
    public int $timeout = 300;
    public int $uniqueFor = 600;

    public function __construct(public int $organizerId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->organizerId;
    }

    /** Programează recalcularea după ce tranzacția curentă e salvată. */
    public static function schedule(?int $organizerId): void
    {
        if (!$organizerId) {
            return;
        }

        try {
            static::dispatch($organizerId)->delay(now()->addMinute())->afterCommit();
        } catch (\Throwable $e) {
            // Nu blocăm niciodată o plată / rambursare din cauza recalculării.
            Log::warning('RecomputeOrganizerBalanceJob dispatch failed', [
                'organizer_id' => $organizerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handle(): void
    {
        $organizer = MarketplaceOrganizer::find($this->organizerId);
        if (!$organizer) {
            return;
        }

        $before = [(float) $organizer->available_balance, (float) $organizer->pending_balance];
        $derived = $organizer->recomputeBalances();

        if (abs($before[0] - $derived['available']) > 0.009 || abs($before[1] - $derived['pending']) > 0.009) {
            Log::info('Organizer balance recomputed', [
                'organizer_id' => $this->organizerId,
                'available' => [$before[0], $derived['available']],
                'pending' => [$before[1], $derived['pending']],
            ]);
        }
    }
}
