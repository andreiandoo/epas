<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill: paid orders whose tickets stayed in 'pending' because a
 * silent observer error broke the payment-callback's ticket-activation
 * step. Marks tickets as 'valid' and confirms held seats for seated
 * orders. Idempotent — safe to re-run.
 *
 * Symptom that triggered this: PaymentController updated orders to
 * status=completed/payment_status=paid; the OrderObserver then threw
 * \Error("sum() on null") because OrganizerNotificationService::notifySale
 * accessed Order->orderItems (a non-existent relation) instead of
 * Order->items. The catch (\Exception) in the observer didn't catch
 * \Error, the exception bubbled past the tickets()->update() line, and
 * subsequent webhook retries hit the idempotency guard.
 */
class FixPendingTicketsOnPaidOrders extends Command
{
    protected $signature = 'fix:pending-tickets-on-paid-orders
        {--dry-run : Print what would change without writing to DB}
        {--id= : Limit to a single order id (for spot-checks)}
        {--ids= : Comma-separated list of order ids to target}
        {--send-emails : Also resend the order confirmation email (and beneficiary tickets) for the affected orders. Skipped if marketplace_email_logs already has a confirmation entry for the order, so re-runs do not double-send.}
        {--missing-emails-only : Skip the ticket activation pass entirely; only iterate paid orders whose order_number is not present in marketplace_email_logs and resend their confirmation. Use after a previous run already flipped tickets to valid without --send-emails. Implies --send-emails.}
        {--since= : When used with --missing-emails-only, only consider orders paid on or after this date (Y-m-d). Default: 7 days ago.}
        {--sleep=0 : Seconds to wait between successful email sends. Useful when one recipient owns many orders (Brevo spam flag risk) or to stay under provider rate limits. Default: 0 (no wait).}
        {--latest-per-customer : Group the result by customer_email and keep only the most recent paid order per recipient. Avoids spamming a customer who placed many duplicate/retry orders.}';

    protected $description = 'Activate tickets for paid orders that were left in pending due to the notifySale observer bug';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('id');
        $idsList = $this->option('ids');
        $sendEmails = (bool) $this->option('send-emails');
        $missingEmailsOnly = (bool) $this->option('missing-emails-only');
        $since = $this->option('since');
        $sleepSeconds = max(0, (int) $this->option('sleep'));
        $latestPerCustomer = (bool) $this->option('latest-per-customer');

        if ($missingEmailsOnly) {
            $sendEmails = true; // implied
        }

        $explicitIdScope = $onlyId || $idsList;

        // withoutGlobalScopes() — Order has implicit tenant filtering that is
        // bypassed in the web context but blocks raw CLI runs from seeing
        // marketplace orders. Tinker confirmed the problem (15 orders for an
        // email returned 0 via Eloquent, 15 via raw DB::select).
        $query = Order::withoutGlobalScopes()
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('payment_status', 'paid');

        if ($explicitIdScope) {
            // The operator targeted specific ids — do whatever needs doing on
            // each: flip pending tickets if any, send email if --send-emails
            // is set. No implicit pending/missing filter, otherwise a clean
            // order would be silently dropped from the run.
            $this->info('Mode: explicit ids (default pending-tickets filter bypassed)');
        } elseif ($missingEmailsOnly) {
            // Use case: tickets were already flipped to valid by an earlier run
            // without --send-emails, so the pending-ticket filter no longer
            // matches. Walk paid orders in the recent window and skip those
            // that already have a log entry for their order_number.
            $sinceDate = $since
                ? \Carbon\Carbon::parse($since)
                : now()->subDays(7);
            $query->where('paid_at', '>=', $sinceDate)
                ->whereNotNull('customer_email')
                // Skip channels that intentionally don't email the buyer:
                //   - external_import: legacy data backfilled in
                //   - pos_app:        cash POS handoff; receipt printed at till
                //   - test_order:     QA artifact
                ->whereNotIn('source', ['external_import', 'pos_app', 'test_order'])
                // Skip demo/seeded orders (DEMO-XXXX-NNNN naming convention).
                // They carry placeholder customer_email values and should
                // never reach a real inbox.
                ->where('order_number', 'not like', 'DEMO%');
            $this->info('Mode: missing-emails-only (paid_at >= ' . $sinceDate->toDateString() . ')');
        } else {
            // Default: only orders that still have pending tickets — the
            // primary symptom of the notifySale observer bug.
            $query->whereHas('tickets', fn ($q) => $q->where('status', 'pending'));
        }

        if ($onlyId) {
            $query->where('id', (int) $onlyId);
        }
        if ($idsList) {
            $ids = collect(explode(',', $idsList))
                ->map(fn ($v) => (int) trim($v))
                ->filter()
                ->all();
            $query->whereIn('id', $ids);
        }

        $orders = $query->with('tickets')->get();

        if ($missingEmailsOnly) {
            // Keep only orders whose order_number is NOT already in logs.
            $orders = $orders->reject(function ($o) {
                if (!$o->customer_email) return true;
                return DB::table('marketplace_email_logs')
                    ->where('to_email', $o->customer_email)
                    ->where(function ($q) use ($o) {
                        $q->where('subject', 'like', "%{$o->order_number}%")
                          ->orWhere('body_html', 'like', "%{$o->order_number}%");
                    })
                    ->exists();
            })->values();
        }

        if ($latestPerCustomer) {
            // Collapse duplicates by customer_email, keep the most recent
            // paid order per recipient. Treats null paid_at as oldest.
            $countBefore = $orders->count();
            $orders = $orders
                ->sortByDesc(fn ($o) => $o->paid_at?->getTimestamp() ?? 0)
                ->unique('customer_email')
                ->values();
            $countAfter = $orders->count();
            $deduped = $countBefore - $countAfter;
            if ($deduped > 0) {
                $this->info("Latest-per-customer: collapsed {$countBefore} → {$countAfter} ({$deduped} duplicate orders dropped).");
            }
        }

        if ($orders->isEmpty()) {
            $this->info('Nothing to fix.');
            return self::SUCCESS;
        }

        $what = $missingEmailsOnly ? 'missing email confirmation' : 'with pending tickets';
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$orders->count()} order(s) {$what}.");

        $totalTickets = 0;
        $seatConfirmFailures = 0;
        $emailsSent = 0;
        $emailsSkippedAlreadySent = 0;
        $emailFailures = 0;

        // PaymentController hosts sendOrderConfirmationEmail / sendBeneficiaryEmails.
        // Resolve once and reuse — both methods are public and stateless on the controller.
        $paymentController = $sendEmails
            ? app(\App\Http\Controllers\Api\MarketplaceClient\PaymentController::class)
            : null;

        foreach ($orders as $order) {
            $pending = $order->tickets->where('status', 'pending');
            $count = $pending->count();
            $totalTickets += $count;

            if ($dryRun) {
                $emailNote = $sendEmails ? ' + would attempt confirmation email' : '';
                $this->line("  - Order #{$order->order_number} (id={$order->id}): {$count} pending ticket(s){$emailNote}");
                continue;
            }

            DB::transaction(function () use ($order, $pending, &$seatConfirmFailures) {
                Ticket::whereIn('id', $pending->pluck('id'))->update([
                    'status' => 'valid',
                    'updated_at' => now(),
                ]);

                // For seated orders, confirm any seats still held in the seat
                // service. Idempotent — confirmPurchase is a no-op for already
                // sold seats. Wrap in try/catch so a partial seat-state
                // mismatch doesn't roll back the ticket activation.
                $seatedItems = $order->meta['seated_items'] ?? [];
                if (!empty($seatedItems)) {
                    try {
                        $seatHoldService = app(\App\Services\Seating\SeatHoldService::class);
                        foreach ($seatedItems as $seatedItem) {
                            $seatHoldService->confirmPurchase(
                                $seatedItem['event_seating_id'],
                                $seatedItem['seat_uids'],
                                'backfill-pending-tickets',
                                (int) ($order->total * 100)
                            );
                        }
                    } catch (\Throwable $e) {
                        $seatConfirmFailures++;
                        Log::warning('Backfill: seat confirm skipped', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $this->info("  - Order #{$order->order_number} (id={$order->id}): {$count} ticket(s) activated");

            if ($sendEmails && $order->customer_email && $order->marketplaceClient) {
                // Skip if a confirmation email already exists in
                // marketplace_email_logs for this order, so this command stays
                // idempotent across re-runs.
                $alreadySent = DB::table('marketplace_email_logs')
                    ->where('to_email', $order->customer_email)
                    ->where(function ($q) use ($order) {
                        $q->where('subject', 'like', "%{$order->order_number}%")
                          ->orWhere('body_html', 'like', "%{$order->order_number}%");
                    })
                    ->exists();

                if ($alreadySent) {
                    $emailsSkippedAlreadySent++;
                    $this->line("      ↪ confirmation email already on record — skipped");
                    continue;
                }

                try {
                    $paymentController->sendOrderConfirmationEmail($order);
                    $emailsSent++;
                    $this->info("      ↪ confirmation email queued");

                    // Beneficiary emails follow the same path as on payment.
                    if (method_exists($paymentController, 'sendBeneficiaryEmails')) {
                        try {
                            $paymentController->sendBeneficiaryEmails($order);
                        } catch (\Throwable $e) {
                            Log::warning('Backfill: beneficiary email failed', [
                                'order_id' => $order->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Pace the loop so a recipient who owns many orders does
                    // not see N emails land in the same second (Brevo / Gmail
                    // spam heuristics) and so we stay under provider rate
                    // limits when fixing 100+ orders at once.
                    if ($sleepSeconds > 0) {
                        sleep($sleepSeconds);
                    }
                } catch (\Throwable $e) {
                    $emailFailures++;
                    $this->error("      ↪ confirmation email FAILED: {$e->getMessage()}");
                    Log::error('Backfill: confirmation email failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $verb = $dryRun ? 'Would activate' : 'Activated';
        $this->info("{$verb} {$totalTickets} ticket(s) across {$orders->count()} order(s).");
        if ($seatConfirmFailures > 0) {
            $this->warn("Seat confirm skipped on {$seatConfirmFailures} order(s) — see laravel.log for details.");
        }
        if ($sendEmails && !$dryRun) {
            $this->info("Emails sent: {$emailsSent}, skipped (already sent): {$emailsSkippedAlreadySent}, failed: {$emailFailures}.");
        }

        return self::SUCCESS;
    }
}
