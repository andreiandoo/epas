<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detects a customer's likely home city from their order history and
 * stores the result in marketplace_customers.settings.detected_location.
 *
 * Metric: orders per city (each order = one "I attended an event here"
 * decision — stronger signal than tickets per order, which conflates
 * group buys with personal attendance).
 *
 * Storage:
 *   settings.detected_location = {
 *     determined_city: string|null,    // top city when its pct ≥ 85%, else null
 *     determined_at: ISO timestamp,
 *     distribution: [                   // top 3 cities; just 1 entry when top is 100%
 *       { city_id, city_name, orders, pct },
 *       ...
 *     ]
 *   }
 *
 * Designed to run on the full Ambilet customer base (~97k) in single-
 * digit minutes via a single bulk aggregation query + chunked writes.
 *
 * Usage:
 *   php artisan customers:detect-cities --marketplace=1
 *   php artisan customers:detect-cities --marketplace=1 --dry-run
 *   php artisan customers:detect-cities --marketplace=1 --customer=12345
 */
class DetectCustomerCitiesCommand extends Command
{
    protected $signature = 'customers:detect-cities
                            {--marketplace= : marketplace_client_id to scan}
                            {--customer= : run only for this single customer id (debug)}
                            {--dry-run : compute everything, don\'t write to DB}';

    protected $description = 'Detect the most likely home city per customer from order history';

    public const SUCCESS_STATUSES = ['paid', 'confirmed', 'completed', 'partially_refunded'];
    public const DETERMINED_THRESHOLD_PCT = 85.0;
    public const SHOW_TOP_N = 3;

    public function handle(): int
    {
        $marketplaceId = (int) $this->option('marketplace');
        if ($marketplaceId <= 0) {
            $this->error('--marketplace=N is required.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $singleCustomer = $this->option('customer') ? (int) $this->option('customer') : null;

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Detecting cities for marketplace #{$marketplaceId}"
            . ($singleCustomer ? " (customer #{$singleCustomer} only)" : ''));

        $start = microtime(true);

        // Single bulk aggregation. The COALESCE between events and
        // marketplace_events handles Ambilet (uses marketplace_event_id)
        // and the newer marketplaces (use event_id) uniformly.
        $sql = <<<SQL
            SELECT
                o.marketplace_customer_id AS customer_id,
                COALESCE(e.marketplace_city_id, me.marketplace_city_id) AS city_id,
                COALESCE(c1.name, c2.name) AS city_name,
                COUNT(DISTINCT o.id) AS order_count
            FROM orders o
            LEFT JOIN events e ON e.id = o.event_id
            LEFT JOIN marketplace_events me ON me.id = o.marketplace_event_id
            LEFT JOIN marketplace_cities c1 ON c1.id = e.marketplace_city_id
            LEFT JOIN marketplace_cities c2 ON c2.id = me.marketplace_city_id
            WHERE o.marketplace_client_id = ?
              AND o.status IN ('paid','confirmed','completed','partially_refunded')
              AND o.marketplace_customer_id IS NOT NULL
              AND COALESCE(e.marketplace_city_id, me.marketplace_city_id) IS NOT NULL
              %CUSTOMER_FILTER%
            GROUP BY o.marketplace_customer_id,
                     COALESCE(e.marketplace_city_id, me.marketplace_city_id),
                     COALESCE(c1.name, c2.name)
        SQL;

        $bindings = [$marketplaceId];
        if ($singleCustomer) {
            $sql = str_replace('%CUSTOMER_FILTER%', 'AND o.marketplace_customer_id = ?', $sql);
            $bindings[] = $singleCustomer;
        } else {
            $sql = str_replace('%CUSTOMER_FILTER%', '', $sql);
        }

        $this->line('Running aggregation query...');
        $rows = DB::select($sql, $bindings);
        $aggSec = round(microtime(true) - $start, 2);
        $this->line("  {$aggSec}s · " . count($rows) . " (customer × city) rows");

        if (empty($rows)) {
            $this->warn('No qualifying orders found.');
            return self::SUCCESS;
        }

        // Group by customer in-memory. The per-customer list is bounded
        // by the number of distinct cities they bought tickets in —
        // realistic max ~50, typical 1-5. Cheap to process.
        $perCustomer = [];
        foreach ($rows as $r) {
            $perCustomer[(int) $r->customer_id][] = [
                'city_id' => (int) $r->city_id,
                'city_name' => (string) $r->city_name,
                'orders' => (int) $r->order_count,
            ];
        }

        $this->line(count($perCustomer) . ' customers have at least one geocoded order');

        // Compute distribution + determined_city per customer.
        $payloads = [];
        $determined = 0;
        $hundredPct = 0;
        foreach ($perCustomer as $customerId => $cities) {
            $total = array_sum(array_column($cities, 'orders'));
            if ($total === 0) continue;

            usort($cities, fn ($a, $b) => $b['orders'] <=> $a['orders']
                ?: strcmp($a['city_name'], $b['city_name']));

            foreach ($cities as &$c) {
                $c['pct'] = round($c['orders'] / $total * 100, 2);
            }
            unset($c);

            $top = $cities[0];

            // 100% case: only ever bought in one city — collapse the
            // distribution to that single entry, no point listing others
            // (they have 0 orders or don't exist).
            $is100 = $top['pct'] >= 99.99;
            $topN = $is100 ? [$top] : array_slice($cities, 0, self::SHOW_TOP_N);

            $determinedCity = $top['pct'] >= self::DETERMINED_THRESHOLD_PCT ? $top['city_name'] : null;
            if ($determinedCity) $determined++;
            if ($is100) $hundredPct++;

            $payloads[$customerId] = [
                'determined_city' => $determinedCity,
                'determined_at' => now()->toIso8601String(),
                'distribution' => $topN,
            ];
        }

        $this->line('Determined city (≥85%): ' . $determined);
        $this->line('Single-city customers (100%): ' . $hundredPct);

        if ($dryRun) {
            $this->info("[DRY RUN] Skipping writes. Sample of 3 payloads:");
            foreach (array_slice($payloads, 0, 3, true) as $cid => $p) {
                $this->line("  Customer #{$cid}: " . json_encode($p, JSON_UNESCAPED_UNICODE));
            }
            $this->line('Total time: ' . round(microtime(true) - $start, 2) . 's');
            return self::SUCCESS;
        }

        // Write in chunks using Postgres jsonb_set so we don't have to
        // read existing settings into PHP just to merge one key. The
        // VALUES table is built once per chunk; CASE clauses keep the
        // bound-param count under Postgres' 65535 limit.
        $this->line('Writing detected_location to ' . count($payloads) . ' customers...');
        $writeStart = microtime(true);

        $bar = $this->output->createProgressBar(count($payloads));
        $bar->start();

        foreach (array_chunk($payloads, 500, true) as $chunk) {
            DB::transaction(function () use ($chunk) {
                // Build a single UPDATE with CASE per row. 500 rows / chunk
                // = ~1000 bound params (id + json each). Safe under PG limits.
                $ids = [];
                $caseClauses = [];
                $bindings = [];
                foreach ($chunk as $customerId => $payload) {
                    $ids[] = (int) $customerId;
                    $caseClauses[] = 'WHEN id = ? THEN jsonb_set(COALESCE(settings, \'{}\'::jsonb), \'{detected_location}\', ?::jsonb)';
                    $bindings[] = (int) $customerId;
                    $bindings[] = json_encode($payload, JSON_UNESCAPED_UNICODE);
                }
                $idsList = implode(',', $ids);
                $caseSql = implode(' ', $caseClauses);

                DB::statement(
                    "UPDATE marketplace_customers SET settings = CASE {$caseSql} END WHERE id IN ({$idsList})",
                    $bindings
                );
            });
            $bar->advance(count($chunk));
        }
        $bar->finish();
        $this->newLine();

        $writeSec = round(microtime(true) - $writeStart, 2);
        $totalSec = round(microtime(true) - $start, 2);
        $this->info("Done. Aggregation {$aggSec}s + write {$writeSec}s = total {$totalSec}s");

        return self::SUCCESS;
    }
}
