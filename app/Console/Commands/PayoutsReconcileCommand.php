<?php

namespace App\Console\Commands;

use App\Filament\Marketplace\Resources\PayoutResource\Pages\ListPayouts;
use App\Models\Event;
use App\Models\MarketplacePayout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Payout reconciliation health-check.
 *
 * For every event that has payouts, compares the TRUE organizer net (computed
 * by SalesBreakdownService via ticket_type -> event, which is correct even for
 * multi-event orders whose order.event_id is NULL) against the sum of what the
 * payouts actually claim (amount + linked refund_amount, for approved /
 * processing / completed / pending payouts).
 *
 * Flags BOTH directions:
 *   - net > paid  => UNDER-paid  (organizer still owed)
 *   - net < paid  => OVER-paid   (the payouts page hides this via max(0,…))
 *
 * Read-only. Never writes anything. Run:
 *   php artisan payouts:reconcile
 *   php artisan payouts:reconcile --event=4690
 *   php artisan payouts:reconcile --threshold=5 --all
 */
class PayoutsReconcileCommand extends Command
{
    protected $signature = 'payouts:reconcile
        {--event= : Restrict to a single event id}
        {--threshold=1 : Absolute RON difference below which an event is considered OK}
        {--all : List every event, not just the discrepant ones}
        {--csv= : Write the full result to this CSV path instead of a table}';

    protected $description = 'Health-check: flag events where the payout total drifts from the real organizer net (catches multi-event / partial / drift issues).';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $onlyEvent = $this->option('event');

        // Statuses that represent a live claim on the event's revenue.
        $claimStatuses = ['completed', 'approved', 'processing', 'pending'];

        $eventIds = MarketplacePayout::query()
            ->when($onlyEvent, fn ($q) => $q->where('event_id', (int) $onlyEvent))
            ->whereNotNull('event_id')
            ->whereIn('status', $claimStatuses)
            ->distinct()
            ->orderBy('event_id')
            ->pluck('event_id');

        if ($eventIds->isEmpty()) {
            $this->info('Niciun eveniment cu deconturi de verificat.');
            return self::SUCCESS;
        }

        $this->info("Verific {$eventIds->count()} evenimente cu deconturi…");

        $rows = [];
        $flagged = 0;
        $totalUnder = 0.0;
        $totalOver = 0.0;

        foreach ($eventIds as $eventId) {
            $event = Event::find($eventId);
            if (!$event) {
                continue;
            }

            try {
                $fin = ListPayouts::calculateEventFinancials($event);
                $net = round((float) ($fin['net'] ?? 0), 2);
            } catch (\Throwable $e) {
                $rows[] = [$eventId, $this->title($event), 'EROARE', '-', '-', 'calc: ' . $e->getMessage()];
                $flagged++;
                continue;
            }

            // Total claimed = amount + linked refund_amount (money leaving Ambilet
            // in both directions for this event's slice), matching the balance math.
            $claims = (float) MarketplacePayout::where('event_id', $eventId)
                ->whereIn('status', $claimStatuses)
                ->sum(DB::raw('COALESCE(amount,0) + COALESCE(refund_amount,0)'));
            $claims = round($claims, 2);

            $diff = round($net - $claims, 2);
            $payoutCount = MarketplacePayout::where('event_id', $eventId)->whereIn('status', $claimStatuses)->count();

            if (abs($diff) <= $threshold) {
                $status = 'OK';
            } elseif ($diff > 0) {
                $status = 'SUB-PLATIT';
                $flagged++;
                $totalUnder += $diff;
            } else {
                $status = 'SUPRA-PLATIT';
                $flagged++;
                $totalOver += abs($diff);
            }

            if (!$this->option('all') && $status === 'OK') {
                continue;
            }

            $rows[] = [
                $eventId,
                $this->title($event),
                number_format($net, 2),
                number_format($claims, 2),
                ($diff >= 0 ? '+' : '') . number_format($diff, 2),
                $status . ($payoutCount > 1 ? " ({$payoutCount} deconturi)" : ''),
            ];
        }

        $headers = ['Event', 'Titlu', 'Net real', 'Platit', 'Diferenta', 'Status'];

        if ($csv = $this->option('csv')) {
            $fh = fopen($csv, 'w');
            fputcsv($fh, $headers);
            foreach ($rows as $r) {
                fputcsv($fh, $r);
            }
            fclose($fh);
            $this->info("CSV scris in: {$csv} (" . count($rows) . ' randuri)');
        } else {
            if (empty($rows)) {
                $this->info('✓ Toate evenimentele cu deconturi sunt reconciliate (in limita pragului).');
            } else {
                $this->table($headers, $rows);
            }
        }

        $this->newLine();
        $this->line("Evenimente semnalate: <fg=yellow>{$flagged}</> | Total sub-platit: <fg=yellow>" . number_format($totalUnder, 2) . " RON</> | Total supra-platit: <fg=red>" . number_format($totalOver, 2) . ' RON</>');

        return self::SUCCESS;
    }

    /**
     * Flatten a (possibly translatable) event title for terminal output.
     */
    protected function title(Event $event): string
    {
        $t = $event->title;
        if (is_array($t)) {
            $t = $t['ro'] ?? $t['en'] ?? (reset($t) ?: '');
        }
        if (method_exists($event, 'getTranslation') && (!is_string($t) || $t === '')) {
            try {
                $t = $event->getTranslation('title', 'ro');
            } catch (\Throwable $e) {
                // keep whatever we have
            }
        }

        return mb_substr((string) $t, 0, 45);
    }
}
