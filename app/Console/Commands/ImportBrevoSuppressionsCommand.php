<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import a Brevo suppression export (CSV) and mark the matching
 * marketplace_customers rows as email_suppressed.
 *
 * Brevo lets you download CSVs from:
 *   - Statistics → Email reporting → Hard bounces
 *   - Statistics → Email reporting → Spam reports
 *   - Statistics → Email reporting → Unsubscribed
 *   - Contacts → Blocklisted contacts
 * Each is a 2-column CSV (Email + sometimes Reason / Date).
 *
 * Usage:
 *   php artisan customers:import-brevo-suppressions hard-bounces.csv --reason=brevo_hard_bounce
 *   php artisan customers:import-brevo-suppressions spam-reports.csv --reason=brevo_spam_trap --marketplace=1
 *
 *   # Re-run without overwriting an earlier (stronger) flag — e.g. don't
 *   # downgrade an invalid_mx to brevo_unsubscribed:
 *   php artisan customers:import-brevo-suppressions blocked.csv --reason=brevo_blocked --no-overwrite
 */
class ImportBrevoSuppressionsCommand extends Command
{
    protected $signature = 'customers:import-brevo-suppressions
                            {file : Path to the CSV export from Brevo}
                            {--reason=brevo_blocked : suppression reason to record (brevo_hard_bounce | brevo_spam_trap | brevo_complaint | brevo_unsubscribed | brevo_blocked | manual)}
                            {--marketplace= : restrict matching to this marketplace_client_id (omit for all)}
                            {--no-overwrite : skip rows that are already suppressed for a different reason}
                            {--dry-run : count matches but do not write to DB}';

    protected $description = 'Mark customers as email_suppressed using a Brevo bounce/spam/blocklist CSV export';

    public function handle(): int
    {
        $file = $this->argument('file');
        $reason = $this->option('reason');
        $marketplace = $this->option('marketplace');
        $noOverwrite = (bool) $this->option('no-overwrite');
        $dryRun = (bool) $this->option('dry-run');

        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $allowedReasons = ['brevo_hard_bounce', 'brevo_spam_trap', 'brevo_complaint', 'brevo_unsubscribed', 'brevo_blocked', 'manual'];
        if (!in_array($reason, $allowedReasons, true)) {
            $this->error("Reason must be one of: " . implode(', ', $allowedReasons));
            return self::FAILURE;
        }

        $fh = fopen($file, 'r');
        if (!$fh) {
            $this->error("Cannot open: {$file}");
            return self::FAILURE;
        }

        // Skip header — Brevo includes a header row on every export. We
        // identify the email column by looking for the first cell containing
        // '@' on the first data row; some exports lead with timestamp / id.
        $emails = [];
        $line = 0;
        $emailColIdx = null;
        while (($cells = fgetcsv($fh)) !== false) {
            $line++;
            if ($line === 1) {
                // Try to detect email column from headers
                foreach ($cells as $i => $cell) {
                    if (stripos((string) $cell, 'email') !== false) {
                        $emailColIdx = $i;
                        break;
                    }
                }
                continue;
            }
            if ($emailColIdx === null) {
                // Fallback: pick the first cell that looks like an email
                foreach ($cells as $i => $cell) {
                    if (str_contains((string) $cell, '@')) {
                        $emailColIdx = $i;
                        break;
                    }
                }
            }
            if ($emailColIdx === null) {
                continue;
            }
            $email = strtolower(trim((string) ($cells[$emailColIdx] ?? '')));
            if ($email !== '' && str_contains($email, '@')) {
                $emails[$email] = true;
            }
        }
        fclose($fh);

        $emails = array_keys($emails);
        $this->info('Distinct emails in CSV: ' . count($emails));

        // Match against marketplace_customers in chunks (avoid IN-list bloat).
        $matchedIds = [];
        $alreadySuppressedDifferent = 0;
        foreach (array_chunk($emails, 1000) as $chunk) {
            $rows = DB::table('marketplace_customers')
                ->select('id', 'email', 'email_suppressed', 'email_suppression_reason')
                ->whereIn(DB::raw('LOWER(email)'), $chunk)
                ->when($marketplace, fn ($q) => $q->where('marketplace_client_id', $marketplace))
                ->get();
            foreach ($rows as $row) {
                if ($noOverwrite && $row->email_suppressed && $row->email_suppression_reason !== $reason) {
                    $alreadySuppressedDifferent++;
                    continue;
                }
                $matchedIds[] = (int) $row->id;
            }
        }

        $this->info('Matched customer rows: ' . count($matchedIds));
        if ($noOverwrite) {
            $this->info("Skipped (already suppressed for a different reason, --no-overwrite): {$alreadySuppressedDifferent}");
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing written.');
            return self::SUCCESS;
        }

        $now = now();
        $touched = 0;
        foreach (array_chunk($matchedIds, 500) as $chunk) {
            $touched += DB::table('marketplace_customers')
                ->whereIn('id', $chunk)
                ->update([
                    'email_suppressed' => true,
                    'email_suppression_reason' => $reason,
                    'email_suppressed_at' => $now,
                ]);
        }
        $this->info("Flagged {$touched} customers as email_suppressed (reason={$reason})");
        return self::SUCCESS;
    }
}
