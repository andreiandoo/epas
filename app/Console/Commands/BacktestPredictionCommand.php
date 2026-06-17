<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Replays the dashboard sales-prediction algorithm against historical
 * months so we can see where it under/over-shoots reality. For each
 * cutoff day, we "freeze" knowledge at that day and ask the predictor
 * for the rest of the month, then compare to the actual outcome.
 *
 * Runs the algorithm in two flavors:
 *   - date-aligned (the pre-2026-06-16 logic — prev[i] for current day i)
 *   - dow-aligned  (the new logic — prev[i + dowShift] for current day i)
 * so we can also see the impact of the shift fix in isolation.
 *
 * Example:
 *   php artisan dashboard:backtest-prediction --marketplace=1 --month=2025-05
 *   php artisan dashboard:backtest-prediction --marketplace=1 --month=2025-04 --cutoffs=3,7,14,21,28
 */
class BacktestPredictionCommand extends Command
{
    protected $signature = 'dashboard:backtest-prediction
                            {--marketplace= : marketplace_client_id (required)}
                            {--month= : YYYY-MM to backtest (default last calendar month)}
                            {--cutoffs=5,10,15,20,25 : days to freeze knowledge at}
                            {--exclude-legacy : exclude legacy_import orders (default keeps them; pre-2026 Ambilet data is ~all legacy_import and would zero out)}';

    protected $description = 'Replay the dashboard sales prediction against a historical month and report errors';

    public function handle(): int
    {
        $marketplaceId = (int) $this->option('marketplace');
        if ($marketplaceId <= 0) {
            $this->error('--marketplace=N is required.');
            return self::FAILURE;
        }

        $tz = 'Europe/Bucharest';
        $monthOpt = $this->option('month') ?: Carbon::now($tz)->copy()->subMonth()->format('Y-m');
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $monthOpt, $tz)->startOfMonth();
        } catch (\Throwable $e) {
            $this->error("Invalid --month format. Use YYYY-MM.");
            return self::FAILURE;
        }
        $monthEnd = $monthStart->copy()->endOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

        $prevMonthStart = $monthStart->copy()->subYear();
        $prevMonthEnd = $prevMonthStart->copy()->endOfMonth();
        $prevDaysInMonth = $prevMonthStart->daysInMonth;

        $dowShift = (((int) $monthStart->dayOfWeek - (int) $prevMonthStart->dayOfWeek) + 7) % 7;

        $cutoffs = array_filter(array_map('intval', explode(',', (string) $this->option('cutoffs'))));
        $cutoffs = array_values(array_filter($cutoffs, fn ($c) => $c > 0 && $c < $daysInMonth));

        $this->line("Backtesting marketplace #{$marketplaceId} — {$monthStart->format('Y-m')} ({$monthStart->englishDayOfWeek}-start)");
        $this->line("  prev year same month: {$prevMonthStart->format('Y-m')} ({$prevMonthStart->englishDayOfWeek}-start)");
        $this->line("  DOW shift between years: +{$dowShift} day(s)");
        $this->line("  cutoffs: " . implode(',', $cutoffs));
        $this->line('');

        // Pull daily sales for both months. Default keeps legacy_import
        // because pre-2026 Ambilet is migrated data — excluding zeros
        // out the entire backtest. The live dashboard already mixes both
        // (current excludes legacy, prev includes it) — for backtest we
        // hold the filter constant across both years so the comparison
        // is apples-to-apples.
        $excludeLegacy = (bool) $this->option('exclude-legacy');
        $current = $this->fetchDailySales($marketplaceId, $monthStart, $monthEnd, $tz, $excludeLegacy);
        $prev    = $this->fetchDailySales($marketplaceId, $prevMonthStart, $prevMonthEnd, $tz, $excludeLegacy);

        $this->line(sprintf('  legacy_import filter: %s', $excludeLegacy ? 'EXCLUDED' : 'INCLUDED'));

        // Pad to month length
        $current = array_pad($current, $daysInMonth, 0.0);
        $prev    = array_pad($prev, $prevDaysInMonth, 0.0);

        $actualTotal = array_sum($current);
        $prevTotal   = array_sum($prev);

        $this->line(sprintf('  Real total %s:       %s RON', $monthStart->format('Y-m'), number_format($actualTotal, 2)));
        $this->line(sprintf('  Real total %s:       %s RON (prev year ref)', $prevMonthStart->format('Y-m'), number_format($prevTotal, 2)));
        $this->line(sprintf('  Year-over-year growth: %+.1f%%', $prevTotal > 0 ? (($actualTotal / $prevTotal) - 1) * 100 : 0));
        $this->line('');

        // Per-DOW totals — useful diagnostic for "which DOW has biggest swings"
        $this->line('Per-DOW totals (Mon-Sun):');
        $perDowCurr = [0,0,0,0,0,0,0];
        $perDowPrev = [0,0,0,0,0,0,0];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
            $perDowCurr[$dow] += $current[$i];
        }
        for ($i = 0; $i < $prevDaysInMonth; $i++) {
            $dow = (int) $prevMonthStart->copy()->addDays($i)->dayOfWeek;
            $perDowPrev[$dow] += $prev[$i];
        }
        $names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        foreach ([1,2,3,4,5,6,0] as $dow) {
            $c = $perDowCurr[$dow]; $p = $perDowPrev[$dow];
            $grow = $p > 0 ? (($c/$p)-1)*100 : 0;
            $this->line(sprintf('  %s: curr=%s  prev=%s  growth=%+.1f%%',
                $names[$dow], number_format($c,2), number_format($p,2), $grow));
        }
        $this->line('');

        // Backtest each cutoff with three alignment / smoothing modes
        $this->line('Backtest per cutoff (predicted full-month vs actual):');
        $header = sprintf('  %-8s | %-22s | %-22s | %-22s | %s',
            'cutoff', 'OLD (date-aligned)', 'NEW (dow-aligned +' . $dowShift . ')',
            'PROPOSED (caps+geomean+MTD)', 'actual');
        $this->line($header);
        $this->line('  ' . str_repeat('-', strlen($header) - 2));
        foreach ($cutoffs as $cutoff) {
            $predOld = $this->predict($current, $prev, $cutoff, $daysInMonth, $prevDaysInMonth, $monthStart, $tz, /*shift*/ 0);
            $predNew = $this->predict($current, $prev, $cutoff, $daysInMonth, $prevDaysInMonth, $monthStart, $tz, /*shift*/ $dowShift);
            $predPro = $this->predictProposed($current, $prev, $cutoff, $daysInMonth, $prevDaysInMonth, $monthStart, $tz, $dowShift);
            $oldSum = array_sum($predOld);
            $newSum = array_sum($predNew);
            $proSum = array_sum($predPro);
            $oldErr = (($oldSum - $actualTotal) / max($actualTotal, 1)) * 100;
            $newErr = (($newSum - $actualTotal) / max($actualTotal, 1)) * 100;
            $proErr = (($proSum - $actualTotal) / max($actualTotal, 1)) * 100;
            $this->line(sprintf('  day %-4d | %12s (%+.1f%%) | %12s (%+.1f%%) | %12s (%+.1f%%) | %s',
                $cutoff,
                number_format($oldSum, 0), $oldErr,
                number_format($newSum, 0), $newErr,
                number_format($proSum, 0), $proErr,
                number_format($actualTotal, 0)
            ));
        }
        $this->line('');

        // Per-day breakdown at the LATEST cutoff for the PROPOSED model
        $lastCutoff = end($cutoffs) ?: max(5, intdiv($daysInMonth, 2));
        $predLast = $this->predictProposed($current, $prev, $lastCutoff, $daysInMonth, $prevDaysInMonth, $monthStart, $tz, $dowShift);
        $this->line("Per-day errors at cutoff day {$lastCutoff} (PROPOSED model):");
        $this->line('  day | DOW | predicted | actual    | error    | error%');
        $this->line('  ' . str_repeat('-', 60));
        for ($i = $lastCutoff; $i < $daysInMonth; $i++) {
            $dow = $names[(int) $monthStart->copy()->addDays($i)->dayOfWeek];
            $pred = $predLast[$i] ?? 0;
            $act  = $current[$i] ?? 0;
            $err  = $pred - $act;
            $errPct = $act > 0 ? ($err / $act) * 100 : ($pred > 0 ? 999 : 0);
            $this->line(sprintf('  %2d  | %s | %9s | %9s | %+9s | %+.1f%%',
                $i + 1,
                $dow,
                number_format($pred, 0),
                number_format($act, 0),
                number_format($err, 0),
                $errPct
            ));
        }

        return self::SUCCESS;
    }

    private function fetchDailySales(int $marketplaceId, Carbon $start, Carbon $end, string $tz, bool $excludeLegacy = false): array
    {
        $excluded = $excludeLegacy
            ? ['test_order', 'external_import', 'legacy_import']
            : ['test_order', 'external_import'];
        $rows = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotIn('source', $excluded)
            ->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->selectRaw("DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE '{$tz}') as date, SUM(total) as total")
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();
        $out = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $out[] = (float) ($rows[$cursor->format('Y-m-d')] ?? 0);
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * Replay the dashboard prediction with a chosen DOW shift.
     * shift = 0 → reproduces the pre-fix date-aligned behavior.
     * shift = N → DOW-aligned (look up prev[i + N]).
     *
     * Returns the full-month series (past + cutoff day + future).
     */
    private function predict(
        array $current,
        array $prev,
        int $cutoffDay,
        int $daysInMonth,
        int $prevDaysInMonth,
        Carbon $monthStart,
        string $tz,
        int $shift
    ): array {
        $prevAt = function (int $i) use ($prev, $shift, $prevDaysInMonth): float {
            $idx = $i + $shift;
            if ($idx < 0 || $idx >= count($prev) || $idx >= $prevDaysInMonth) return 0.0;
            return (float) ($prev[$idx] ?? 0);
        };

        $dowRatios = [];
        $allRatios = [];
        $completedTotal = 0.0;
        $completedDays = 0;
        for ($i = 0; $i < $cutoffDay; $i++) {
            $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
            $curr = (float) ($current[$i] ?? 0);
            $prv  = $prevAt($i);
            if ($prv > 0 && $curr > 0) {
                $dowRatios[$dow][] = $curr / $prv;
                $allRatios[] = $curr / $prv;
            }
            $completedTotal += $curr;
            if ($curr > 0) $completedDays++;
        }
        $dowGrowth = [];
        for ($dow = 0; $dow < 7; $dow++) {
            $r = $dowRatios[$dow] ?? [];
            $dowGrowth[$dow] = !empty($r) ? array_sum($r) / count($r) : null;
        }
        $overallGrowth = !empty($allRatios) ? array_sum($allRatios) / count($allRatios) : null;
        $overallAvg    = $completedDays > 0 ? $completedTotal / $completedDays : 0.0;

        $out = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            if ($i < $cutoffDay) {
                // "Past" — actual data fully known
                $out[] = (float) ($current[$i] ?? 0);
            } else {
                $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
                $growth = $dowGrowth[$dow] ?? $overallGrowth ?? 1.0;
                $prv = $prevAt($i);
                if ($prv > 0 && ($dowGrowth[$dow] !== null || $overallGrowth !== null)) {
                    $out[] = round($prv * $growth);
                } else {
                    $out[] = round($overallAvg);
                }
            }
        }
        return $out;
    }

    /**
     * Proposed predictor — A+B+C combined:
     *
     *   A. Cap on every growth ratio at [0.3, 3.5] — anything outside is
     *      a likely outlier (event-driven spike, rare promo, data error)
     *      and bleeding it forward poisons every future day.
     *
     *   B. Geometric mean (not arithmetic) when 2+ samples available —
     *      sqrt(0.5 × 5) = 1.58 vs arithmetic 2.75. Far more robust to
     *      single big-day spikes that dominate early-month ratios.
     *
     *   C. Month-to-date pacing for cutoff < 7 — when we don't have
     *      enough completed days for the per-DOW model to be statistically
     *      meaningful (max ~1 sample per DOW after 5 days), fall back to
     *      "we're at X% of last year's same-cutoff pace, project pro-rata".
     *      Smoother extrapolation that ignores DOW-level noise until
     *      enough data accumulates.
     */
    private function predictProposed(
        array $current,
        array $prev,
        int $cutoffDay,
        int $daysInMonth,
        int $prevDaysInMonth,
        Carbon $monthStart,
        string $tz,
        int $shift
    ): array {
        $prevAt = function (int $i) use ($prev, $shift, $prevDaysInMonth): float {
            $idx = $i + $shift;
            if ($idx < 0 || $idx >= count($prev) || $idx >= $prevDaysInMonth) return 0.0;
            return (float) ($prev[$idx] ?? 0);
        };

        $clamp = fn (float $x): float => max(0.3, min(3.5, $x));
        $geomean = function (array $vals) {
            $vals = array_filter($vals, fn ($v) => $v > 0);
            if (empty($vals)) return null;
            if (count($vals) === 1) return reset($vals);
            $sumLog = 0.0;
            foreach ($vals as $v) $sumLog += log($v);
            return exp($sumLog / count($vals));
        };

        // Collect per-DOW ratios + overall MTD bookkeeping
        $dowRatios = [];
        $allRatios = [];
        $completedTotal = 0.0;
        $completedDays = 0;
        for ($i = 0; $i < $cutoffDay; $i++) {
            $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
            $curr = (float) ($current[$i] ?? 0);
            $prv  = $prevAt($i);
            if ($prv > 0 && $curr > 0) {
                $dowRatios[$dow][] = $curr / $prv;
                $allRatios[] = $curr / $prv;
            }
            $completedTotal += $curr;
            if ($curr > 0) $completedDays++;
        }
        $dowGrowth = [];
        for ($dow = 0; $dow < 7; $dow++) {
            $g = $geomean($dowRatios[$dow] ?? []);
            $dowGrowth[$dow] = $g !== null ? $clamp($g) : null;
        }
        $overallGeoGrowth = $geomean($allRatios);
        $overallGrowth = $overallGeoGrowth !== null ? $clamp($overallGeoGrowth) : null;
        $overallAvg = $completedDays > 0 ? $completedTotal / $completedDays : 0.0;

        // Early cutoff — use MTD pacing instead of per-day extrapolation.
        // Prev MTD aligned by shift so the pace ratio uses same-DOW days.
        $useMtdPacing = $cutoffDay < 7;
        $mtdRemaining = null;
        if ($useMtdPacing) {
            $currMtd = $completedTotal;
            $prevMtdAligned = 0.0;
            for ($j = 0; $j < $cutoffDay; $j++) $prevMtdAligned += $prevAt($j);
            $prevTotalAligned = 0.0;
            for ($j = 0; $j < $daysInMonth; $j++) $prevTotalAligned += $prevAt($j);
            if ($prevMtdAligned > 0 && $prevTotalAligned > 0) {
                $pacePct = $prevMtdAligned / $prevTotalAligned;
                $projectedTotal = $currMtd / $pacePct;
                $mtdRemaining = max(0.0, $projectedTotal - $currMtd);
            }
        }

        $out = [];
        // Pre-compute prev remaining sum (used for proportional pacing distribution)
        $prevRemainingSum = 0.0;
        for ($j = $cutoffDay; $j < $daysInMonth; $j++) $prevRemainingSum += $prevAt($j);

        for ($i = 0; $i < $daysInMonth; $i++) {
            if ($i < $cutoffDay) {
                $out[] = (float) ($current[$i] ?? 0);
                continue;
            }

            // Pacing path — distribute MTD-projected remaining proportionally
            // to prev's day weights so DOW patterns still show through.
            if ($useMtdPacing && $mtdRemaining !== null && $prevRemainingSum > 0) {
                $weight = $prevAt($i) / $prevRemainingSum;
                $out[] = round($mtdRemaining * $weight);
                continue;
            }

            // Per-day path with capped + geomean growth.
            $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
            $growth = $dowGrowth[$dow] ?? $overallGrowth ?? 1.0;
            $prv = $prevAt($i);
            if ($prv > 0 && ($dowGrowth[$dow] !== null || $overallGrowth !== null)) {
                $out[] = round($prv * $growth);
            } else {
                $out[] = round($overallAvg);
            }
        }
        return $out;
    }
}
