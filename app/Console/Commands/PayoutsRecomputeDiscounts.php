<?php

namespace App\Console\Commands;

use App\Models\MarketplacePayout;
use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Recompute per-row discount + payout.discount_amount + payout.amount
 * for existing payouts whose snapshot was written by the buggy
 * SalesBreakdownService that used effective_total proportional formula.
 *
 * The new logic reads per-ticket effective price straight off
 * Ticket::getEffectivePrice() (which returns price − meta.discount_amount
 * written at checkout). Per-type discount = Σ(catalog − effective) for
 * the latest qty tickets of that type matched against this payout's
 * breakdown.
 *
 * Dry-run by default — prints proposed changes per payout. --apply
 * actually writes.
 */
class PayoutsRecomputeDiscounts extends Command
{
    protected $signature = 'payouts:recompute-discounts
        {--apply : Persist changes (default is dry-run)}
        {--payout= : Recompute a single payout ID}
        {--event= : Recompute all payouts for a single event ID}
        {--marketplace= : Limit to payouts of a marketplace_client_id}';

    protected $description = 'Recompute breakdown.discount + payout.discount_amount + payout.amount from per-ticket meta.discount_amount';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $payoutId = $this->option('payout');
        $eventId = $this->option('event');
        $marketplaceId = $this->option('marketplace');

        $q = MarketplacePayout::query()->whereNotNull('event_id');
        if ($payoutId) $q->where('id', (int) $payoutId);
        if ($eventId) $q->where('event_id', (int) $eventId);
        if ($marketplaceId) $q->where('marketplace_client_id', (int) $marketplaceId);

        $total = (clone $q)->count();
        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " Scanning {$total} payouts...");

        $changed = 0;
        $unchanged = 0;
        $skipped = 0;
        $totalDiscountDelta = 0.0;

        $q->orderBy('id')->chunk(50, function ($chunk) use (&$changed, &$unchanged, &$skipped, &$totalDiscountDelta, $apply) {
            foreach ($chunk as $p) {
                $bd = $p->ticket_breakdown ?? [];
                if (empty($bd)) {
                    $skipped++;
                    continue;
                }

                $newDiscountByType = $this->computeDiscountPerType($p);

                $newBd = [];
                $totalNewDiscount = 0.0;
                $totalGross = 0.0;
                $changedRow = false;
                foreach ($bd as $item) {
                    $ttId = (int) ($item['ticket_type_id'] ?? 0);
                    $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 0);
                    $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                    $oldDisc = (float) ($item['discount'] ?? 0);
                    $newDisc = round((float) ($newDiscountByType[$ttId] ?? 0), 2);

                    if (abs($oldDisc - $newDisc) > 0.005) {
                        $changedRow = true;
                    }

                    $item['discount'] = $newDisc;
                    $newBd[] = $item;

                    if ($qty > 0 && $price > 0) {
                        $totalGross += $price * $qty;
                    }
                    $totalNewDiscount += $newDisc;
                }

                $oldDiscountTotal = (float) ($p->discount_amount ?? 0);
                $newDiscountTotal = round($totalNewDiscount, 2);
                $oldAmount = (float) ($p->amount ?? 0);
                $newAmount = round($totalGross - $newDiscountTotal, 2);

                $discountDelta = round($newDiscountTotal - $oldDiscountTotal, 2);
                $amountDelta = round($newAmount - $oldAmount, 2);
                $totalDiscountDelta += abs($discountDelta);

                if (!$changedRow && abs($discountDelta) < 0.005 && abs($amountDelta) < 0.005) {
                    $unchanged++;
                    continue;
                }

                $this->line(sprintf(
                    "  payout %d (event %d): discount %s → %s (Δ %s), amount %s → %s (Δ %s)",
                    $p->id, $p->event_id,
                    number_format($oldDiscountTotal, 2), number_format($newDiscountTotal, 2),
                    ($discountDelta >= 0 ? '+' : '') . number_format($discountDelta, 2),
                    number_format($oldAmount, 2), number_format($newAmount, 2),
                    ($amountDelta >= 0 ? '+' : '') . number_format($amountDelta, 2),
                ));

                if ($apply) {
                    $p->update([
                        'ticket_breakdown' => $newBd,
                        'discount_amount' => $newDiscountTotal,
                        'amount' => $newAmount,
                    ]);
                }
                $changed++;
            }
        });

        $this->newLine();
        $this->info("Total scanned: {$total}");
        $this->info("Changed: {$changed}" . ($apply ? ' (applied)' : ' (would change)'));
        $this->info("Unchanged: {$unchanged}");
        if ($skipped > 0) {
            $this->warn("Skipped (no breakdown): {$skipped}");
        }
        $this->info("Total |Δ discount|: " . number_format($totalDiscountDelta, 2) . ' lei');

        if (!$apply && $changed > 0) {
            $this->newLine();
            $this->warn('Dry-run — re-run with --apply to persist.');
        }

        return self::SUCCESS;
    }

    /**
     * Per-ticket-type discount for THIS payout, using the same
     * "latest N tickets per type" selector the PDF / model accessor use.
     * Returns [ticket_type_id => Σ(catalog − effective)].
     */
    protected function computeDiscountPerType(MarketplacePayout $p): array
    {
        $qtyByType = [];
        foreach (($p->ticket_breakdown ?? []) as $item) {
            $ttId = (int) ($item['ticket_type_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 0);
            if ($ttId > 0 && $qty > 0) {
                $qtyByType[$ttId] = ($qtyByType[$ttId] ?? 0) + $qty;
            }
        }
        if (empty($qtyByType)) return [];

        $tickets = Ticket::with(['order:id,created_at'])
            ->whereHas('ticketType', fn ($qq) => $qq->where('event_id', $p->event_id))
            ->whereIn('ticket_type_id', array_keys($qtyByType))
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', function ($qq) {
                $qq->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'external_import')
                    ->where('source', '!=', 'pos_app');
            })
            ->get(['id', 'ticket_type_id', 'order_id', 'price', 'meta', 'status']);

        if ($tickets->isEmpty()) return [];

        $tickets = $tickets->sortByDesc(function ($t) {
            return [$t->order?->created_at?->timestamp ?? 0, $t->id];
        });

        $perType = [];
        foreach ($tickets->groupBy('ticket_type_id') as $ttId => $group) {
            $ttId = (int) $ttId;
            $needed = (int) ($qtyByType[$ttId] ?? 0);
            if ($needed <= 0) continue;

            $sum = 0.0;
            foreach ($group->take($needed) as $t) {
                $catalog = (float) ($t->attributes['price'] ?? $t->price ?? 0);
                $eff = (float) $t->getEffectivePrice();
                $sum += max(0.0, $catalog - $eff);
            }
            $perType[$ttId] = round($sum, 2);
        }

        return $perType;
    }
}
