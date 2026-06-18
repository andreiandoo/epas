<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scan marketplace_customers for addresses whose domain has no MX record.
 *
 * Triggered by Brevo blocking the sending account after counting 74 invalid
 * MX addresses on outgoing campaigns. checkdnsrr() is offline (no API calls,
 * no quota) and resolves a typical Ambilet domain list (~5k unique domains
 * out of ~97k customers) in under a minute on a small VPS.
 *
 *   php artisan customers:audit-email-mx --marketplace=1 --report=/tmp/bad-mx.csv
 *   php artisan customers:audit-email-mx --marketplace=1 --apply
 *   php artisan customers:audit-email-mx --marketplace=1 --apply --report=/tmp/bad-mx.csv
 *
 * Without --apply the command only writes the CSV report — DB stays intact.
 */
class AuditCustomerEmailMxCommand extends Command
{
    protected $signature = 'customers:audit-email-mx
                            {--marketplace= : marketplace_client_id to scan (omit for all)}
                            {--apply : set email_suppressed=true / reason=invalid_mx for every match}
                            {--report= : path to write the CSV report (optional)}
                            {--skip-cached : skip domains already covered by an existing invalid_mx row (faster re-runs)}';

    protected $description = 'Find marketplace_customers whose email domain has no MX record (bad addresses)';

    public function handle(): int
    {
        $marketplace = $this->option('marketplace');
        $apply = (bool) $this->option('apply');
        $report = $this->option('report');
        $skipCached = (bool) $this->option('skip-cached');

        $query = DB::table('marketplace_customers')
            ->select('id', 'email', 'marketplace_client_id', 'email_suppressed', 'email_suppression_reason')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($marketplace) {
            $query->where('marketplace_client_id', $marketplace);
        }

        $total = (clone $query)->count();
        $this->info("Scanning {$total} customer rows" . ($marketplace ? " (marketplace={$marketplace})" : ''));

        // Domain → 'ok' | 'bad'. Avoids resolving the same domain N times for N
        // customers on the same provider — gmail.com/yahoo.com alone are
        // typically ~70 % of the corpus.
        $domainStatus = [];
        $badCustomers = [];
        $cursor = $query->orderBy('id')->cursor();
        $progress = $this->output->createProgressBar($total);
        $progress->start();

        foreach ($cursor as $row) {
            $progress->advance();
            $email = strtolower(trim((string) $row->email));
            if (!str_contains($email, '@')) {
                continue;
            }
            [, $domain] = explode('@', $email, 2);
            $domain = trim($domain);
            if ($domain === '') {
                continue;
            }

            if ($skipCached && $row->email_suppressed && $row->email_suppression_reason === 'invalid_mx') {
                $domainStatus[$domain] = $domainStatus[$domain] ?? 'bad';
            }

            if (!array_key_exists($domain, $domainStatus)) {
                // checkdnsrr returns true if any MX record exists. Fallback to
                // A record is intentionally NOT done — RFC 5321 allows MX-less
                // domains to receive on the A record, but Brevo flags those
                // anyway, and any spam-trap operator would set up an MX.
                $domainStatus[$domain] = @checkdnsrr($domain, 'MX') ? 'ok' : 'bad';
            }

            if ($domainStatus[$domain] === 'bad') {
                $badCustomers[] = [
                    'id' => (int) $row->id,
                    'email' => $email,
                    'domain' => $domain,
                    'marketplace_client_id' => (int) $row->marketplace_client_id,
                ];
            }
        }
        $progress->finish();
        $this->newLine();

        $badDomainCount = count(array_filter($domainStatus, fn ($v) => $v === 'bad'));
        $okDomainCount = count(array_filter($domainStatus, fn ($v) => $v === 'ok'));
        $this->info("Resolved domains: {$okDomainCount} ok, {$badDomainCount} bad");
        $this->info('Affected customer rows: ' . count($badCustomers));

        if ($report) {
            $fh = fopen($report, 'w');
            fputcsv($fh, ['customer_id', 'email', 'domain', 'marketplace_client_id']);
            foreach ($badCustomers as $row) {
                fputcsv($fh, $row);
            }
            fclose($fh);
            $this->info("Report written to {$report}");
        }

        if (!$apply) {
            $this->warn('Dry run — pass --apply to flag email_suppressed=true');
            return self::SUCCESS;
        }

        $now = now();
        $touched = 0;
        // Batch by IDs to stay efficient on the ~5k bad-domain customer range
        // we expect. Single UPDATE … WHERE IN avoids per-row roundtrips.
        foreach (array_chunk(array_column($badCustomers, 'id'), 500) as $chunk) {
            $touched += DB::table('marketplace_customers')
                ->whereIn('id', $chunk)
                ->update([
                    'email_suppressed' => true,
                    'email_suppression_reason' => 'invalid_mx',
                    'email_suppressed_at' => $now,
                ]);
        }
        $this->info("Flagged {$touched} customers as email_suppressed (reason=invalid_mx)");
        return self::SUCCESS;
    }
}
