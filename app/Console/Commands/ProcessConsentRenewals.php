<?php

namespace App\Console\Commands;

use App\Services\Tracking\ConsentRenewalService;
use Illuminate\Console\Command;

class ProcessConsentRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consent:process-renewals
                            {--tenant= : Process renewals only for specific tenant ID}
                            {--dry-run : Show what would be done without sending notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and send consent renewal notifications for expiring consents';

    public function __construct(
        protected ConsentRenewalService $renewalService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No notifications will be sent');
            $this->newLine();
        }

        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant ID {$tenantId} not found");
                return Command::FAILURE;
            }

            if ($dryRun) {
                $this->showDryRunForTenant($tenant);
            } else {
                $result = $this->renewalService->processRenewalsForTenant($tenant);
                $this->displayResult($result);
            }
        } else {
            if ($dryRun) {
                $this->showDryRunForAllTenants();
            } else {
                $results = $this->renewalService->processAllTenants();
                $this->displayResults($results);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Show dry run output for a single tenant
     */
    protected function showDryRunForTenant(\App\Models\Tenant $tenant): void
    {
        $this->info("Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->newLine();

        $firstNotificationConsents = $this->renewalService->getConsentsNeedingFirstNotification($tenant->id);
        $reminderConsents = $this->renewalService->getConsentsNeedingReminder($tenant->id);

        $this->info("Consents needing FIRST notification: {$firstNotificationConsents->count()}");
        if ($firstNotificationConsents->count() > 0) {
            $headers = ['ID', 'Visitor ID', 'Customer', 'Expires At', 'Days Left'];
            $rows = $firstNotificationConsents->map(function ($consent) {
                return [
                    $consent->id,
                    substr($consent->visitor_id, 0, 12) . '...',
                    $consent->customer?->email ?? 'Anonymous',
                    $consent->expires_at->format('Y-m-d'),
                    now()->diffInDays($consent->expires_at, false),
                ];
            })->toArray();
            $this->table($headers, $rows);
        }

        $this->newLine();
        $this->info("Consents needing REMINDER notification: {$reminderConsents->count()}");
        if ($reminderConsents->count() > 0) {
            $headers = ['ID', 'Visitor ID', 'Customer', 'Expires At', 'Days Left'];
            $rows = $reminderConsents->map(function ($consent) {
                return [
                    $consent->id,
                    substr($consent->visitor_id, 0, 12) . '...',
                    $consent->customer?->email ?? 'Anonymous',
                    $consent->expires_at->format('Y-m-d'),
                    now()->diffInDays($consent->expires_at, false),
                ];
            })->toArray();
            $this->table($headers, $rows);
        }
    }

    /**
     * Show dry run output for all tenants
     */
    protected function showDryRunForAllTenants(): void
    {
        $tenants = \App\Models\Tenant::where('is_active', true)->get();

        $this->info("Found {$tenants->count()} active tenant(s)");
        $this->newLine();

        $summary = [];

        foreach ($tenants as $tenant) {
            $firstNotificationCount = $this->renewalService
                ->getConsentsNeedingFirstNotification($tenant->id)
                ->count();
            $reminderCount = $this->renewalService
                ->getConsentsNeedingReminder($tenant->id)
                ->count();

            $summary[] = [
                $tenant->id,
                $tenant->name,
                $firstNotificationCount,
                $reminderCount,
                $firstNotificationCount + $reminderCount,
            ];
        }

        $this->table(
            ['Tenant ID', 'Tenant Name', 'First Notifications', 'Reminders', 'Total'],
            $summary
        );
    }

    /**
     * Display result for a single tenant
     */
    protected function displayResult(array $result): void
    {
        if (isset($result['skipped']) && $result['skipped']) {
            $this->warn("Tenant {$result['tenant_id']}: Skipped - {$result['reason']}");
            return;
        }

        $firstCount = count($result['first_notifications']);
        $reminderCount = count($result['reminders']);

        $this->info("Tenant {$result['tenant_id']}:");
        $this->line("  First notifications sent: {$firstCount}");
        $this->line("  Reminders sent: {$reminderCount}");
    }

    /**
     * Display results for all tenants
     */
    protected function displayResults(array $results): void
    {
        $totalFirst = 0;
        $totalReminders = 0;
        $skipped = 0;

        foreach ($results as $tenantId => $result) {
            if (isset($result['skipped']) && $result['skipped']) {
                $skipped++;
                continue;
            }

            $totalFirst += count($result['first_notifications']);
            $totalReminders += count($result['reminders']);
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Tenants processed: " . count($results));
        $this->line("Tenants skipped: {$skipped}");
        $this->line("First notifications sent: {$totalFirst}");
        $this->line("Reminders sent: {$totalReminders}");
        $this->line("Total notifications: " . ($totalFirst + $totalReminders));
    }
}
