<?php

namespace App\Console\Commands;

use App\Services\Analytics\ScheduledReportService;
use App\Services\Analytics\EventExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEventReportsCommand extends Command
{
    protected $signature = 'analytics:process-reports
                            {--type=all : Type of processing (reports, goals, cleanup, all)}';

    protected $description = 'Process scheduled analytics reports and goal alerts';

    public function handle(ScheduledReportService $reportService, EventExportService $exportService): int
    {
        $type = $this->option('type');

        $this->info("Processing event analytics ({$type})...");

        try {
            $results = [];

            // Process scheduled reports
            if (in_array($type, ['reports', 'all'])) {
                $this->line('Processing scheduled reports...');
                $reportResults = $reportService->processDueReports();
                $results['reports'] = $reportResults;
                $this->info("  Reports: {$reportResults['sent']} sent, {$reportResults['failed']} failed");
            }

            // Process goal alerts
            if (in_array($type, ['goals', 'all'])) {
                $this->line('Processing goal alerts...');
                $goalResults = $reportService->processGoalAlerts();
                $results['goals'] = $goalResults;
                $this->info("  Goal alerts: {$goalResults['sent']} sent, {$goalResults['failed']} failed");
            }

            // Cleanup old export files
            if (in_array($type, ['cleanup', 'all'])) {
                $this->line('Cleaning up old export files...');
                $deleted = $exportService->cleanupOldExports();
                $results['cleanup'] = $deleted;
                $this->info("  Deleted {$deleted} old export files");
            }

            Log::info('Event analytics processing completed', $results);

            $this->info('Processing completed successfully.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Processing failed: ' . $e->getMessage());
            Log::error('Event analytics processing failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
