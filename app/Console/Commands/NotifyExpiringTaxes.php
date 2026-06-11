<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use App\Notifications\ExpiringTaxesNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyExpiringTaxes extends Command
{
    protected $signature = 'taxes:notify-expiring
                            {--days=30 : Number of days to look ahead for expiring taxes}
                            {--tenant= : Specific tenant ID to check (optional)}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send notifications for taxes that are about to expire';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $this->info("Checking for taxes expiring in the next {$days} days...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
        }

        $cutoffDate = Carbon::today()->addDays($days);
        $today = Carbon::today();

        // Get tenants to check
        $tenantsQuery = Tenant::query();
        if ($tenantId) {
            $tenantsQuery->where('id', $tenantId);
        }

        $tenants = $tenantsQuery->get();
        $totalNotifications = 0;

        foreach ($tenants as $tenant) {
            $expiringTaxes = $this->getExpiringTaxes($tenant->id, $today, $cutoffDate);

            if ($expiringTaxes['total'] === 0) {
                continue;
            }

            $this->line("Tenant {$tenant->name} (ID: {$tenant->id}): {$expiringTaxes['total']} expiring tax(es)");

            // Display details
            foreach ($expiringTaxes['general'] as $tax) {
                $daysLeft = $today->diffInDays($tax->valid_until);
                $this->line("  - General: {$tax->name} expires in {$daysLeft} days ({$tax->valid_until->format('Y-m-d')})");
            }

            foreach ($expiringTaxes['local'] as $tax) {
                $daysLeft = $today->diffInDays($tax->valid_until);
                $location = $tax->getLocationString();
                $this->line("  - Local: {$location} expires in {$daysLeft} days ({$tax->valid_until->format('Y-m-d')})");
            }

            if (!$dryRun) {
                $this->sendNotification($tenant, $expiringTaxes, $days);
                $totalNotifications++;
            }
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would have sent {$totalNotifications} notification(s)");
        } else {
            $this->info("Sent {$totalNotifications} notification(s)");
        }

        return Command::SUCCESS;
    }

    protected function getExpiringTaxes(int $tenantId, Carbon $today, Carbon $cutoffDate): array
    {
        $generalTaxes = GeneralTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $today)
            ->where('valid_until', '<=', $cutoffDate)
            ->orderBy('valid_until')
            ->get();

        $localTaxes = LocalTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $today)
            ->where('valid_until', '<=', $cutoffDate)
            ->orderBy('valid_until')
            ->get();

        return [
            'general' => $generalTaxes,
            'local' => $localTaxes,
            'total' => $generalTaxes->count() + $localTaxes->count(),
        ];
    }

    protected function sendNotification(Tenant $tenant, array $expiringTaxes, int $days): void
    {
        try {
            // Get users who should receive this notification (admins)
            $users = $tenant->users()
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['admin', 'owner', 'super-admin']);
                })
                ->get();

            if ($users->isEmpty()) {
                // Fallback to tenant owner
                $users = $tenant->users()->take(1)->get();
            }

            foreach ($users as $user) {
                $user->notify(new ExpiringTaxesNotification($expiringTaxes, $days));
            }

            Log::info("Expiring taxes notification sent for tenant {$tenant->id}", [
                'tenant_id' => $tenant->id,
                'expiring_count' => $expiringTaxes['total'],
                'days_ahead' => $days,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send expiring taxes notification for tenant {$tenant->id}", [
                'error' => $e->getMessage(),
            ]);
            $this->error("Failed to send notification for tenant {$tenant->name}: {$e->getMessage()}");
        }
    }
}
