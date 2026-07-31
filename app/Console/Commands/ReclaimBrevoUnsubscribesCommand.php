<?php

namespace App\Console\Commands;

use App\Models\MarketplaceCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Move Brevo's newsletter-unsubscribes into our app and unblock them in Brevo, so
 * they stop blocking TRANSACTIONAL email (order confirmations, password resets).
 *
 * Brevo applies its single unsubscribe list to transactional too. Going forward
 * that never happens again because newsletters now carry OUR List-Unsubscribe
 * header (unsubscribes stay app-side). This command clears the existing backlog:
 *
 *   For each Brevo blocked contact whose reason is an UNSUBSCRIBE (hard bounces /
 *   spam are intentionally left blocked):
 *     1. mark the customer email_suppressed in our DB → excluded from newsletter
 *        audiences (we remember they're unsubscribed), while transactional still
 *        sends (that path does not check email_suppressed);
 *     2. unblock them in Brevo (remove from blockedContacts + emailBlacklisted=false)
 *        so their transactional mail flows.
 *
 * Idempotent: re-running is safe (already-unblocked → 404, suppression is a no-op).
 *
 *   php artisan brevo:reclaim-unsubscribes --dry-run
 *   php artisan brevo:reclaim-unsubscribes            # (nohup for large backlogs)
 */
class ReclaimBrevoUnsubscribesCommand extends Command
{
    protected $signature = 'brevo:reclaim-unsubscribes
        {--api-key= : Brevo v3 API key (defaults to BREVO_API_KEY)}
        {--marketplace=1 : Marketplace client id for the app-side suppression}
        {--offset=0 : Start offset into Brevo blockedContacts (for resuming)}
        {--limit=0 : Max unsubscribe contacts to process (0 = all)}
        {--sleep-ms=120 : Pause between unblock calls to respect Brevo rate limits}
        {--dry-run : Count + sample only; no writes to Brevo or our DB}';

    protected $description = 'Unblock Brevo newsletter-unsubscribers and record them as unsubscribed in the app';

    public function handle(): int
    {
        $apiKey = (string) ($this->option('api-key') ?: env('BREVO_API_KEY'));
        $dry = (bool) $this->option('dry-run');
        $mcId = (int) $this->option('marketplace');
        $max = (int) $this->option('limit');
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;

        if (!$apiKey) {
            $this->error('No Brevo API key — pass --api-key=xkeysib-... or set BREVO_API_KEY.');
            return self::FAILURE;
        }

        $headers = ['api-key' => $apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $pageSize = 100;
        $offset = (int) $this->option('offset');
        $seen = 0; $processed = 0; $suppressed = 0; $unblocked = 0; $skippedOther = 0;

        $this->info(($dry ? '[DRY RUN] ' : '') . 'Reclaiming Brevo newsletter-unsubscribes…');

        while (true) {
            $resp = Http::withHeaders($headers)
                ->get('https://api.brevo.com/v3/smtp/blockedContacts', ['limit' => $pageSize, 'offset' => $offset]);

            if ($resp->status() === 429) {
                $this->warn('  rate limited — sleeping 3s');
                usleep(3_000_000);
                continue;
            }
            if (!$resp->ok()) {
                $this->error('  Brevo API error ' . $resp->status() . ' — ' . $resp->body());
                break;
            }

            $contacts = $resp->json()['contacts'] ?? [];
            if (empty($contacts)) {
                break;
            }

            foreach ($contacts as $c) {
                $seen++;
                $email = strtolower(trim((string) ($c['email'] ?? '')));
                if (!$email) {
                    continue;
                }
                $reason = strtolower(
                    (string) ($c['reason']['message'] ?? '') . ' ' . (string) ($c['reason']['code'] ?? '')
                );

                // Only reclaim UNSUBSCRIBES. Hard bounces / spam complaints must
                // stay blocked (dead / hostile addresses).
                if (stripos($reason, 'unsubscrib') === false) {
                    $skippedOther++;
                    continue;
                }

                $processed++;
                if ($dry) {
                    if ($processed <= 25) {
                        $this->line("  [dry] {$email} — {$reason}");
                    }
                    if ($max && $processed >= $max) {
                        break 2;
                    }
                    continue;
                }

                // 1) Remember internally as unsubscribed → excluded from newsletter
                //    audiences; transactional still sends.
                $customer = MarketplaceCustomer::where('email', $email)
                    ->when($mcId, fn ($q) => $q->where('marketplace_client_id', $mcId))
                    ->first();
                if ($customer) {
                    $customer->markHardSuppressed('brevo_unsubscribe_reclaimed');
                    $suppressed++;
                }

                // 2) Unblock in Brevo so transactional flows.
                Http::withHeaders($headers)
                    ->delete('https://api.brevo.com/v3/smtp/blockedContacts/' . rawurlencode($email));
                Http::withHeaders($headers)
                    ->put('https://api.brevo.com/v3/contacts/' . rawurlencode($email), ['emailBlacklisted' => false]);
                $unblocked++;

                if ($unblocked % 50 === 0) {
                    $this->line("  …{$unblocked} unblocked ({$suppressed} suppressed in-app)");
                }
                if ($sleepUs) {
                    usleep($sleepUs);
                }
                if ($max && $processed >= $max) {
                    break 2;
                }
            }

            $offset += $pageSize;
            if (count($contacts) < $pageSize) {
                break;
            }
        }

        $this->info(sprintf(
            '%sDone. scanned=%d unsubscribe_contacts=%d unblocked=%d app_suppressed=%d other_reasons_left_blocked=%d',
            $dry ? '[DRY RUN] ' : '',
            $seen,
            $processed,
            $unblocked,
            $suppressed,
            $skippedOther
        ));

        return self::SUCCESS;
    }
}
