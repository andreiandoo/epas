<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTenantInvoices extends Command
{
    protected $signature = 'invoices:generate-tenant
                            {--tenant-id= : Generate invoice for specific tenant}
                            {--dry-run : Show what would be generated without creating invoices}';

    protected $description = 'Generate invoices for tenants based on their billing cycle and commission rates';

    public function handle()
    {
        $today = Carbon::today();
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant-id');

        $this->info("🔍 Checking for tenants due for billing on {$today->toDateString()}...");

        // Get tenants that need invoicing
        $query = Tenant::query()
            ->whereNotNull('next_billing_date')
            ->where('next_billing_date', '<=', $today)
            ->where('status', 'active');

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->info('✅ No tenants due for billing today.');
            return 0;
        }

        $this->info("📋 Found {$tenants->count()} tenant(s) to invoice:");

        $settings = Setting::current();
        $generated = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->line("\n" . str_repeat('─', 60));
                $this->info("Processing: {$tenant->name} (ID: {$tenant->id})");

                // Calculate billing period
                $periodEnd = $tenant->next_billing_date;
                $periodStart = $periodEnd->copy()->subDays($tenant->billing_cycle_days ?? 30);

                $this->line("  Period: {$periodStart->toDateString()} to {$periodEnd->toDateString()}");

                // Calculate gross revenue from orders in this period
                $grossRevenue = Order::where('tenant_id', $tenant->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd->endOfDay()])
                    ->where('status', '!=', 'cancelled')
                    ->sum('total');

                $this->line("  Gross revenue: " . number_format($grossRevenue, 2) . " {$tenant->currency}");

                // Calculate commission amount
                $commissionRate = $tenant->commission_rate ?? 0;
                $invoiceAmount = round($grossRevenue * ($commissionRate / 100), 2);

                $this->line("  Commission rate: {$commissionRate}%");
                $this->line("  Invoice amount: " . number_format($invoiceAmount, 2) . " {$tenant->currency}");

                if ($invoiceAmount <= 0) {
                    $this->warn("  ⚠️  Skipping - no revenue in this period");

                    // Still update next billing date
                    if (!$dryRun) {
                        $tenant->update([
                            'next_billing_date' => $periodEnd->copy()->addDays($tenant->billing_cycle_days ?? 30)
                        ]);
                    }
                    continue;
                }

                if ($dryRun) {
                    $this->info("  [DRY RUN] Would generate invoice for {$invoiceAmount} {$tenant->currency}");
                } else {
                    // Generate invoice
                    $invoice = Invoice::create([
                        'tenant_id' => $tenant->id,
                        'number' => $settings->getNextInvoiceNumber(),
                        'issue_date' => $today,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'due_date' => $today->copy()->addDays($settings->default_payment_terms_days ?? 5),
                        'amount' => $invoiceAmount,
                        'currency' => $tenant->currency ?? 'RON',
                        'status' => 'outstanding',
                        'meta' => [
                            'commission_rate' => $commissionRate,
                            'gross_revenue' => $grossRevenue,
                            'billing_cycle_days' => $tenant->billing_cycle_days,
                        ],
                    ]);

                    // Update tenant's next billing date
                    $tenant->update([
                        'next_billing_date' => $periodEnd->copy()->addDays($tenant->billing_cycle_days ?? 30)
                    ]);

                    $this->info("  ✅ Invoice #{$invoice->number} created");
                    $this->line("  Next billing date: {$tenant->next_billing_date->toDateString()}");
                    $generated++;
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->line("\n" . str_repeat('─', 60));

        if ($dryRun) {
            $this->info("🎯 Dry run complete. Would generate {$tenants->count()} invoice(s)");
        } else {
            $this->info("🎉 Invoice generation complete!");
            $this->line("  ✅ Generated: {$generated}");
            if ($errors > 0) {
                $this->line("  ❌ Errors: {$errors}");
            }
        }

        return $errors > 0 ? 1 : 0;
    }
}
