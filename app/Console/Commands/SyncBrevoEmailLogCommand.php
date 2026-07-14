<?php

namespace App\Console\Commands;

use App\Models\MarketplaceEmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Pull the real delivery events from Brevo for a single MarketplaceEmailLog and
 * write them onto delivered_at / opened_at / clicked_at / bounced_at.
 *
 * Newsletter logs are created WITHOUT a message_id (SendNewsletterJob), so the
 * Brevo webhook — which matches by message-id — can never update them. This
 * command closes that gap on demand by querying Brevo's event feed for the
 * recipient in the window around sent_at:
 *
 *   GET /v3/smtp/statistics/events?email=...&startDate=...&endDate=...
 *
 * The raw events it prints also double as a diagnosis: if Brevo returns no
 * `delivered` (only `blocked` / `error`, or nothing at all) the mail was never
 * actually delivered — typically because the address sits on Brevo's blocklist.
 *
 * Usage:
 *   php artisan email-logs:sync-brevo 141737 --dry-run
 *   php artisan email-logs:sync-brevo 141737
 */
class SyncBrevoEmailLogCommand extends Command
{
    protected $signature = 'email-logs:sync-brevo
        {log : MarketplaceEmailLog id}
        {--api-key= : Brevo v3 API key (defaults to env BREVO_API_KEY)}
        {--days=30 : Days after sent_at to scan for events}
        {--dry-run : Show what Brevo returned + would update, without writing}';

    protected $description = 'Pull delivered/opened/clicked/bounced events from Brevo for one email log and update its fields.';

    public function handle(): int
    {
        $log = MarketplaceEmailLog::find($this->argument('log'));
        if (!$log) {
            $this->error('Email log not found: ' . $this->argument('log'));
            return self::FAILURE;
        }

        $apiKey = (string) ($this->option('api-key') ?: env('BREVO_API_KEY'));
        if (!$apiKey) {
            $this->error('No API key — pass --api-key=xkeysib-... or set BREVO_API_KEY.');
            return self::FAILURE;
        }

        $email = $log->to_email;
        if (!$email) {
            $this->error('Log has no to_email.');
            return self::FAILURE;
        }

        $anchor = $log->sent_at ? Carbon::parse($log->sent_at) : now();
        $start = $anchor->copy()->subDay()->toDateString();
        // Brevo rejects an endDate in the future, so clamp to today.
        $endCarbon = $anchor->copy()->addDays((int) $this->option('days'));
        if ($endCarbon->gt(now())) {
            $endCarbon = now();
        }
        $end = $endCarbon->toDateString();

        $this->info("Log #{$log->id} → {$email} | sent_at={$log->sent_at} | scanning Brevo {$start} … {$end}");

        // Pull every event for this recipient in the window (paginated).
        $events = [];
        $offset = 0;
        $limit = 100;
        do {
            $resp = Http::withHeaders(['api-key' => $apiKey, 'Accept' => 'application/json'])
                ->timeout(30)
                ->get('https://api.brevo.com/v3/smtp/statistics/events', [
                    'email' => $email,
                    'startDate' => $start,
                    'endDate' => $end,
                    'limit' => $limit,
                    'offset' => $offset,
                    'sort' => 'asc',
                ]);

            if ($resp->failed()) {
                $this->error('Brevo API error (' . $resp->status() . '): ' . $resp->body());
                return self::FAILURE;
            }

            $batch = $resp->json('events') ?? [];
            $events = array_merge($events, $batch);
            $offset += $limit;
        } while (count($batch) === $limit);

        if (empty($events)) {
            $this->warn('No Brevo events for this recipient in the window — Brevo has no record it was delivered (likely blocked/suppressed or never handed off).');
        } else {
            $this->line(str_pad('EVENT', 18) . str_pad('DATE', 30) . 'messageId / reason');
            foreach ($events as $e) {
                $idOrReason = ($e['messageId'] ?? '') !== '' ? $e['messageId'] : ($e['reason'] ?? '');
                $this->line(str_pad((string) ($e['event'] ?? '?'), 18) . str_pad((string) ($e['date'] ?? '?'), 30) . $idOrReason);
            }
        }

        // Multiple emails can hit the same recipient inside the window, each
        // with its own message-id. Anchor on the message-id whose send
        // (`requests`) is closest to THIS log's sent_at, then keep only that
        // message's events — otherwise a different email's bounce/open would be
        // attributed to this log.
        $anchoredMsgId = null;
        $reqPool = array_filter($events, fn ($e) => strtolower((string) ($e['event'] ?? '')) === 'requests' && !empty($e['messageId']) && !empty($e['date']));
        $pool = !empty($reqPool) ? $reqPool : array_filter($events, fn ($e) => !empty($e['messageId']) && !empty($e['date']));
        $bestDiff = null;
        foreach ($pool as $e) {
            $diff = abs(Carbon::parse($e['date'])->diffInSeconds($anchor));
            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff = $diff;
                $anchoredMsgId = trim((string) $e['messageId'], '<>');
            }
        }
        if ($anchoredMsgId !== null) {
            $this->line("Matched message-id (send closest to sent_at): {$anchoredMsgId}");
            $events = array_values(array_filter($events, fn ($e) => trim((string) ($e['messageId'] ?? ''), '<>') === $anchoredMsgId));
        }

        // Earliest timestamp per mapped event bucket.
        $first = function (array $names) use ($events) {
            $best = null;
            foreach ($events as $e) {
                $ev = strtolower((string) ($e['event'] ?? ''));
                if (in_array($ev, $names, true) && !empty($e['date'])) {
                    $t = Carbon::parse($e['date']);
                    if ($best === null || $t->lt($best)) {
                        $best = $t;
                    }
                }
            }
            return $best;
        };

        $deliveredAt = $first(['delivered']);
        $openedAt = $first(['opened', 'uniqueopened', 'unique_opened']);
        $clickedAt = $first(['click', 'clicks', 'uniqueclicks', 'unique_clicks']);
        $bouncedAt = $first(['hardbounces', 'softbounces', 'bounces', 'hard_bounce', 'soft_bounce', 'blocked', 'error', 'invalid', 'spam']);

        // A click implies an open.
        if ($clickedAt && !$openedAt) {
            $openedAt = $clickedAt;
        }

        $updates = [];
        if ($deliveredAt && !$log->delivered_at) { $updates['delivered_at'] = $deliveredAt; }
        if ($openedAt && !$log->opened_at) { $updates['opened_at'] = $openedAt; }
        if ($clickedAt && !$log->clicked_at) { $updates['clicked_at'] = $clickedAt; }
        if ($bouncedAt && !$log->bounced_at) { $updates['bounced_at'] = $bouncedAt; }

        // Highest-signal status.
        if ($clickedAt) { $updates['status'] = 'clicked'; }
        elseif ($openedAt) { $updates['status'] = 'opened'; }
        elseif ($deliveredAt) { $updates['status'] = 'delivered'; }
        elseif ($bouncedAt) { $updates['status'] = 'bounced'; }

        // Backfill the message-id from Brevo if the log never stored one.
        if (empty($log->message_id)) {
            foreach ($events as $e) {
                if (!empty($e['messageId'])) {
                    $updates['message_id'] = trim((string) $e['messageId'], '<>');
                    break;
                }
            }
        }

        $this->newLine();
        if (empty($updates)) {
            $this->warn('Nothing to update (no new events, or fields already set).');
            return self::SUCCESS;
        }

        $this->table(['field', 'value'], collect($updates)->map(fn ($v, $k) => [$k, (string) $v])->values()->all());

        if ($this->option('dry-run')) {
            $this->info('Dry run — not written.');
            return self::SUCCESS;
        }

        // Keep the raw events for the audit trail.
        $meta = is_array($log->metadata) ? $log->metadata : [];
        $meta['brevo_events_sync'] = ['at' => now()->toIso8601String(), 'events' => $events];
        $updates['metadata'] = $meta;

        $log->update($updates);
        $this->info("Updated email log #{$log->id}.");

        return self::SUCCESS;
    }
}
