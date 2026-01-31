<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Gamification\ExperienceAction;
use App\Models\Gamification\ExperienceTransaction;
use App\Services\Gamification\ExperienceService;
use Illuminate\Console\Command;

class SyncProfileCompleteXp extends Command
{
    protected $signature = 'gamification:sync-profile-xp
                            {--tenant= : Specific tenant ID to process}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Award profile_complete XP to existing customers with complete profiles who haven\'t received it yet';

    public function handle(ExperienceService $experienceService): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $this->info($dryRun ? '[DRY RUN] Checking customers with complete profiles...' : 'Syncing profile_complete XP...');

        // Find customers with complete profiles
        $query = Customer::query()
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->whereNotNull('phone')
            ->whereNotNull('city')
            ->whereNotNull('country')
            ->whereNotNull('date_of_birth')
            ->where('first_name', '!=', '')
            ->where('last_name', '!=', '')
            ->where('phone', '!=', '')
            ->where('city', '!=', '')
            ->where('country', '!=', '');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $customers = $query->get();

        $this->info("Found {$customers->count()} customers with complete profiles.");

        $awarded = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        foreach ($customers as $customer) {
            // Check if customer already received profile_complete XP
            $alreadyAwarded = ExperienceTransaction::where('customer_id', $customer->id)
                ->where('action_type', ExperienceAction::ACTION_PROFILE_COMPLETE)
                ->exists();

            if ($alreadyAwarded) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $this->line("\n  Would award XP to customer #{$customer->id} ({$customer->email}) - tenant: {$customer->tenant_id}");
                $awarded++;
                $bar->advance();
                continue;
            }

            // Award XP
            if ($customer->tenant_id) {
                $transaction = $experienceService->awardActionXpForTenant(
                    $customer->tenant_id,
                    $customer->id,
                    ExperienceAction::ACTION_PROFILE_COMPLETE,
                    0,
                    [
                        'reference_type' => Customer::class,
                        'reference_id' => $customer->id,
                        'description' => [
                            'en' => 'Profile completed (sync)',
                            'ro' => 'Profil completat (sincronizare)',
                        ],
                    ]
                );

                if ($transaction) {
                    $awarded++;
                    $this->line("\n  Awarded XP to customer #{$customer->id} ({$customer->email})");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Summary:");
        $this->info("  - Customers processed: {$customers->count()}");
        $this->info("  - XP awarded: {$awarded}");
        $this->info("  - Already had XP (skipped): {$skipped}");

        if ($dryRun) {
            $this->warn("\nThis was a dry run. Run without --dry-run to actually award XP.");
        }

        return Command::SUCCESS;
    }
}
