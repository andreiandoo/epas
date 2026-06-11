<?php

namespace App\Console\Commands;

use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use App\Notifications\CapiConnectionRecovered;
use App\Notifications\CapiConnectionUnhealthy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled health check for Facebook CAPI connections.
 *
 *   - Runs hourly (see routes/console.php).
 *   - For every connection with status=active, counts the last N hours
 *     of facebook_capi_events grouped by status.
 *   - A connection is "unhealthy" when it has at least `--min-attempts`
 *     total dispatches in the window AND the failure share is at or
 *     above `--threshold` percent.
 *   - Fires the unhealthy notification ONCE per failure incident (on
 *     the healthy→alerting transition). When the window flips back to
 *     healthy, fires the recovery notification and resets the state.
 *
 * Recipients: all super-admins of the marketplace_client that owns the
 * organizer behind the connection. Emails + the in-app database
 * notification channel (visible in Filament notifications panel).
 *
 *   php artisan capi:health-check                  # default 6h window
 *   php artisan capi:health-check --window=12      # widen window
 *   php artisan capi:health-check --connection=9   # one connection only
 *   php artisan capi:health-check --dry-run        # log decisions, no writes / no mail
 */
class CapiHealthCheck extends Command
{
    protected $signature = 'capi:health-check
        {--connection= : Restrict to a single facebook_capi_connections.id}
        {--window=6 : Hours window of facebook_capi_events to evaluate}
        {--threshold=95 : Failure percent (inclusive) that flips a connection to alerting}
        {--min-attempts=10 : Minimum events in the window before a connection can be flagged}
        {--dry-run : Log decisions but do not send notifications or persist state changes}';

    protected $description = 'Detect Facebook CAPI connections in a failure cycle and notify super-admins (one alert per incident).';

    public function handle(): int
    {
        $windowHours = max(1, (int) $this->option('window'));
        $threshold = max(1, min(100, (float) $this->option('threshold')));
        $minAttempts = max(1, (int) $this->option('min-attempts'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('');
        $this->info($dryRun ? '=== DRY-RUN — no state writes / no mail ===' : '=== Live run ===');
        $this->line("window={$windowHours}h  threshold={$threshold}%  min_attempts={$minAttempts}");
        $this->line('');

        $query = FacebookCapiConnection::query()->where('status', 'active');
        if ($id = $this->option('connection')) {
            $query->where('id', (int) $id);
        }

        $connections = $query->get();
        if ($connections->isEmpty()) {
            $this->warn('No active connections to evaluate.');
            return self::SUCCESS;
        }

        $alerted = 0;
        $recovered = 0;
        $stillAlerting = 0;
        $stillHealthy = 0;

        $since = now()->subHours($windowHours);

        foreach ($connections as $conn) {
            $rows = DB::table('facebook_capi_events')
                ->where('connection_id', $conn->id)
                ->where('created_at', '>=', $since)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $sent = (int) ($rows['sent'] ?? 0);
            $failed = (int) ($rows['failed'] ?? 0);
            $total = $sent + $failed; // intentionally exclude 'pending' from the math

            $failPct = $total > 0 ? ($failed / $total) * 100 : 0.0;
            $isUnhealthy = $total >= $minAttempts && $failPct >= $threshold;
            $wasAlerting = $conn->isAlerting();

            $label = sprintf(
                'conn#%d org=%s pixel=%s  total=%d sent=%d failed=%d fail_pct=%.1f%%  was=%s now=%s',
                $conn->id,
                $conn->marketplace_organizer_id,
                $conn->pixel_id,
                $total,
                $sent,
                $failed,
                $failPct,
                $wasAlerting ? 'alerting' : 'healthy',
                $isUnhealthy ? 'alerting' : 'healthy',
            );

            if ($isUnhealthy && ! $wasAlerting) {
                // Healthy → alerting transition: fire unhealthy notification.
                $this->warn("  [ALERT] {$label}");
                $alerted++;
                if (! $dryRun) {
                    $this->dispatchAlert($conn, $sent, $failed, $total, $failPct, $windowHours);
                    $conn->forceFill([
                        'last_health_status' => 'alerting',
                        'last_alerted_at' => now(),
                    ])->save();
                }
            } elseif (! $isUnhealthy && $wasAlerting) {
                // Alerting → healthy transition: fire recovery notification.
                $this->info("  [RECOVER] {$label}");
                $recovered++;
                if (! $dryRun) {
                    $this->dispatchRecovery($conn, $sent, $failed, $total, $windowHours);
                    $conn->forceFill([
                        'last_health_status' => null,
                        'last_alerted_at' => null,
                    ])->save();
                }
            } elseif ($isUnhealthy) {
                $this->line("  [still alerting] {$label}");
                $stillAlerting++;
            } else {
                $this->line("  [healthy] {$label}");
                $stillHealthy++;
            }
        }

        $this->line('');
        $this->info("Summary: alerted={$alerted}  recovered={$recovered}  still_alerting={$stillAlerting}  healthy={$stillHealthy}");
        $this->line('');

        return self::SUCCESS;
    }

    private function dispatchAlert(FacebookCapiConnection $conn, int $sent, int $failed, int $total, float $failPct, int $windowHours): void
    {
        $context = $this->resolveContext($conn);
        $latest = DB::table('facebook_capi_events')
            ->where('connection_id', $conn->id)
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->orderByDesc('created_at')
            ->first(['error_message', 'created_at']);
        $sinceFailed = DB::table('facebook_capi_events')
            ->where('connection_id', $conn->id)
            ->where('status', 'failed')
            ->orderBy('created_at')
            ->where('created_at', '>=', now()->subHours($windowHours * 4)) // look a bit wider for incident start
            ->value('created_at');

        $stats = [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'failure_pct' => $failPct,
            'window_hours' => $windowHours,
            'latest_error' => $latest?->error_message,
            'since' => $sinceFailed,
            'organizer_name' => $context['organizer_name'],
            'marketplace_name' => $context['marketplace_name'],
        ];

        Notification::send($context['recipients'], new CapiConnectionUnhealthy($conn, $stats));
    }

    private function dispatchRecovery(FacebookCapiConnection $conn, int $sent, int $failed, int $total, int $windowHours): void
    {
        $context = $this->resolveContext($conn);
        $outageStart = $conn->last_alerted_at?->toDateTimeString();
        $outageHuman = $conn->last_alerted_at?->diffForHumans(now(), true);

        $stats = [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'window_hours' => $windowHours,
            'organizer_name' => $context['organizer_name'],
            'marketplace_name' => $context['marketplace_name'],
            'outage_started' => $outageStart,
            'outage_duration_human' => $outageHuman,
        ];

        Notification::send($context['recipients'], new CapiConnectionRecovered($conn, $stats));
    }

    /**
     * Resolve organizer / marketplace names + the list of super-admin
     * recipients for a given connection.
     *
     * @return array{recipients:\Illuminate\Support\Collection,organizer_name:?string,marketplace_name:?string}
     */
    private function resolveContext(FacebookCapiConnection $conn): array
    {
        $organizerName = null;
        $marketplaceName = null;
        $marketplaceClientId = $conn->marketplace_client_id;

        if ($conn->marketplace_organizer_id) {
            $org = MarketplaceOrganizer::query()
                ->where('id', $conn->marketplace_organizer_id)
                ->first(['id', 'name', 'marketplace_client_id']);
            if ($org) {
                $organizerName = $org->name;
                $marketplaceClientId = $marketplaceClientId ?: $org->marketplace_client_id;
            }
        }

        if ($marketplaceClientId) {
            $mp = MarketplaceClient::query()->find($marketplaceClientId, ['id', 'name']);
            $marketplaceName = $mp?->name;
        }

        $recipients = collect();
        if ($marketplaceClientId) {
            $recipients = MarketplaceAdmin::query()
                ->where('marketplace_client_id', $marketplaceClientId)
                ->where('role', 'super_admin')
                ->whereNotNull('email')
                ->get();
        }

        return [
            'recipients' => $recipients,
            'organizer_name' => $organizerName,
            'marketplace_name' => $marketplaceName,
        ];
    }
}
