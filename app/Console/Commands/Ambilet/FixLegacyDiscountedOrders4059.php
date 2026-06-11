<?php

namespace App\Console\Commands\Ambilet;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot back-fill for 9 orders imported from old.ambilet.ro on event
 * 4059 that lost their 20% promo discount during migration. The customer
 * paid (catalog * qty * 1.06) - (catalog * qty * 0.20), i.e. catalog +
 * 6% commission added on top, minus a 20% catalog discount. New tables
 * stored only the post-discount post-commission total on the order
 * (subtotal == total) and left tickets.price = NULL + meta empty, so
 * downstream payout / decont logic credited the organizer at full
 * catalog × qty.
 *
 * What this writes:
 *   tickets.price                  = catalog (50 for Categ II, 60 for Categ I)
 *   tickets.meta.discount_amount   = 10 / 12 per ticket (20 % of catalog)
 *   orders.subtotal                = catalog * qty   (was: total = post-disc)
 *   orders.discount_amount         = subtotal * 0.20
 *   orders.total                   = catalog * qty * 1.06 - discount   (unchanged)
 *   orders.promo_code              = '20OFFLEGACY'    (marker for audit)
 *   orders.commission_amount       = catalog * qty * 0.06   (added_on_top)
 *
 * Idempotent: skips orders whose subtotal already differs from total
 * (meaning the discount snapshot is already in place) and whose
 * meta.discount_amount is set on at least one ticket.
 *
 * Dry-run by default. --apply persists. Always run with --apply for
 * the actual back-fill; the dry-run is a guard so you can eyeball the
 * numbers first.
 */
class FixLegacyDiscountedOrders4059 extends Command
{
    protected $signature = 'ambilet:fix-legacy-discount-orders-4059
        {--apply : Persist changes (default is dry-run)}';

    protected $description = 'Back-fill missing 20% promo discount on 9 legacy ambilet orders for event 4059';

    /** Catalog price per ticket type for the affected types. */
    protected array $catalog = [
        9873 => 50.0, // Categ II
        9874 => 60.0, // Categ I
    ];

    protected float $discountPct = 0.20;
    protected float $commissionPct = 0.06;
    protected string $promoCodeMarker = '20OFFLEGACY';

    /** order_number values to patch — verified against the old WP CSV. */
    protected array $orderNumbers = [
        'AMB-864779',
        'AMB-865361',
        'AMB-865410',
        'AMB-865735',
        'AMB-865821',
        'AMB-866006',
        'AMB-866507',
        'AMB-866544',
        'AMB-866963',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . ' Patching 9 legacy ambilet orders on event 4059 with 20% promo discount...');
        $this->newLine();

        $changedOrders = 0;
        $skippedAlreadyFixed = 0;
        $missing = 0;

        DB::beginTransaction();
        try {
            foreach ($this->orderNumbers as $on) {
                $order = Order::where('order_number', $on)->first();
                if (!$order) {
                    $this->warn("  {$on}: NOT FOUND");
                    $missing++;
                    continue;
                }

                $tickets = Ticket::where('order_id', $order->id)->get();
                if ($tickets->isEmpty()) {
                    $this->warn("  {$on}: order found but has no tickets");
                    $missing++;
                    continue;
                }

                // Idempotency check — if discount_amount is already > 0 OR
                // any ticket has meta.discount_amount set, treat as fixed
                // and skip.
                $alreadyHasOrderDiscount = (float) $order->discount_amount > 0;
                $alreadyHasTicketMetaDiscount = $tickets->contains(function ($t) {
                    $m = is_array($t->meta) ? $t->meta : [];
                    return array_key_exists('discount_amount', $m) && (float) $m['discount_amount'] > 0;
                });
                if ($alreadyHasOrderDiscount || $alreadyHasTicketMetaDiscount) {
                    $this->line("  {$on}: already patched (skipping)");
                    $skippedAlreadyFixed++;
                    continue;
                }

                $subtotalCatalog = 0.0;
                $totalDiscount = 0.0;
                $ticketUpdates = [];

                foreach ($tickets as $t) {
                    $ttId = (int) $t->ticket_type_id;
                    if (!isset($this->catalog[$ttId])) {
                        $this->error("  {$on}: ticket {$t->id} has unexpected ticket_type_id={$ttId}; aborting this order");
                        continue 2;
                    }
                    $catalog = $this->catalog[$ttId];
                    $perTicketDiscount = round($catalog * $this->discountPct, 2);
                    $subtotalCatalog += $catalog;
                    $totalDiscount += $perTicketDiscount;
                    $newMeta = is_array($t->meta) ? $t->meta : [];
                    $newMeta['discount_amount'] = $perTicketDiscount;
                    $newMeta['promo_code_legacy'] = $this->promoCodeMarker;
                    $ticketUpdates[] = [
                        'ticket' => $t,
                        'price' => $catalog,
                        'meta' => $newMeta,
                    ];
                }

                $commissionAmount = round($subtotalCatalog * $this->commissionPct, 2);
                // total stays as what was already stored (= what customer paid)
                $expectedTotal = round($subtotalCatalog + $commissionAmount - $totalDiscount, 2);
                $storedTotal = (float) $order->total;

                $totalsMatch = abs($expectedTotal - $storedTotal) < 0.005;

                $this->line(sprintf(
                    "  %s | qty=%d | subtotal: %.2f → %.2f | discount: 0.00 → %.2f | commission: %.2f → %.2f | total stays %.2f (formula gives %.2f %s)",
                    $on, $tickets->count(),
                    (float) $order->subtotal, $subtotalCatalog,
                    $totalDiscount,
                    (float) $order->commission_amount, $commissionAmount,
                    $storedTotal, $expectedTotal,
                    $totalsMatch ? '✓' : '⚠ MISMATCH'
                ));

                if (!$totalsMatch) {
                    $this->error("  {$on}: stored total ({$storedTotal}) doesn't equal formula ({$expectedTotal}); skipping to avoid corrupting the order");
                    continue;
                }

                if ($apply) {
                    foreach ($ticketUpdates as $u) {
                        $u['ticket']->update([
                            'price' => $u['price'],
                            'meta' => $u['meta'],
                        ]);
                    }
                    $order->update([
                        'subtotal' => $subtotalCatalog,
                        'discount_amount' => $totalDiscount,
                        'commission_amount' => $commissionAmount,
                        'promo_code' => $this->promoCodeMarker,
                    ]);
                }

                $changedOrders++;
            }

            if ($apply) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Changed: {$changedOrders}" . ($apply ? ' (applied + committed)' : ' (would change, rolled back)'));
        $this->info("Already patched: {$skippedAlreadyFixed}");
        if ($missing > 0) {
            $this->warn("Missing: {$missing}");
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('Dry-run — re-run with --apply to persist.');
        } else {
            $this->newLine();
            $this->info('Next step: re-run discount recompute on affected payouts:');
            $this->line('  php artisan payouts:recompute-discounts --event=4059 --update-amount --apply');
        }

        return self::SUCCESS;
    }
}
