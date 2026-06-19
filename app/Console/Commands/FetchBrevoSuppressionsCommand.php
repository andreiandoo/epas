<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Pull the blocked/bounced/spam/unsubscribed list directly from Brevo's API
 * and flag matching marketplace_customers as email_suppressed.
 *
 * Brevo deprecated the "Email Reporting" export UI; the only stable path is
 * GET /v3/smtp/blockedContacts, which returns a unified feed of every
 * address that SMTP rejected — with a reason.code we map to our internal
 * suppression reasons:
 *
 *   hardBounce            → brevo_hard_bounce
 *   bounce / softBounce   → brevo_hard_bounce  (Brevo treats persistent softs as hards eventually)
 *   spam / complaint      → brevo_complaint
 *   blockedByUser /
 *   adminBlocked /
 *   unsubscribedViaApi /
 *   unsubscribedViaMA     → brevo_unsubscribed
 *   blockedByOurself /
 *   contactBlockedByAdmin / *   → brevo_blocked  (default catch-all)
 *
 * Spam-trap hits don't show as their own reason in this endpoint — Brevo
 * reports them as either complaints (spam) or hardBounces depending on the
 * trap operator. The brevo_spam_trap reason is reserved for direct trap
 * lists if/when Brevo exposes them.
 *
 *   php artisan customers:fetch-brevo-suppressions --marketplace=1 --dry-run
 *   php artisan customers:fetch-brevo-suppressions --marketplace=1
 *   # restrict to a window (Brevo paginates; defaults to a full sweep)
 *   php artisan customers:fetch-brevo-suppressions --marketplace=1 --since=2026-01-01
 *   # use a one-off key if .env doesn't have BREVO_API_KEY:
 *   php artisan customers:fetch-brevo-suppressions --api-key=xkeysib-...
 */
class FetchBrevoSuppressionsCommand extends Command
{
    protected $signature = 'customers:fetch-brevo-suppressions
                            {--marketplace= : restrict matching to this marketplace_client_id (recommended)}
                            {--api-key= : Brevo v3 API key (defaults to env BREVO_API_KEY)}
                            {--since= : Only fetch contacts blocked on/after this date (YYYY-MM-DD)}
                            {--until= : Only fetch contacts blocked on/before this date (YYYY-MM-DD)}
                            {--limit=100 : page size (Brevo max 100)}
                            {--no-overwrite : keep an earlier suppression reason if it differs}
                            {--dry-run : count matches but do not write to DB}
                            {--report= : optional CSV path to dump the fetched list}';

    protected $description = 'Pull blocked/bounced contacts from Brevo and flag them as email_suppressed';

    public function handle(): int
    {
        $apiKey = $this->option('api-key') ?: env('BREVO_API_KEY');
        if (!$apiKey) {
            $this->error('No API key — pass --api-key=xkeysib-... or set BREVO_API_KEY in .env');
            return self::FAILURE;
        }

        $marketplace = $this->option('marketplace');
        $since = $this->option('since');
        $until = $this->option('until');
        $limit = (int) $this->option('limit') ?: 100;
        $noOverwrite = (bool) $this->option('no-overwrite');
        $dryRun = (bool) $this->option('dry-run');
        $report = $this->option('report');

        $reasonMap = [
            'hardBounce' => 'brevo_hard_bounce',
            'bounce' => 'brevo_hard_bounce',
            'softBounce' => 'brevo_hard_bounce',
            'spam' => 'brevo_complaint',
            'complaint' => 'brevo_complaint',
            'unsubscribedViaApi' => 'brevo_unsubscribed',
            'unsubscribedViaMA' => 'brevo_unsubscribed',
            'blockedByUser' => 'brevo_unsubscribed',
            'adminBlocked' => 'brevo_blocked',
            'contactBlockedByAdmin' => 'brevo_blocked',
            'blockedByOurself' => 'brevo_blocked',
        ];

        $offset = 0;
        $fetched = 0;
        $rowsForReport = [];
        $emailToReason = [];

        $this->info('Fetching from Brevo API…');

        while (true) {
            $params = ['limit' => $limit, 'offset' => $offset];
            if ($since) $params['startDate'] = $since;
            if ($until) $params['endDate'] = $until;

            $resp = Http::withHeaders([
                'api-key' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(30)->get('https://api.brevo.com/v3/smtp/blockedContacts', $params);

            if (!$resp->successful()) {
                $this->error('Brevo API error ' . $resp->status() . ': ' . $resp->body());
                return self::FAILURE;
            }

            $data = $resp->json();
            $contacts = $data['contacts'] ?? [];
            $totalCount = $data['count'] ?? null;

            if (empty($contacts)) break;

            foreach ($contacts as $c) {
                $email = strtolower(trim((string) ($c['email'] ?? '')));
                $code = $c['reason']['code'] ?? 'unknown';
                if ($email === '') continue;

                $internal = $reasonMap[$code] ?? 'brevo_blocked';
                // Stronger reasons (hard_bounce, complaint, spam_trap) beat
                // softer (unsubscribed, blocked) when the same email shows up
                // multiple times in the feed across reasons.
                $priority = ['brevo_hard_bounce' => 4, 'brevo_spam_trap' => 4, 'brevo_complaint' => 3, 'brevo_blocked' => 2, 'brevo_unsubscribed' => 1];
                if (!isset($emailToReason[$email]) ||
                    ($priority[$internal] ?? 0) > ($priority[$emailToReason[$email]] ?? 0)) {
                    $emailToReason[$email] = $internal;
                }

                $rowsForReport[] = [$email, $code, $internal, $c['blockedAt'] ?? ''];
            }

            $fetched += count($contacts);
            $this->line("  page offset={$offset} → +" . count($contacts) . " (total fetched: {$fetched}" . ($totalCount ? " / {$totalCount}" : '') . ')');

            if (count($contacts) < $limit) break;
            $offset += $limit;
        }

        $this->info('Distinct emails: ' . count($emailToReason));
        if (empty($emailToReason)) {
            $this->warn('Nothing to import.');
            return self::SUCCESS;
        }

        if ($report) {
            $fh = fopen($report, 'w');
            fputcsv($fh, ['email', 'brevo_code', 'internal_reason', 'blocked_at']);
            foreach ($rowsForReport as $row) fputcsv($fh, $row);
            fclose($fh);
            $this->info("Report written to {$report}");
        }

        $matchedByReason = [];
        $skippedNoOverwrite = 0;
        $emails = array_keys($emailToReason);

        foreach (array_chunk($emails, 1000) as $chunk) {
            $reasonsForChunk = array_intersect_key($emailToReason, array_flip($chunk));
            $rows = DB::table('marketplace_customers')
                ->select('id', 'email', 'email_suppressed', 'email_suppression_reason')
                ->whereIn(DB::raw('LOWER(email)'), $chunk)
                ->when($marketplace, fn ($q) => $q->where('marketplace_client_id', $marketplace))
                ->get();

            $now = now();
            foreach ($rows as $row) {
                $reason = $reasonsForChunk[strtolower($row->email)] ?? 'brevo_blocked';
                if ($noOverwrite && $row->email_suppressed && $row->email_suppression_reason !== $reason) {
                    $skippedNoOverwrite++;
                    continue;
                }
                $matchedByReason[$reason] = ($matchedByReason[$reason] ?? 0) + 1;
                if (!$dryRun) {
                    DB::table('marketplace_customers')->where('id', $row->id)->update([
                        'email_suppressed' => true,
                        'email_suppression_reason' => $reason,
                        'email_suppressed_at' => $now,
                    ]);
                }
            }
        }

        $this->info('Match summary by reason:');
        foreach ($matchedByReason as $reason => $cnt) {
            $this->line("  {$reason}: {$cnt}");
        }
        if ($noOverwrite) {
            $this->info("Skipped (--no-overwrite, stronger reason already set): {$skippedNoOverwrite}");
        }
        if ($dryRun) {
            $this->warn('Dry run — nothing written.');
        }
        return self::SUCCESS;
    }
}
