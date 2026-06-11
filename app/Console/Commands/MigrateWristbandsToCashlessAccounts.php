<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\Cashless\CashlessAccount;
use App\Models\Wristband;
use Illuminate\Console\Command;

class MigrateWristbandsToCashlessAccounts extends Command
{
    protected $signature = 'cashless:migrate-wristbands
                            {--edition= : Migrate only a specific festival_edition_id}
                            {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Create CashlessAccount records for existing wristbands that have a customer_id';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $editionId = $this->option('edition');

        $query = Wristband::whereNotNull('customer_id')
            ->whereDoesntHave('cashlessAccount');

        if ($editionId) {
            $query->where('festival_edition_id', $editionId);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No wristbands to migrate — all already have CashlessAccounts.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$total} wristband(s) to migrate.");

        if ($dryRun) {
            $query->each(function (Wristband $wristband) {
                $this->line("  Would create CashlessAccount for wristband #{$wristband->id} (uid: {$wristband->uid}, customer: {$wristband->customer_id}, balance: {$wristband->balance_cents})");
            });

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(100, function ($wristbands) use (&$created, &$skipped, &$errors, $bar) {
            foreach ($wristbands as $wristband) {
                try {
                    // Check if account already exists for this customer+edition combo
                    $exists = CashlessAccount::where('customer_id', $wristband->customer_id)
                        ->where('festival_edition_id', $wristband->festival_edition_id)
                        ->exists();

                    if ($exists) {
                        // Link wristband to existing account
                        CashlessAccount::where('customer_id', $wristband->customer_id)
                            ->where('festival_edition_id', $wristband->festival_edition_id)
                            ->whereNull('wristband_id')
                            ->update(['wristband_id' => $wristband->id]);
                        $skipped++;
                    } else {
                        CashlessAccount::create([
                            'tenant_id'                  => $wristband->tenant_id,
                            'festival_edition_id'        => $wristband->festival_edition_id,
                            'customer_id'                => $wristband->customer_id,
                            'wristband_id'               => $wristband->id,
                            'festival_pass_purchase_id'  => $wristband->festival_pass_purchase_id,
                            'account_number'             => CashlessAccount::generateAccountNumber(),
                            'balance_cents'              => $wristband->balance_cents,
                            'total_topped_up_cents'      => $wristband->transactions()
                                ->where('transaction_type', 'topup')
                                ->sum('amount_cents'),
                            'total_spent_cents'          => $wristband->transactions()
                                ->where('transaction_type', 'payment')
                                ->sum('amount_cents'),
                            'total_cashed_out_cents'     => $wristband->transactions()
                                ->where('transaction_type', 'cashout')
                                ->sum('amount_cents'),
                            'currency'                   => $wristband->currency ?? 'RON',
                            'status'                     => $wristband->isActive()
                                ? AccountStatus::Active
                                : AccountStatus::Closed,
                            'activated_at'               => $wristband->activated_at,
                        ]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $this->error("  Error migrating wristband #{$wristband->id}: {$e->getMessage()}");
                    $errors++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Migration complete: {$created} created, {$skipped} skipped (already existed), {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
