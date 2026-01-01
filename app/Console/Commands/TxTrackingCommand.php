<?php

namespace App\Console\Commands;

use App\Jobs\Tracking\AggregateEventFunnelsJob;
use App\Jobs\Tracking\CalculateEngagementMetricsJob;
use App\Jobs\Tracking\CalculatePersonAffinitiesJob;
use App\Jobs\Tracking\CalculateTemporalPatternsJob;
use App\Jobs\Tracking\CalculateTicketPreferencesJob;
use App\Jobs\Tracking\UpdatePersonDailyStatsJob;
use App\Models\CustomerSegment;
use App\Services\Tracking\TxAudienceBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TxTrackingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tx:tracking
                            {action : The action to perform (affinities, preferences, funnels, daily-stats, temporal, engagement, segments, stats)}
                            {--tenant= : Filter by tenant ID}
                            {--person= : Filter by person ID}
                            {--event= : Filter by event entity ID}
                            {--days=365 : Lookback days for calculations}
                            {--hours=24 : Lookback hours for funnel aggregation}
                            {--date= : Specific date for daily stats (Y-m-d)}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     */
    protected $description = 'Manage TX tracking jobs and feature store calculations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'affinities' => $this->runAffinities(),
            'preferences' => $this->runPreferences(),
            'funnels' => $this->runFunnels(),
            'daily-stats' => $this->runDailyStats(),
            'temporal' => $this->runTemporal(),
            'engagement' => $this->runEngagement(),
            'segments' => $this->runSegments(),
            'stats' => $this->showStats(),
            default => $this->invalidAction($action),
        };
    }

    protected function runAffinities(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $personId = $this->option('person') ? (int) $this->option('person') : null;
        $days = (int) $this->option('days');

        $this->info("Calculating person affinities...");
        $this->table(['Option', 'Value'], [
            ['Tenant ID', $tenantId ?? 'All'],
            ['Person ID', $personId ?? 'All with activity'],
            ['Lookback Days', $days],
        ]);

        if ($this->option('sync')) {
            $job = new CalculatePersonAffinitiesJob($tenantId, $personId, $days);
            $job->handle();
            $this->info("Affinities calculated synchronously.");
        } else {
            CalculatePersonAffinitiesJob::dispatch($tenantId, $personId, $days);
            $this->info("Job dispatched to queue.");
        }

        return Command::SUCCESS;
    }

    protected function runPreferences(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $personId = $this->option('person') ? (int) $this->option('person') : null;
        $days = (int) $this->option('days');

        $this->info("Calculating ticket preferences...");
        $this->table(['Option', 'Value'], [
            ['Tenant ID', $tenantId ?? 'All'],
            ['Person ID', $personId ?? 'All purchasers'],
            ['Lookback Days', $days],
        ]);

        if ($this->option('sync')) {
            $job = new CalculateTicketPreferencesJob($tenantId, $personId, $days);
            $job->handle();
            $this->info("Preferences calculated synchronously.");
        } else {
            CalculateTicketPreferencesJob::dispatch($tenantId, $personId, $days);
            $this->info("Job dispatched to queue.");
        }

        return Command::SUCCESS;
    }

    protected function runFunnels(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $eventId = $this->option('event') ? (int) $this->option('event') : null;
        $hours = (int) $this->option('hours');

        $this->info("Aggregating event funnels...");
        $this->table(['Option', 'Value'], [
            ['Tenant ID', $tenantId ?? 'All'],
            ['Event ID', $eventId ?? 'All'],
            ['Lookback Hours', $hours],
        ]);

        if ($this->option('sync')) {
            $job = new AggregateEventFunnelsJob($tenantId, $eventId, $hours);
            $job->handle();
            $this->info("Funnels aggregated synchronously.");
        } else {
            AggregateEventFunnelsJob::dispatch($tenantId, $eventId, $hours);
            $this->info("Job dispatched to queue.");
        }

        return Command::SUCCESS;
    }

    protected function runDailyStats(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $dateStr = $this->option('date');
        $date = $dateStr ? Carbon::parse($dateStr) : null;
        $days = $date ? 1 : (int) $this->option('days');

        $this->info("Updating person daily stats...");
        $this->table(['Option', 'Value'], [
            ['Tenant ID', $tenantId ?? 'All'],
            ['Date', $date?->toDateString() ?? 'Last ' . $days . ' days'],
        ]);

        if ($this->option('sync')) {
            $job = new UpdatePersonDailyStatsJob($tenantId, $date, $days);
            $job->handle();
            $this->info("Daily stats updated synchronously.");
        } else {
            UpdatePersonDailyStatsJob::dispatch($tenantId, $date, $days);
            $this->info("Job dispatched to queue.");
        }

        return Command::SUCCESS;
    }

    protected function runTemporal(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $personId = $this->option('person') ? (int) $this->option('person') : null;
        $days = (int) $this->option('days');

        $this->info("Calculating temporal patterns (activity + purchase windows)...");
        $this->table(['Option', 'Value'], [
            ['Tenant ID', $tenantId ?? 'All'],
            ['Person ID', $personId ?? 'All with activity'],
            ['Lookback Days', $days],
        ]);

        if ($this->option('sync')) {
            $job = new CalculateTemporalPatternsJob($tenantId, $personId, $days);
            $job->handle();
            $this->info("Temporal patterns calculated synchronously.");
        } else {
            CalculateTemporalPatternsJob::dispatch($tenantId, $personId, $days);
            $this->info("Job dispatched to queue.");
        }

        return Command::SUCCESS;
    }

    protected function runEngagement(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $personId = $this->option('person') ? (int) $this->option('person') : null;

        $this->info("Calculating engagement metrics (email fatigue + channel affinity)...");
        $this->table(['Option', 'Value'], [
            ['Tenant ID', $tenantId ?? 'All'],
            ['Person ID', $personId ?? 'All with email'],
        ]);

        if ($this->option('sync')) {
            $job = new CalculateEngagementMetricsJob($tenantId, $personId);
            $job->handle();
            $this->info("Engagement metrics calculated synchronously.");
        } else {
            CalculateEngagementMetricsJob::dispatch($tenantId, $personId);
            $this->info("Job dispatched to queue.");
        }

        return Command::SUCCESS;
    }

    protected function runSegments(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $query = CustomerSegment::where('is_dynamic', true)
            ->whereNotNull('conditions');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $segments = $query->get();

        if ($segments->isEmpty()) {
            $this->warn("No dynamic segments found.");
            return Command::SUCCESS;
        }

        $this->info("Recalculating {$segments->count()} dynamic segments...");

        $bar = $this->output->createProgressBar($segments->count());

        foreach ($segments as $segment) {
            try {
                $oldCount = $segment->member_count;
                $newCount = TxAudienceBuilder::recalculateSegment($segment);

                $this->line(" Segment #{$segment->id} '{$segment->name}': {$oldCount} → {$newCount}");
            } catch (\Exception $e) {
                $this->error(" Segment #{$segment->id} failed: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Segment recalculation complete.");

        return Command::SUCCESS;
    }

    protected function showStats(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info("TX Tracking Statistics");
        $this->newLine();

        // Event counts
        $eventCounts = \DB::table('tx_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('event_name, COUNT(*) as count')
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->get();

        $this->info("Events by Type:");
        $this->table(['Event Name', 'Count'], $eventCounts->map(fn($e) => [$e->event_name, number_format($e->count)])->toArray());

        // Identity links
        $linkCounts = \DB::table('tx_identity_links')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('link_source, COUNT(*) as count')
            ->groupBy('link_source')
            ->orderByDesc('count')
            ->get();

        $this->newLine();
        $this->info("Identity Links by Source:");
        $this->table(['Link Source', 'Count'], $linkCounts->map(fn($l) => [$l->link_source, number_format($l->count)])->toArray());

        // Feature store counts
        $fsCounts = [
            ['Artist Affinities', \DB::table('fs_person_affinity_artist')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count()],
            ['Genre Affinities', \DB::table('fs_person_affinity_genre')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count()],
            ['Ticket Preferences', \DB::table('fs_person_ticket_pref')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count()],
            ['Daily Stats', \DB::table('fs_person_daily')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count()],
            ['Funnel Hours', \DB::table('fs_event_funnel_hourly')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count()],
        ];

        $this->newLine();
        $this->info("Feature Store Records:");
        $this->table(['Table', 'Records'], $fsCounts);

        // Segments
        $segmentCount = CustomerSegment::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
        $dynamicCount = CustomerSegment::where('is_dynamic', true)->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();

        $this->newLine();
        $this->info("Segments: {$segmentCount} total ({$dynamicCount} dynamic)");

        return Command::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line("Available actions: affinities, preferences, funnels, daily-stats, temporal, engagement, segments, stats");
        return Command::FAILURE;
    }
}
