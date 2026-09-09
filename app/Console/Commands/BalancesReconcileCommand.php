<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Services\Marketplace\SalesBreakdownService;
use Illuminate\Console\Command;

/**
 * Organizer-balance reconciliation health-check.
 *
 * The incrementally-maintained ledger columns on marketplace_organizers
 * (available_balance / pending_balance, kept in sync via
 * reserveBalanceForPayout / recordPayoutCompleted / returnPendingBalance)
 * drift permanently the moment a single increment is missed or double-applied
 * — organizer 586 sits at available 94k / pending -39k while reality is
 * ~30.6k / 33.7k. This command computes the TRUE balance the same way the
 * /organizator/sold page does (SalesBreakdownService per event + payout sums)
 * and flags every organizer whose stored columns disagree.
 *
 *   TRUE net       = Σ SalesBreakdownService(event).total_net  over the org's events,
 *                    with legacy_import revenue excluded on events that have NO
 *                    Tixello decont (those were settled in the old system) and
 *                    kept on events that DO (settled here — revenue must offset
 *                    the payout, else it reads as a phantom over-payment)
 *   paid           = Σ completed payouts   (org-wide, incl. event_id = NULL)
 *   pending        = Σ approved + processing payouts (org-wide) — NOT 'pending',
 *                    which is the abandoned GenerateAutoDeconts auto-draft batch
 *   available_real = net − paid − pending   (may be NEGATIVE = over-paid, owes back)
 *   pending_real   = pending
 *
 * Read-only. Never writes. The --fix that recomputes the columns lands in a
 * later step, only after this scan shows the scope.
 *
 *   php artisan balances:reconcile
 *   php artisan balances:reconcile --organizer=586
 *   php artisan balances:reconcile --threshold=5 --csv=/tmp/bal.csv
 */
class BalancesReconcileCommand extends Command
{
    protected $signature = 'balances:reconcile
        {--organizer= : Restrict to a single organizer id}
        {--threshold=1 : Absolute RON drift below which an organizer is considered OK}
        {--all : List every scanned organizer, not just the drifting ones}
        {--limit= : Stop after scanning this many organizers (safety valve on huge marketplaces)}
        {--csv= : Write the full result to this CSV path instead of a table}
        {--fix : WRITE the derived balance into available_balance / pending_balance for every drifting organizer}';

    protected $description = 'Health-check (add --fix to repair): flag organizers whose stored available/pending balance drifts from the real (derived) balance.';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $onlyOrganizer = $this->option('organizer');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $service = app(SalesBreakdownService::class);

        // Only organizers that could plausibly have a balance: an event, a
        // payout, or a non-zero stored column. Skips the long tail of empty
        // organizer rows so the SalesBreakdownService loop stays bounded.
        $organizers = MarketplaceOrganizer::query()
            ->when($onlyOrganizer, fn ($q) => $q->where('id', (int) $onlyOrganizer))
            ->when(!$onlyOrganizer, function ($q) {
                $q->where(function ($w) {
                    $w->where('available_balance', '!=', 0)
                      ->orWhere('pending_balance', '!=', 0)
                      ->orWhereHas('events')
                      ->orWhereHas('payouts');
                });
            })
            ->orderBy('id')
            ->get();

        if ($organizers->isEmpty()) {
            $this->info('Niciun organizator de verificat.');
            return self::SUCCESS;
        }

        $total = $limit !== null ? min($limit, $organizers->count()) : $organizers->count();
        $this->info("Verific {$total} organizatori…");

        if ($this->option('fix')) {
            $this->warn('MOD --fix ACTIV: available_balance / pending_balance vor fi REscrise pentru fiecare organizator cu drift.');
        }

        $rows = [];
        $flagged = 0;
        $totalAbsDrift = 0.0;
        $scanned = 0;

        foreach ($organizers as $organizer) {
            if ($limit !== null && $scanned >= $limit) {
                break;
            }
            $scanned++;

            try {
                $derived = $organizer->deriveBalances($service);
                $netReal = $derived['net'];
                $paid = $derived['paid'];
                $pending = $derived['pending'];
            } catch (\Throwable $e) {
                $rows[] = [$organizer->id, $this->name($organizer), 'EROARE', '-', '-', '-', '-', '-', 'calc: ' . mb_substr($e->getMessage(), 0, 40)];
                $flagged++;
                continue;
            }

            $availableReal = round($netReal - $paid - $pending, 2);
            $pendingReal = round($pending, 2);

            $dbAvailable = round((float) $organizer->available_balance, 2);
            $dbPending = round((float) $organizer->pending_balance, 2);

            $driftAvailable = round($dbAvailable - $availableReal, 2);
            $driftPending = round($dbPending - $pendingReal, 2);
            $worstDrift = max(abs($driftAvailable), abs($driftPending));

            if ($worstDrift <= $threshold) {
                $status = 'OK';
            } else {
                $status = 'DRIFT';
                $flagged++;
                $totalAbsDrift += $worstDrift;

                // --fix turns the ledger columns into a cache of the derived
                // truth. Only drifting organizers are written, so a re-run
                // after a successful fix is a no-op.
                if ($this->option('fix')) {
                    $organizer->recomputeBalances($service);
                    $status = 'REPARAT';
                }
            }

            if (!$this->option('all') && $status === 'OK') {
                continue;
            }

            $rows[] = [
                $organizer->id,
                $this->name($organizer),
                number_format($dbAvailable, 2),
                number_format($availableReal, 2),
                ($driftAvailable >= 0 ? '+' : '') . number_format($driftAvailable, 2),
                number_format($dbPending, 2),
                number_format($pendingReal, 2),
                ($driftPending >= 0 ? '+' : '') . number_format($driftPending, 2),
                $status,
            ];
        }

        $headers = ['Org', 'Nume', 'DB disp.', 'Real disp.', 'Drift disp.', 'DB proc.', 'Real proc.', 'Drift proc.', 'Status'];

        if ($csv = $this->option('csv')) {
            $fh = fopen($csv, 'w');
            fputcsv($fh, $headers);
            foreach ($rows as $r) {
                fputcsv($fh, $r);
            }
            fclose($fh);
            $this->info("CSV scris in: {$csv} (" . count($rows) . ' randuri)');
        } elseif (empty($rows)) {
            $this->info('✓ Toate soldurile verificate sunt reconciliate (in limita pragului).');
        } else {
            $this->table($headers, $rows);
        }

        $this->newLine();
        $this->line("Organizatori verificati: <fg=cyan>{$scanned}</> | cu drift: <fg=yellow>{$flagged}</> | drift absolut total (cel mai mare per org): <fg=red>" . number_format($totalAbsDrift, 2) . ' RON</>');

        return self::SUCCESS;
    }

    protected function name(MarketplaceOrganizer $organizer): string
    {
        $name = $organizer->company_name ?: $organizer->name ?: $organizer->email ?: ('#' . $organizer->id);

        return mb_substr((string) $name, 0, 30);
    }
}
