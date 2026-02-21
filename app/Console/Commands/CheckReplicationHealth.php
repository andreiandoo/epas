<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckReplicationHealth extends Command
{
    protected $signature = 'db:replication-health
                            {--alert-threshold=60 : Lag threshold in seconds to trigger alert}';

    protected $description = 'Check PostgreSQL streaming replication status and replica lag';

    public function handle(): int
    {
        $threshold = (int) $this->option('alert-threshold');

        try {
            $replicas = DB::select("
                SELECT
                    client_addr,
                    state,
                    sent_lsn,
                    write_lsn,
                    flush_lsn,
                    replay_lsn,
                    COALESCE(
                        (EXTRACT(EPOCH FROM now()) - EXTRACT(EPOCH FROM reply_time))::int,
                        0
                    ) AS lag_seconds
                FROM pg_stat_replication
            ");
        } catch (\Exception $e) {
            $this->error('Cannot query replication status: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($replicas)) {
            $this->error('No replicas connected to primary!');
            Log::critical('PostgreSQL replication: no replicas connected');
            return self::FAILURE;
        }

        $this->info('Connected replicas: ' . count($replicas));
        $hasIssues = false;

        foreach ($replicas as $replica) {
            $lagStatus = $replica->lag_seconds > $threshold ? 'HIGH LAG' : 'OK';
            $this->line(sprintf(
                '  %s | State: %s | Lag: %ds | %s',
                $replica->client_addr,
                $replica->state,
                $replica->lag_seconds,
                $lagStatus
            ));

            if ($replica->lag_seconds > $threshold) {
                $hasIssues = true;
                $this->warn("  ALERT: Replica {$replica->client_addr} lag ({$replica->lag_seconds}s) exceeds threshold ({$threshold}s)");
                Log::warning('PostgreSQL replication lag alert', [
                    'replica' => $replica->client_addr,
                    'lag_seconds' => $replica->lag_seconds,
                    'state' => $replica->state,
                    'threshold' => $threshold,
                ]);
            }
        }

        if ($hasIssues) {
            return self::FAILURE;
        }

        $this->info('All replicas healthy.');
        return self::SUCCESS;
    }
}
