<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sterge datele generate de tixello-stress (k6 stress testing suite).
 *
 * Identifica datele dupa marker-ele:
 *   - email LIKE 'k6stress_%' sau LIKE '%@stress.test'
 *   - session_id LIKE 'k6\_%'
 *
 * Idempotent. Suporta --dry-run.
 */
class StressCleanupCommand extends Command
{
    protected $signature = 'stress:cleanup
                            {--dry-run : Doar afiseaza ce ar fi sters, fara modificari}
                            {--since=1h : Limita temporala (ex: 1h, 24h, 7d)}';

    protected $description = 'Sterge datele generate de tixello-stress k6 suite';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $since = $this->parseSince($this->option('since'));

        $this->info($dryRun ? '🧪 DRY RUN — nimic nu se va sterge' : '🧹 LIVE — sterg datele');
        $this->info("   Limita temporala: created_at >= {$since->toDateTimeString()}");
        $this->newLine();

        $totals = [];

        // 1. Users / customers cu email marker
        $totals['users'] = $this->cleanByEmail('users', $since, $dryRun);
        $totals['customers'] = $this->cleanByEmail('customers', $since, $dryRun);

        // 2. Orders
        $totals['orders'] = $this->cleanByEmailColumn('orders', 'customer_email', $since, $dryRun);
        $totals['orders_email'] = $this->cleanByEmailColumn('orders', 'email', $since, $dryRun);

        // 3. Email logs
        $totals['email_logs'] = $this->cleanByEmailColumn('email_logs', 'to', $since, $dryRun);
        $totals['email_logs_recipient'] = $this->cleanByEmailColumn('email_logs', 'recipient', $since, $dryRun);

        // 4. Seat holds cu session marker
        if (Schema::hasTable('seat_holds')) {
            $q = DB::table('seat_holds')
                ->where(function ($q) {
                    $q->where('session_id', 'like', 'k6\_%')
                      ->orWhere('session_id', 'like', 'k6stress%');
                })
                ->where('created_at', '>=', $since);
            $count = $q->count();
            if (! $dryRun && $count > 0) $q->delete();
            $totals['seat_holds'] = $count;
        }

        // 5. Event seats blocate de stress test — revert la 'available'
        // (numai daca tabela are status si exista holds asociate marker-elor noastre)
        if (Schema::hasTable('event_seats') && Schema::hasColumn('event_seats', 'status')) {
            $q = DB::table('event_seats')
                ->where('status', 'held')
                ->where('updated_at', '>=', $since);
            $count = $q->count();
            // Revert defensiv: doar daca are hold_id NULL (orfan) sau marcat
            if (! $dryRun && $count > 0) {
                DB::table('event_seats')
                    ->where('status', 'held')
                    ->where('updated_at', '>=', $since)
                    ->whereNotIn('id', function ($sub) {
                        $sub->select('event_seat_id')
                            ->from('seat_holds')
                            ->whereNotNull('event_seat_id');
                    })
                    ->update(['status' => 'available', 'updated_at' => now()]);
            }
            $totals['event_seats_reverted'] = $count;
        }

        // 6. Activity log entries
        if (Schema::hasTable('activity_log')) {
            $q = DB::table('activity_log')
                ->where('created_at', '>=', $since)
                ->where(function ($q) {
                    $q->where('description', 'like', '%k6stress%')
                      ->orWhere('properties', 'like', '%@stress.test%');
                });
            $count = $q->count();
            if (! $dryRun && $count > 0) $q->delete();
            $totals['activity_log'] = $count;
        }

        // Summary
        $this->newLine();
        $this->info('=== REZUMAT ===');
        $rows = [];
        $total = 0;
        foreach ($totals as $table => $count) {
            $rows[] = [$table, $count];
            $total += $count;
        }
        $this->table(['Tabela / Operatie', 'Records'], $rows);
        $this->info("Total: {$total} records " . ($dryRun ? '(ar fi fost sterse)' : 'sterse'));

        return self::SUCCESS;
    }

    private function cleanByEmail(string $table, Carbon $since, bool $dryRun): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'email')) {
            return 0;
        }
        $q = DB::table($table)
            ->where(function ($q) {
                $q->where('email', 'like', 'k6stress\_%')
                  ->orWhere('email', 'like', '%@stress.test');
            })
            ->where('created_at', '>=', $since);
        $count = $q->count();
        if (! $dryRun && $count > 0) $q->delete();
        return $count;
    }

    private function cleanByEmailColumn(string $table, string $column, Carbon $since, bool $dryRun): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }
        $q = DB::table($table)
            ->where(function ($q) use ($column) {
                $q->where($column, 'like', 'k6stress\_%')
                  ->orWhere($column, 'like', '%@stress.test');
            })
            ->where('created_at', '>=', $since);
        $count = $q->count();
        if (! $dryRun && $count > 0) $q->delete();
        return $count;
    }

    private function parseSince(string $since): Carbon
    {
        if (preg_match('/^(\d+)([hdm])$/', $since, $m)) {
            $val = (int) $m[1];
            return match ($m[2]) {
                'm' => now()->subMinutes($val),
                'h' => now()->subHours($val),
                'd' => now()->subDays($val),
            };
        }
        return now()->subHour();
    }
}
