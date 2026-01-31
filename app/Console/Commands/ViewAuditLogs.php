<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditService;
use Illuminate\Console\Command;

class ViewAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:view
                            {--tenant= : Filter by tenant ID}
                            {--action= : Filter by action}
                            {--resource-type= : Filter by resource type}
                            {--severity= : Filter by severity level}
                            {--from= : Start date (Y-m-d format)}
                            {--to= : End date (Y-m-d format)}
                            {--limit=50 : Number of logs to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View audit logs with filtering';

    /**
     * Execute the console command.
     */
    public function handle(AuditService $auditService): int
    {
        $filters = [
            'tenant_id' => $this->option('tenant'),
            'action' => $this->option('action'),
            'resource_type' => $this->option('resource-type'),
            'severity' => $this->option('severity'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'limit' => (int) $this->option('limit'),
        ];

        // Remove null filters
        $filters = array_filter($filters, fn($value) => $value !== null);

        $logs = $auditService->getLogs($filters);

        if (empty($logs)) {
            $this->info('No audit logs found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($logs) . " audit log(s)");
        $this->newLine();

        $headers = ['Timestamp', 'Action', 'Actor', 'Resource', 'Severity'];
        $rows = [];

        foreach ($logs as $log) {
            $rows[] = [
                $log['created_at'],
                $log['action'],
                $log['actor_name'] ?? 'Unknown',
                ($log['resource_type'] ?? '') . ($log['resource_id'] ? ":{$log['resource_id']}" : ''),
                $log['severity'],
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
}
