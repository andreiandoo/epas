<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\MarketplaceEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Recover customers whose TRANSACTIONAL email (order confirmation, password reset)
 * was blocked by Brevo because they unsubscribed from marketing ("blocked : due to
 * unsubscribed user"). Brevo applies its single unsubscribe/blacklist to
 * transactional too, so a paying customer can end up without their tickets.
 *
 * Per recipient it: (1) unblocks them in Brevo (emailBlacklisted=false +
 * removes any transactional block), then (2) — for --orders — resends the ticket
 * purchase confirmation. Marketing stays off because our newsletter audiences
 * exclude email_suppressed / app-unsubscribed customers, so unblocking Brevo does
 * NOT re-market them.
 *
 * Going forward this is prevented by the app-side List-Unsubscribe header (Brevo
 * no longer records the unsubscribe). This command is the one-off cleanup for
 * contacts already blocked.
 *
 * Examples:
 *   php artisan brevo:recover-transactional --orders=182305,184539,134339 --emails=analar860@gmail.com --dry-run
 *   php artisan brevo:recover-transactional --orders=182305,184539,134339 --emails=analar860@gmail.com
 */
class RecoverBrevoBlockedTransactionalCommand extends Command
{
    protected $signature = 'brevo:recover-transactional
        {--orders= : Comma-separated order IDs — unblock recipient in Brevo AND resend the ticket confirmation}
        {--emails= : Comma-separated emails — unblock in Brevo only (e.g. password_reset cases)}
        {--api-key= : Brevo v3 API key (defaults to BREVO_API_KEY)}
        {--dry-run : Show what would happen without calling Brevo or sending}';

    protected $description = 'Unblock Brevo-blocked (unsubscribed) customers and resend their transactional emails';

    public function handle(): int
    {
        $apiKey = (string) ($this->option('api-key') ?: env('BREVO_API_KEY'));
        $dry = (bool) $this->option('dry-run');

        if (!$apiKey && !$dry) {
            $this->error('No Brevo API key — pass --api-key=xkeysib-... or set BREVO_API_KEY.');
            return self::FAILURE;
        }

        $orderIds = array_filter(array_map('trim', explode(',', (string) $this->option('orders'))));
        $extraEmails = array_filter(array_map('trim', explode(',', (string) $this->option('emails'))));

        // Resolve orders → emails, keep the order for resend.
        $orders = [];
        foreach ($orderIds as $oid) {
            $order = Order::find((int) $oid);
            if (!$order) {
                $this->warn("  order {$oid}: not found — skipped");
                continue;
            }
            $email = $order->customer_email ?: $order->marketplaceCustomer?->email;
            if (!$email) {
                $this->warn("  order {$oid}: no email — skipped");
                continue;
            }
            $orders[] = ['order' => $order, 'email' => $email];
        }

        // Unique set of emails to unblock (order recipients + --emails).
        $emails = collect($orders)->pluck('email')
            ->merge($extraEmails)
            ->map(fn ($e) => strtolower(trim($e)))
            ->filter()
            ->unique()
            ->values();

        $this->info(($dry ? '[DRY RUN] ' : '') . "Unblocking {$emails->count()} email(s) in Brevo, resending " . count($orders) . ' order(s).');

        // 1) Unblock in Brevo FIRST (so the resend below isn't blocked again).
        foreach ($emails as $email) {
            if ($dry) {
                $this->line("  [dry] unblock {$email}");
                continue;
            }
            $this->unblockInBrevo($apiKey, $email);
        }

        // 2) Resend the ticket confirmation for each order.
        foreach ($orders as $row) {
            $order = $row['order'];
            if ($dry) {
                $this->line("  [dry] resend ticket_purchase for order {$order->id} → {$row['email']}");
                continue;
            }
            try {
                $mc = $order->marketplaceClient;
                if (!$mc) {
                    $this->warn("  order {$order->id}: no marketplace client — cannot resend");
                    continue;
                }
                $ok = (new MarketplaceEmailService($mc))->sendTicketPurchaseEmail($order);
                $this->line('  ' . ($ok ? '✓' : '✗') . " resent ticket_purchase for order {$order->id} → {$row['email']}");
            } catch (\Throwable $e) {
                $this->error("  order {$order->id}: resend failed — {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    /**
     * Remove the marketing unsubscribe (emailBlacklisted) and any transactional
     * block for this address in Brevo. Best-effort: 404 just means nothing to do.
     */
    protected function unblockInBrevo(string $apiKey, string $email): void
    {
        $headers = ['api-key' => $apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'];

        // Marketing unsubscribe (this is what "blocked : due to unsubscribed user" is).
        $r1 = Http::withHeaders($headers)
            ->put('https://api.brevo.com/v3/contacts/' . rawurlencode($email), ['emailBlacklisted' => false]);

        // Transactional block list (hard bounces / manual blocks), if present.
        $r2 = Http::withHeaders($headers)
            ->delete('https://api.brevo.com/v3/smtp/blockedContacts/' . rawurlencode($email));

        $this->line(sprintf(
            '  unblock %s → contacts:%s blockedContacts:%s',
            $email,
            $r1->status(),
            $r2->status()
        ));
    }
}
