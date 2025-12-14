<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Gamification\GamificationConfig;
use App\Models\Tenant;
use App\Services\Gamification\GamificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GamificationMaintenanceCommand extends Command
{
    protected $signature = 'gamification:maintenance
                            {--expire-points : Process expired points}
                            {--birthday-bonuses : Award birthday bonuses}
                            {--tenant= : Specific tenant ID}';

    protected $description = 'Run gamification maintenance tasks (expire points, birthday bonuses)';

    protected GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        parent::__construct();
        $this->gamificationService = $gamificationService;
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        // Get all tenants with gamification enabled
        $tenantsQuery = Tenant::query()
            ->whereHas('microservices', function ($q) {
                $q->where('slug', 'gamification')
                  ->where('tenant_microservices.is_active', true);
            });

        if ($tenantId) {
            $tenantsQuery->where('id', $tenantId);
        }

        $tenants = $tenantsQuery->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants with gamification enabled.');
            return self::SUCCESS;
        }

        $expirePoints = $this->option('expire-points');
        $birthdayBonuses = $this->option('birthday-bonuses');

        // If no specific option, run all
        if (!$expirePoints && !$birthdayBonuses) {
            $expirePoints = true;
            $birthdayBonuses = true;
        }

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name} (ID: {$tenant->id})");

            try {
                if ($expirePoints) {
                    $this->processExpiredPoints($tenant);
                }

                if ($birthdayBonuses) {
                    $this->processBirthdayBonuses($tenant);
                }
            } catch (\Exception $e) {
                $this->error("Error processing tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Gamification maintenance error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info('Gamification maintenance completed.');
        return self::SUCCESS;
    }

    protected function processExpiredPoints(Tenant $tenant): void
    {
        $expired = $this->gamificationService->processExpiredPoints($tenant->id);

        if ($expired > 0) {
            $this->line("  - Expired {$expired} points");
            Log::info('Gamification points expired', [
                'tenant_id' => $tenant->id,
                'points_expired' => $expired,
            ]);
        } else {
            $this->line('  - No points to expire');
        }
    }

    protected function processBirthdayBonuses(Tenant $tenant): void
    {
        $config = GamificationConfig::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        if (!$config || $config->birthday_bonus_points <= 0) {
            $this->line('  - Birthday bonuses not configured');
            return;
        }

        // Find customers with birthdays today
        $today = now()->format('m-d');
        $customersWithBirthday = Customer::where('tenant_id', $tenant->id)
            ->whereNotNull('date_of_birth')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = ?", [$today])
            ->get();

        $awarded = 0;
        foreach ($customersWithBirthday as $customer) {
            $transaction = $this->gamificationService->awardBirthdayBonus($tenant->id, $customer->id);

            if ($transaction) {
                $awarded++;
                $this->line("  - Awarded birthday bonus to customer {$customer->id}");
            }
        }

        if ($awarded > 0) {
            $this->line("  - Total birthday bonuses awarded: {$awarded}");
            Log::info('Gamification birthday bonuses awarded', [
                'tenant_id' => $tenant->id,
                'customers_awarded' => $awarded,
            ]);
        } else {
            $this->line('  - No birthday bonuses to award today');
        }
    }
}
