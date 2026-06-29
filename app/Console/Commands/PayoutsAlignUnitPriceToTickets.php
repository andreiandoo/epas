<?php

namespace App\Console\Commands;

use App\Models\MarketplacePayout;
use Illuminate\Console\Command;

/**
 * Realign per-row unit_price in payout.ticket_breakdown so it matches
 * what the customers actually paid (max tier price across the slice)
 * instead of the ticket_type's CURRENT catalog. The gap surfaces when
 * an operator edited a ticket_type's price AFTER sales — the snapshot
 * read the post-sale catalog and inflated gross/net by the delta × qty,
 * producing a fictional discount the customer never received.
 *
 * Real promos are NOT affected: with a promo, tickets.price stays at
 * the original catalog and meta.discount_amount holds the per-ticket
 * cut. The original buildRemainingTicketsItems already encodes that
 * correctly through `sorted_tickets.catalog`, so this command reads
 * the saved tiers (which carry the EFFECTIVE paid prices) and uses
 * their MAX as the corrected unit_price.
 *
 * For each row:
 *   - new_unit_price = max(tier.price) when tiers exist and max > 0
 *   - commission_per_ticket = recompute at new_unit_price × rate
 *     (commission stays on catalog by Tixello policy; new catalog
 *     reflects real sale price, so marketplace commission drops by
 *     the same proportion the per-ticket gross drops)
 *   - gross = qty × new_unit_price + (on_top ? qty × new_commission : 0)
 *   - net = gross − commission − discount
 *
 * Dry-run by default; --apply persists changes. payouts.amount is
 * left intact unless --update-amount is passed (already-paid payouts
 * shouldn't have their historic amount silently rewritten).
 */
class PayoutsAlignUnitPriceToTickets extends Command
{
    protected $signature = 'payouts:align-unit-price-to-tickets
        {--apply : Persist changes (default is dry-run)}
        {--payout= : Process a single payout ID}
        {--event= : Process all payouts for a single event ID}
        {--marketplace= : Limit to payouts of a marketplace_client_id}
        {--update-amount : Also rewrite payouts.amount (default keeps the historic value)}
        {--max-delta=0 : Skip payouts whose |Δ net| > this lei (0 = no cap)}';

    protected $description = 'Realign payout.ticket_breakdown unit_price to max(tier.price), correcting inflated snapshots after post-sale ticket_type edits';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $payoutId = $this->option('payout');
        $eventId = $this->option('event');
        $marketplaceId = $this->option('marketplace');
        $updateAmount = (bool) $this->option('update-amount');
        $maxDelta = (float) $this->option('max-delta');

        $q = MarketplacePayout::query()->whereNotNull('ticket_breakdown');
        if ($payoutId) $q->where('id', (int) $payoutId);
        if ($eventId) $q->where('event_id', (int) $eventId);
        if ($marketplaceId) $q->where('marketplace_client_id', (int) $marketplaceId);

        $total = (clone $q)->count();
        $mode = $updateAmount ? 'breakdown + amount' : 'breakdown only';
        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " Scanning {$total} payouts ({$mode})...");

        $changed = 0;
        $unchanged = 0;
        $skippedByCap = 0;
        $totalNetDelta = 0.0;
        $totalCommDelta = 0.0;
        $bigChanges = [];

        $q->orderBy('id')->chunk(50, function ($chunk) use (
            &$changed, &$unchanged, &$skippedByCap, &$totalNetDelta, &$totalCommDelta, &$bigChanges,
            $apply, $updateAmount, $maxDelta
        ) {
            foreach ($chunk as $p) {
                $bd = $p->ticket_breakdown ?? [];
                if (empty($bd)) {
                    $unchanged++;
                    continue;
                }

                $newBd = [];
                $rowChanged = false;
                $oldNetTotal = 0.0;
                $newNetTotal = 0.0;
                $oldCommTotal = 0.0;
                $newCommTotal = 0.0;

                foreach ($bd as $item) {
                    $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 0);
                    $oldUnit = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                    $oldCommPer = (float) ($item['commission_per_ticket'] ?? 0);
                    $mode = $item['commission_mode'] ?? null;
                    $isOnTop = in_array($mode, ['added_on_top', 'on_top'], true);
                    $oldGross = (float) ($item['gross'] ?? ($qty * $oldUnit + ($isOnTop ? $qty * $oldCommPer : 0)));
                    $oldComm = (float) ($item['commission_amount'] ?? ($qty * $oldCommPer));
                    $oldDisc = (float) ($item['discount'] ?? 0);
                    $oldNet = (float) ($item['net'] ?? ($oldGross - $oldComm - $oldDisc));

                    $oldNetTotal += $oldNet;
                    $oldCommTotal += $oldComm;

                    $tiers = $item['tiers'] ?? null;
                    $maxTier = 0.0;
                    if (is_array($tiers)) {
                        foreach ($tiers as $tier) {
                            $tp = (float) ($tier['price'] ?? 0);
                            if ($tp > $maxTier) $maxTier = $tp;
                        }
                    }

                    // Only correct when tiers reveal a max BELOW current
                    // unit_price. When tiers match or exceed unit_price,
                    // the snapshot is already truthful — leave it alone.
                    // Also guard against zero-tier rows (Invitatie 0×N
                    // would otherwise wipe the row's unit_price to 0
                    // and lose the type name attribution).
                    if ($maxTier > 0 && $maxTier < $oldUnit - 0.005) {
                        // Commission scales with the new catalog basis.
                        // Per row config the rate isn't stored, so we
                        // back it out from the old (commission per ticket
                        // / old unit price). Round to 4 decimals to keep
                        // micro-cent precision the original calc used.
                        $rate = $oldUnit > 0 ? $oldCommPer / $oldUnit : 0;
                        $newCommPer = round($maxTier * $rate, 4);
                        $newGross = $qty * $maxTier + ($isOnTop ? $qty * $newCommPer : 0);
                        $newComm = $qty * $newCommPer;
                        // Discount stays as stored — it's the real promo
                        // amount from sorted_tickets (catalog − effective)
                        // and doesn't depend on which unit_price we render.
                        $newNet = $newGross - $newComm - $oldDisc;

                        $item['unit_price'] = $maxTier;
                        $item['price'] = $maxTier;
                        $item['commission_per_ticket'] = $newCommPer;
                        $item['gross'] = round($newGross, 2);
                        $item['commission_amount'] = round($newComm, 2);
                        $item['net'] = round($newNet, 2);
                        $rowChanged = true;
                        $newNetTotal += $newNet;
                        $newCommTotal += $newComm;
                    } else {
                        $newNetTotal += $oldNet;
                        $newCommTotal += $oldComm;
                    }

                    $newBd[] = $item;
                }

                if (!$rowChanged) {
                    $unchanged++;
                    continue;
                }

                $netDelta = round($newNetTotal - $oldNetTotal, 2);
                $commDelta = round($newCommTotal - $oldCommTotal, 2);

                if ($maxDelta > 0 && abs($netDelta) > $maxDelta) {
                    $skippedByCap++;
                    continue;
                }

                $totalNetDelta += abs($netDelta);
                $totalCommDelta += abs($commDelta);

                $oldAmount = (float) ($p->amount ?? 0);
                $newAmount = round($newNetTotal, 2);
                $amountDelta = round($newAmount - $oldAmount, 2);

                if (abs($amountDelta) >= 500) {
                    $bigChanges[] = [$p->id, $p->event_id, $oldAmount, $newAmount, $amountDelta, $netDelta, $commDelta];
                }

                $this->line(sprintf(
                    "  payout %d (event %d): net %s → %s (Δ %s), comm %s → %s (Δ %s)" . ($updateAmount ? ", amount %s → %s (Δ %s)" : ""),
                    $p->id, $p->event_id,
                    number_format($oldNetTotal, 2), number_format($newNetTotal, 2),
                    ($netDelta >= 0 ? '+' : '') . number_format($netDelta, 2),
                    number_format($oldCommTotal, 2), number_format($newCommTotal, 2),
                    ($commDelta >= 0 ? '+' : '') . number_format($commDelta, 2),
                    number_format($oldAmount, 2), number_format($newAmount, 2),
                    ($amountDelta >= 0 ? '+' : '') . number_format($amountDelta, 2),
                ));

                if ($apply) {
                    $payload = ['ticket_breakdown' => $newBd];
                    if ($updateAmount) {
                        $payload['amount'] = $newAmount;
                    }
                    // Always refresh the aggregates that are pure
                    // derivations of the breakdown — commission_amount AND
                    // gross_amount. The "Editează bilete decontate" modal's
                    // "Stare curentă" card reads gross_amount for Brut,
                    // commission_amount for Comision; leaving gross_amount
                    // stale produces a mixed view (new comm + old brut).
                    // discount_amount is untouched (we don't manufacture
                    // discounts here — that's a separate code path).
                    // amount stays controlled by --update-amount because
                    // it represents the historical paid-out value on
                    // completed payouts; flipping it silently would
                    // misrepresent what the organizer was actually paid.
                    $newGrossTotal = 0.0;
                    foreach ($newBd as $r) {
                        $newGrossTotal += (float) ($r['gross'] ?? 0);
                    }
                    $payload['commission_amount'] = round($newCommTotal, 2);
                    $payload['gross_amount'] = round($newGrossTotal, 2);
                    $p->update($payload);
                }
                $changed++;
            }
        });

        $this->newLine();
        $this->info("Total scanned: {$total}");
        $this->info("Changed: {$changed}" . ($apply ? ' (applied)' : ' (would change)'));
        $this->info("Unchanged: {$unchanged}");
        if ($skippedByCap > 0) {
            $this->warn("Skipped (|Δ net| > {$maxDelta}): {$skippedByCap}");
        }
        $this->info("Total |Δ net|: " . number_format($totalNetDelta, 2) . ' lei');
        $this->info("Total |Δ commission|: " . number_format($totalCommDelta, 2) . ' lei');

        if (!empty($bigChanges) && !$updateAmount) {
            $this->newLine();
            $this->warn("⚠  These payouts have a >=500 lei gap between stored amount and the corrected net.");
            $this->warn("   Stored amount preserved (since --update-amount was not passed).");
            $this->line("   payout_id | event_id | stored_amt | corrected_net | Δamt   | Δnet   | Δcomm");
            foreach (array_slice($bigChanges, 0, 30) as [$pid, $eid, $oa, $na, $da, $nd, $cd]) {
                $this->line(sprintf("   %-9d | %-8d | %10s | %13s | %+7.2f | %+7.2f | %+7.2f",
                    $pid, $eid, number_format($oa, 2), number_format($na, 2), $da, $nd, $cd));
            }
            if (count($bigChanges) > 30) {
                $this->line("   ... and " . (count($bigChanges) - 30) . " more.");
            }
        }

        if (!$apply && $changed > 0) {
            $this->newLine();
            $this->warn('Dry-run — re-run with --apply to persist.');
            $this->line('  Use --update-amount only when you also want payouts.amount rewritten.');
        }

        return self::SUCCESS;
    }
}
