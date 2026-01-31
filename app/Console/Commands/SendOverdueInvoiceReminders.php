<?php

namespace App\Console\Commands;

use App\Mail\InvoiceMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-overdue-reminders
                            {--days= : Only send reminders for invoices overdue by this many days}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send email reminders for overdue invoices';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificDays = $this->option('days');

        $this->info('🔍 Checking for overdue invoices...');

        // Get overdue invoices
        $query = Invoice::query()
            ->where('status', 'outstanding')
            ->where('due_date', '<', Carbon::today());

        if ($specificDays) {
            $targetDate = Carbon::today()->subDays($specificDays);
            $query->whereDate('due_date', '=', $targetDate);
        }

        $overdueInvoices = $query->with('tenant')->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('✅ No overdue invoices found.');
            return 0;
        }

        $this->info("📋 Found {$overdueInvoices->count()} overdue invoice(s):");

        $sent = 0;
        $errors = 0;

        foreach ($overdueInvoices as $invoice) {
            $tenant = $invoice->tenant;
            $daysOverdue = Carbon::today()->diffInDays($invoice->due_date);

            $this->line("\n" . str_repeat('─', 60));
            $this->info("Invoice: {$invoice->number}");
            $this->line("  Tenant: {$tenant->name}");
            $this->line("  Email: {$tenant->email}");
            $this->line("  Due Date: {$invoice->due_date->toDateString()}");
            $this->line("  Days Overdue: {$daysOverdue}");
            $this->line("  Amount: " . number_format($invoice->amount, 2) . " {$invoice->currency}");

            if (!$tenant->email) {
                $this->warn("  ⚠️  Skipping - no email address");
                continue;
            }

            if ($dryRun) {
                $this->info("  [DRY RUN] Would send overdue reminder to {$tenant->email}");
            } else {
                try {
                    // Send overdue reminder email
                    Mail::to($tenant->email)->send(new InvoiceMail($invoice, 'invoice_overdue'));

                    // Log email
                    EmailLog::create([
                        'recipient_email' => $tenant->email,
                        'recipient_name' => $tenant->name,
                        'subject' => "Overdue Invoice Reminder - {$invoice->number}",
                        'body' => "Overdue reminder sent - {$daysOverdue} days overdue",
                        'sent_at' => now(),
                        'status' => 'sent',
                        'event_trigger' => 'invoice_overdue',
                    ]);

                    $this->info("  ✅ Reminder sent successfully");
                    $sent++;
                } catch (\Exception $e) {
                    $this->error("  ❌ Error: {$e->getMessage()}");
                    $errors++;
                }
            }
        }

        $this->line("\n" . str_repeat('─', 60));

        if ($dryRun) {
            $this->info("🎯 Dry run complete. Would send {$overdueInvoices->count()} reminder(s)");
        } else {
            $this->info("🎉 Overdue reminders sent!");
            $this->line("  ✅ Sent: {$sent}");
            if ($errors > 0) {
                $this->line("  ❌ Errors: {$errors}");
            }
        }

        return $errors > 0 ? 1 : 0;
    }
}
