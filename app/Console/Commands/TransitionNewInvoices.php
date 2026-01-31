<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TransitionNewInvoices extends Command
{
    protected $signature = 'invoices:transition-new
                            {--grace-days=3 : Number of grace period days before invoice becomes outstanding}
                            {--dry-run : Show what would be transitioned without actually updating}';

    protected $description = 'Transition invoices from "new" status to "outstanding" after grace period';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $graceDays = (int) $this->option('grace-days');

        $this->info('🔍 Checking for new invoices past grace period...');

        // Calculate cutoff date (issue_date + grace_days < today)
        $cutoffDate = Carbon::today()->subDays($graceDays);

        // Get all 'new' invoices where issue_date is older than grace period
        $invoices = Invoice::query()
            ->where('status', 'new')
            ->where('issue_date', '<=', $cutoffDate)
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('✅ No invoices to transition.');
            return 0;
        }

        $this->info("📋 Found {$invoices->count()} invoice(s) to transition:");

        $transitioned = 0;

        foreach ($invoices as $invoice) {
            $daysOld = Carbon::today()->diffInDays($invoice->issue_date);

            $this->line("\n" . str_repeat('─', 60));
            $this->info("Invoice: {$invoice->number}");
            $this->line("  Tenant: {$invoice->tenant->name}");
            $this->line("  Issue Date: {$invoice->issue_date->toDateString()}");
            $this->line("  Days Old: {$daysOld}");
            $this->line("  Grace Period: {$graceDays} days");
            $this->line("  Current Status: {$invoice->status}");

            if ($dryRun) {
                $this->info("  [DRY RUN] Would transition to 'outstanding'");
            } else {
                try {
                    $invoice->update(['status' => 'outstanding']);
                    $this->info("  ✅ Transitioned to 'outstanding'");
                    $transitioned++;
                } catch (\Exception $e) {
                    $this->error("  ❌ Error: {$e->getMessage()}");
                }
            }
        }

        $this->line("\n" . str_repeat('─', 60));

        if ($dryRun) {
            $this->info("🎯 Dry run complete. Would transition {$invoices->count()} invoice(s)");
        } else {
            $this->info("🎉 Transition complete!");
            $this->line("  ✅ Transitioned: {$transitioned}");
        }

        return 0;
    }
}
