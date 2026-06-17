<?php

namespace App\Console\Commands;

use App\Models\MarketplaceCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mark customers as unsubscribed (accepts_marketing=false) from a CSV
 * of email addresses. Idempotent — re-running on the same list is a
 * no-op for already-unsubscribed customers.
 *
 * CSV format: one email per line, no header. Trimmed + lowercased
 * before matching, so casing/whitespace variations don't miss rows.
 *
 * Usage:
 *   php artisan customers:unsubscribe-from-csv --marketplace=1 --file=path/to/list.csv
 *   php artisan customers:unsubscribe-from-csv --marketplace=1 --file=path/to/list.csv --dry-run
 */
class UnsubscribeFromCsvCommand extends Command
{
    protected $signature = 'customers:unsubscribe-from-csv
                            {--marketplace= : marketplace_client_id to scope the update}
                            {--file= : path to the CSV file (one email per line)}
                            {--dry-run : compute totals without writing}';

    protected $description = 'Mark customers as unsubscribed from a CSV list of emails';

    public function handle(): int
    {
        $marketplaceId = (int) $this->option('marketplace');
        $file = (string) $this->option('file');
        $dryRun = (bool) $this->option('dry-run');

        if ($marketplaceId <= 0) {
            $this->error('--marketplace=N is required.');
            return self::FAILURE;
        }
        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Loading emails from {$file}...");

        // Stream-read so a 1M-row CSV doesn't blow memory. Normalize on
        // read (lowercase + trim) so the matching set is canonical.
        $emails = [];
        $totalRead = 0;
        $invalid = 0;
        $handle = fopen($file, 'rb');
        if (!$handle) {
            $this->error("Could not open file for reading.");
            return self::FAILURE;
        }
        while (($line = fgets($handle)) !== false) {
            $totalRead++;
            $email = strtolower(trim($line));
            // Strip a trailing CR (CRLF line endings on Windows-saved CSVs).
            $email = rtrim($email, "\r");
            if ($email === '') continue;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;
                continue;
            }
            $emails[$email] = true;
        }
        fclose($handle);

        $uniqueEmails = array_keys($emails);
        $this->line("  Total lines: {$totalRead}");
        $this->line("  Invalid emails skipped: {$invalid}");
        $this->line("  Unique normalized emails: " . count($uniqueEmails));

        if (empty($uniqueEmails)) {
            $this->warn('Nothing to do.');
            return self::SUCCESS;
        }

        // Look up matching customers in chunks to stay under Postgres'
        // 65535 bound-param ceiling on a single statement.
        $matched = 0;
        $alreadyUnsubscribed = 0;
        $stillSubscribed = 0;
        foreach (array_chunk($uniqueEmails, 10000) as $chunk) {
            $rows = MarketplaceCustomer::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $chunk)
                ->select(['id', 'accepts_marketing'])
                ->get();
            $matched += $rows->count();
            $alreadyUnsubscribed += $rows->where('accepts_marketing', false)->count();
            $stillSubscribed += $rows->where('accepts_marketing', true)->count();
        }
        $notFound = count($uniqueEmails) - $matched;

        $this->line('');
        $this->line('Match summary:');
        $this->line("  Matched in DB:           {$matched}");
        $this->line("  Already unsubscribed:    {$alreadyUnsubscribed}");
        $this->line("  Currently subscribed:    {$stillSubscribed} ← to be updated");
        $this->line("  Not found in DB:         {$notFound}");

        if ($dryRun) {
            $this->info('[DRY RUN] No writes performed.');
            return self::SUCCESS;
        }

        if ($stillSubscribed === 0) {
            $this->info('No customers need updating — all matched customers are already unsubscribed.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info("Updating {$stillSubscribed} customers...");

        $updatedTotal = 0;
        foreach (array_chunk($uniqueEmails, 10000) as $chunk) {
            $updated = MarketplaceCustomer::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->where('accepts_marketing', true)
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $chunk)
                ->update([
                    'accepts_marketing' => false,
                    'marketing_consent_at' => null,
                    'updated_at' => now(),
                ]);
            $updatedTotal += $updated;
        }

        $this->info("Done. Updated {$updatedTotal} customers (set accepts_marketing=false).");

        return self::SUCCESS;
    }
}
