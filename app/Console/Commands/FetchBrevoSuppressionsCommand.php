<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Pull the full Brevo suppression set (blocked + bounced + spam + unsubscribed)
 * and flag matching marketplace_customers as email_suppressed.
 *
 * Brevo splits suppression data across two endpoints:
 *   GET /v3/smtp/blockedContacts        — hard bounces, complaints, admin blocks
 *   GET /v3/smtp/statistics/events      — paginated event feed; we pull
 *                                          event=unsubscribed (the blocked
 *                                          feed never includes unsubscribes)
 *                                          and optionally event=spam/error
 *                                          for belt-and-braces coverage.
 *
 * Mapping (Brevo code/event → internal suppression reason):
 *   hardBounce / bounce / softBounce  → brevo_hard_bounce
 *   spam / complaint                  → brevo_complaint
 *   unsubscribedViaApi/MA, blockedByUser, event=unsubscribed → brevo_unsubscribed
 *   adminBlocked / blockedByOurself / contactBlockedByAdmin / event=blocked → brevo_blocked
 *   event=error                       → brevo_blocked (delivery error, can't deliver)
 *
 * When the same email appears across feeds, the higher-severity reason wins
 * (hard_bounce > complaint > blocked > unsubscribed).
 *
 *   php artisan customers:fetch-brevo-suppressions --marketplace=1 --dry-run
 *   php artisan customers:fetch-brevo-suppressions --marketplace=1 --no-overwrite
 *   php artisan customers:fetch-brevo-suppressions --marketplace=1 --skip-events
 */
class FetchBrevoSuppressionsCommand extends Command
{
    protected $signature = 'customers:fetch-brevo-suppressions
                            {--marketplace= : restrict matching to this marketplace_client_id (recommended)}
                            {--api-key= : Brevo v3 API key (defaults to env BREVO_API_KEY)}
                            {--since= : Only fetch records on/after this date (YYYY-MM-DD); events endpoint requires it for ranges > 30 days}
                            {--until= : Only fetch records on/before this date (YYYY-MM-DD)}
                            {--limit=100 : page size (Brevo max 100)}
                            {--skip-events : do not query statistics/events (skips unsubscribers)}
                            {--no-overwrite : keep an earlier suppression reason if it differs}
                            {--dry-run : count matches but do not write to DB}
                            {--report= : optional CSV path to dump the fetched list}';

    protected $description = 'Pull blocked/bounced/spam/unsubscribed contacts from Brevo and flag them as email_suppressed';

    /** @var string */
    protected $apiKey;

    /** @var array<string,string> internal severity ordering for merging duplicates */
    protected array $priority = [
        'brevo_hard_bounce' => 4,
        'brevo_spam_trap'   => 4,
        'brevo_complaint'   => 3,
        'brevo_blocked'     => 2,
        'brevo_unsubscribed'=> 1,
    ];

    public function handle(): int
    {
        $this->apiKey = (string) ($this->option('api-key') ?: env('BREVO_API_KEY'));
        if (!$this->apiKey) {
            $this->error('No API key — pass --api-key=xkeysib-... or set BREVO_API_KEY in .env');
            return self::FAILURE;
        }

        $marketplace = $this->option('marketplace');
        $since = $this->option('since');
        $until = $this->option('until');
        $limit = (int) $this->option('limit') ?: 100;
        $skipEvents = (bool) $this->option('skip-events');
        $noOverwrite = (bool) $this->option('no-overwrite');
        $dryRun = (bool) $this->option('dry-run');
        $report = $this->option('report');

        $emailToReason = [];
        $rowsForReport = [];

        // ─── Phase 1 — blocked contacts (hard bounces, complaints, admin blocks) ──
        $this->info('Phase 1/2 — /v3/smtp/blockedContacts');
        $count = $this->fetchBlockedContacts($since, $until, $limit, $emailToReason, $rowsForReport);
        $this->info("  blocked-contacts emails accumulated: {$count}");

        // ─── Phase 2 — event feed (unsubscribed + supplementary blocked/error) ──
        if (!$skipEvents) {
            foreach (['unsubscribed', 'blocked', 'error'] as $eventType) {
                $this->info("Phase 2/2 — /v3/smtp/statistics/events event={$eventType}");
                $before = count($emailToReason);
                $this->fetchEvents($eventType, $since, $until, $limit, $emailToReason, $rowsForReport);
                $added = count($emailToReason) - $before;
                $this->info("  event={$eventType} added {$added} new emails (total distinct: " . count($emailToReason) . ')');
            }
        } else {
            $this->warn('Phase 2 skipped (--skip-events). Unsubscribers will NOT be flagged.');
        }

        $this->info('Distinct emails across all feeds: ' . count($emailToReason));
        if (empty($emailToReason)) {
            $this->warn('Nothing to import.');
            return self::SUCCESS;
        }

        if ($report) {
            $fh = fopen($report, 'w');
            fputcsv($fh, ['email', 'source', 'brevo_code_or_event', 'internal_reason', 'when']);
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

        $this->info('Match summary by reason (DB-matched only):');
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

    /**
     * Paginate through /v3/smtp/blockedContacts and merge into the email map.
     * Returns the running map size after this phase (NOT the row count).
     */
    protected function fetchBlockedContacts(?string $since, ?string $until, int $limit, array &$emailToReason, array &$rowsForReport): int
    {
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
        while (true) {
            $params = ['limit' => $limit, 'offset' => $offset];
            if ($since) $params['startDate'] = $since;
            if ($until) $params['endDate'] = $until;

            $resp = Http::withHeaders(['api-key' => $this->apiKey, 'Accept' => 'application/json'])
                ->timeout(30)
                ->get('https://api.brevo.com/v3/smtp/blockedContacts', $params);

            if (!$resp->successful()) {
                $this->error('  Brevo API error ' . $resp->status() . ': ' . $resp->body());
                break;
            }

            $contacts = $resp->json()['contacts'] ?? [];
            $totalCount = $resp->json()['count'] ?? null;
            if (empty($contacts)) break;

            foreach ($contacts as $c) {
                $email = strtolower(trim((string) ($c['email'] ?? '')));
                if ($email === '') continue;
                $code = $c['reason']['code'] ?? 'unknown';
                $internal = $reasonMap[$code] ?? 'brevo_blocked';

                if (!isset($emailToReason[$email]) ||
                    ($this->priority[$internal] ?? 0) > ($this->priority[$emailToReason[$email]] ?? 0)) {
                    $emailToReason[$email] = $internal;
                }
                $rowsForReport[] = [$email, 'blockedContacts', $code, $internal, $c['blockedAt'] ?? ''];
            }

            $this->line("    offset={$offset} → +" . count($contacts) . ($totalCount ? " (/ {$totalCount})" : ''));
            if (count($contacts) < $limit) break;
            $offset += $limit;
        }

        return count($emailToReason);
    }

    /**
     * Paginate through /v3/smtp/statistics/events?event=X for a single
     * event type (unsubscribed, blocked, error). Brevo caps page size at
     * 100 and total range at 30 days unless you pass startDate/endDate.
     */
    protected function fetchEvents(string $eventType, ?string $since, ?string $until, int $limit, array &$emailToReason, array &$rowsForReport): void
    {
        $eventReasonMap = [
            'unsubscribed' => 'brevo_unsubscribed',
            'blocked'      => 'brevo_blocked',
            'error'        => 'brevo_blocked',
            'spam'         => 'brevo_complaint',
            'hardBounces'  => 'brevo_hard_bounce',
        ];
        $internal = $eventReasonMap[$eventType] ?? 'brevo_blocked';

        $offset = 0;
        while (true) {
            $params = ['limit' => $limit, 'offset' => $offset, 'event' => $eventType, 'sort' => 'desc'];
            // Brevo's events endpoint requires a date range when sweeping the
            // full history; default to a wide window (5 years) so we don't
            // miss historic unsubscribes.
            if ($since) {
                $params['startDate'] = $since;
            } else {
                $params['startDate'] = now()->subYears(5)->format('Y-m-d');
            }
            if ($until) {
                $params['endDate'] = $until;
            } else {
                $params['endDate'] = now()->format('Y-m-d');
            }

            $resp = Http::withHeaders(['api-key' => $this->apiKey, 'Accept' => 'application/json'])
                ->timeout(30)
                ->get('https://api.brevo.com/v3/smtp/statistics/events', $params);

            if (!$resp->successful()) {
                $this->error('  Brevo events API error ' . $resp->status() . ': ' . $resp->body());
                break;
            }

            $events = $resp->json()['events'] ?? [];
            if (empty($events)) break;

            foreach ($events as $e) {
                $email = strtolower(trim((string) ($e['email'] ?? '')));
                if ($email === '') continue;

                if (!isset($emailToReason[$email]) ||
                    ($this->priority[$internal] ?? 0) > ($this->priority[$emailToReason[$email]] ?? 0)) {
                    $emailToReason[$email] = $internal;
                }
                $rowsForReport[] = [$email, "events/{$eventType}", $e['event'] ?? $eventType, $internal, $e['date'] ?? ''];
            }

            $this->line("    event={$eventType} offset={$offset} → +" . count($events));
            if (count($events) < $limit) break;
            $offset += $limit;
            // Brevo events caps offset at 5000 per range — break early so we
            // don't hammer the API for nothing.
            if ($offset >= 5000) {
                $this->warn("    event={$eventType} hit offset cap at 5000; narrow --since/--until if you need older rows");
                break;
            }
        }
    }
}
