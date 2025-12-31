<?php

namespace App\Console\Commands;

use App\Mail\ContractMail;
use App\Models\Tenant;
use App\Services\ContractPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RenewExpiringContracts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'contracts:renew
                            {--days=30 : Number of days before expiration to renew}
                            {--send-email : Send email notification with new contract}
                            {--dry-run : Show what would be renewed without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically renew contracts that are expiring soon';

    public function __construct(
        private ContractPdfService $contractService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $sendEmail = $this->option('send-email');
        $dryRun = $this->option('dry-run');

        $this->info("Checking for contracts expiring within {$days} days...");

        // Find tenants with auto-renew enabled and contracts expiring soon
        $tenants = Tenant::where('contract_auto_renew', true)
            ->where('status', 'active')
            ->whereNotNull('contract_renewal_date')
            ->where('contract_renewal_date', '<=', now()->addDays($days))
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No contracts need renewal.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tenants->count()} contracts to renew.");

        $renewed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing: {$tenant->name} (Contract expires: {$tenant->contract_renewal_date->format('Y-m-d')})");

            if ($dryRun) {
                $this->comment("  [DRY RUN] Would regenerate contract for {$tenant->name}");
                continue;
            }

            try {
                // Regenerate contract
                $path = $this->contractService->regenerate($tenant);

                // Send email if requested
                if ($sendEmail && $tenant->contact_email) {
                    Mail::to($tenant->contact_email)
                        ->send(new ContractMail($tenant, $path));

                    $tenant->update(['contract_sent_at' => now()]);

                    $this->info("  ✓ Contract renewed and sent to {$tenant->contact_email}");
                } else {
                    $this->info("  ✓ Contract renewed");
                }

                Log::info('Contract auto-renewed', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'old_expiry' => $tenant->contract_renewal_date,
                    'new_expiry' => now()->addYear(),
                ]);

                $renewed++;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to renew: {$e->getMessage()}");

                Log::error('Contract auto-renewal failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        $this->newLine();
        $this->info("Renewal complete: {$renewed} renewed, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
