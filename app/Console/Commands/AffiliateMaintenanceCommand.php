<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\AffiliateSettings;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AffiliateMaintenanceCommand extends Command
{
    protected $signature = 'affiliate:maintenance
                            {--release-commissions : Release held commissions that passed the hold period}
                            {--recalculate-balances : Recalculate all affiliate balances}
                            {--tenant= : Specific tenant ID}';

    protected $description = 'Run affiliate maintenance tasks (release commissions, recalculate balances)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        // Get all tenants with affiliates enabled
        $tenantsQuery = Tenant::query()
            ->whereHas('microservices', function ($q) {
                $q->where('slug', 'affiliates')
                  ->where('tenant_microservices.is_active', true);
            });

        if ($tenantId) {
            $tenantsQuery->where('id', $tenantId);
        }

        $tenants = $tenantsQuery->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants with affiliates microservice enabled.');
            return self::SUCCESS;
        }

        $releaseCommissions = $this->option('release-commissions');
        $recalculateBalances = $this->option('recalculate-balances');

        // If no specific option, run all
        if (!$releaseCommissions && !$recalculateBalances) {
            $releaseCommissions = true;
            $recalculateBalances = true;
        }

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name} (ID: {$tenant->id})");

            try {
                if ($releaseCommissions) {
                    $this->releaseHeldCommissions($tenant);
                }

                if ($recalculateBalances) {
                    $this->recalculateBalances($tenant);
                }
            } catch (\Exception $e) {
                $this->error("Error processing tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Affiliate maintenance error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info('Affiliate maintenance completed.');
        return self::SUCCESS;
    }

    /**
     * Release held commissions that have passed the hold period.
     * Moves pending_balance to available_balance for eligible conversions.
     */
    protected function releaseHeldCommissions(Tenant $tenant): void
    {
        $settings = AffiliateSettings::getOrCreate($tenant->id);
        $holdDays = $settings->commission_hold_days ?? 30;

        $releaseDate = now()->subDays($holdDays);

        // Find all approved conversions that were created before the release date
        // and haven't been marked as released yet
        $conversionsToRelease = AffiliateConversion::query()
            ->whereHas('affiliate', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })
            ->where('status', 'approved')
            ->where('created_at', '<=', $releaseDate)
            ->whereNull('released_at')
            ->get();

        if ($conversionsToRelease->isEmpty()) {
            $this->line('  - No commissions to release');
            return;
        }

        $totalReleased = 0;
        $affiliatesUpdated = collect();

        DB::transaction(function () use ($conversionsToRelease, &$totalReleased, &$affiliatesUpdated) {
            foreach ($conversionsToRelease as $conversion) {
                // Mark conversion as released
                $conversion->update(['released_at' => now()]);

                $totalReleased += $conversion->commission_value;
                $affiliatesUpdated->push($conversion->affiliate_id);
            }

            // Recalculate balances for affected affiliates
            $uniqueAffiliates = $affiliatesUpdated->unique();
            foreach ($uniqueAffiliates as $affiliateId) {
                $affiliate = Affiliate::find($affiliateId);
                if ($affiliate) {
                    $affiliate->recalculateBalances();
                }
            }
        });

        $affiliateCount = $affiliatesUpdated->unique()->count();
        $this->line("  - Released {$conversionsToRelease->count()} commissions totaling " . number_format($totalReleased, 2) . " RON");
        $this->line("  - Updated balances for {$affiliateCount} affiliates");

        Log::info('Affiliate commissions released', [
            'tenant_id' => $tenant->id,
            'conversions_released' => $conversionsToRelease->count(),
            'total_released' => $totalReleased,
            'affiliates_updated' => $affiliateCount,
        ]);
    }

    /**
     * Recalculate all affiliate balances.
     * Useful for fixing any balance discrepancies.
     */
    protected function recalculateBalances(Tenant $tenant): void
    {
        $affiliates = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('customer_id') // Only affiliates with customer accounts
            ->get();

        if ($affiliates->isEmpty()) {
            $this->line('  - No affiliates to recalculate');
            return;
        }

        $updated = 0;
        foreach ($affiliates as $affiliate) {
            $oldPending = $affiliate->pending_balance;
            $oldAvailable = $affiliate->available_balance;

            $affiliate->recalculateBalances();
            $affiliate->refresh();

            // Check if anything changed
            if ($oldPending != $affiliate->pending_balance || $oldAvailable != $affiliate->available_balance) {
                $updated++;
                $this->line("  - Updated affiliate {$affiliate->code}: pending {$oldPending} -> {$affiliate->pending_balance}, available {$oldAvailable} -> {$affiliate->available_balance}");
            }
        }

        $this->line("  - Recalculated balances for {$affiliates->count()} affiliates ({$updated} had changes)");

        if ($updated > 0) {
            Log::info('Affiliate balances recalculated', [
                'tenant_id' => $tenant->id,
                'total_affiliates' => $affiliates->count(),
                'affiliates_updated' => $updated,
            ]);
        }
    }
}
