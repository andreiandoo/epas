<?php

namespace App\Console\Commands;

use App\Models\Coupon\CouponCode;
use App\Models\Event;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit + backfill per-ticket discount_amount on ticket.meta for
 * historical orders that were created before per-ticket persistence
 * landed in CheckoutController.
 *
 * Without --backfill it just lists what's currently displayed vs what
 * would be displayed if we ran the eligibility check against the
 * original coupon / organizer promo. Pass --backfill to write the
 * per-ticket discount_amount onto each ticket's meta so the display
 * becomes exact (instead of falling back to the proportional
 * approximation in Ticket::getEffectivePrice).
 *
 *   php artisan discounts:audit
 *     [--marketplace=ID]   filter by marketplace_client_id
 *     [--from=YYYY-MM-DD]  created_at >= date
 *     [--to=YYYY-MM-DD]    created_at <= date
 *     [--order=171231]     a single order id
 *     [--limit=N]          stop after N orders (0 = unlimited)
 *     [--only-mismatched]  show only orders where proposal != current
 *     [--include-done]     also process orders fully meta-backfilled
 *     [--backfill]         persist proposed discount onto ticket.meta
 *
 * Safety: backfill ONLY writes when (a) proposal > 0 and (b) the
 * ticket has no existing meta.discount_amount. We never overwrite an
 * existing value — if the underlying coupon was deleted / edited
 * since the order, our reconstruction can be wrong and we don't want
 * to clobber a value that may have been written correctly at sale
 * time.
 */
class AuditTicketDiscountsCommand extends Command
{
    protected $signature = 'discounts:audit
        {--marketplace= : marketplace_client_id filter}
        {--from= : created_at >= date (Y-m-d)}
        {--to= : created_at <= date (Y-m-d)}
        {--order= : single order id}
        {--limit=0 : max orders to process (0 = no limit)}
        {--only-mismatched : show only orders with material display error (restricted promo or >0.5 RON diff)}
        {--only-restricted : show only orders where the promo did NOT cover all tickets (true corner case)}
        {--include-done : include orders where every ticket already has meta.discount_amount}
        {--backfill : persist proposed discount_amount onto ticket.meta}';

    protected $description = 'Audit and optionally backfill per-ticket discount_amount on ticket.meta';

    public function handle(): int
    {
        $query = Order::query()
            ->where('discount_amount', '>', 0)
            ->whereHas('tickets')
            ->orderBy('id');

        if ($id = $this->option('order')) {
            $query->where('id', (int) $id);
        }
        if ($mc = $this->option('marketplace')) {
            $query->where('marketplace_client_id', (int) $mc);
        }
        if ($from = $this->option('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $limit = (int) $this->option('limit');
        $backfill = (bool) $this->option('backfill');
        $onlyMismatch = (bool) $this->option('only-mismatched');
        $onlyRestricted = (bool) $this->option('only-restricted');
        $includeDone = (bool) $this->option('include-done');

        $totalSeen = 0;
        $totalSkippedDone = 0;
        $totalMismatched = 0;
        $totalWritten = 0;
        $totalTicketsWritten = 0;

        foreach ($query->cursor() as $order) {
            if ($limit > 0 && $totalSeen >= $limit) break;
            $totalSeen++;

            $order->load(['tickets.ticketType', 'tickets.marketplaceTicketType']);
            $tickets = $order->tickets;
            if ($tickets->isEmpty()) continue;

            $alreadyDone = $tickets->every(fn ($t) => isset(($t->meta ?? [])['discount_amount']));
            if ($alreadyDone && !$includeDone) {
                $totalSkippedDone++;
                continue;
            }

            $eligibleTicketIds = $this->resolveEligibleTicketIds($order, $tickets);
            $allocations = $this->allocateDiscount(
                discount: (float) $order->discount_amount,
                tickets: $tickets,
                eligibleTicketIds: $eligibleTicketIds,
            );

            // Build per-ticket comparison rows. Distinguish two kinds of
            // discrepancy:
            //   - "restricted" — the promo did NOT cover all tickets
            //     (the real corner case the audit was built for; current
            //     display falsely discounts ineligible tickets).
            //   - "displayDiff" — at least one ticket's currently-shown
            //     price differs from proposed by > 0.5 RON. Catches any
            //     other source of material display error. Tighter
            //     thresholds (<0.5) just flag the 1-cent rounding noise
            //     from how the proportional fallback rounds per-ticket
            //     vs the allocation's last-gets-remainder logic.
            $rows = [];
            $maxDelta = 0.0;
            foreach ($tickets as $t) {
                $price = (float) $t->price;
                $proposed = (float) ($allocations[$t->id] ?? 0);
                $proposedEffective = round(max(0, $price - $proposed), 2);
                $currentEffective = $t->getEffectivePrice();
                $delta = abs($currentEffective - $proposedEffective);
                if ($delta > $maxDelta) $maxDelta = $delta;
                $source = isset(($t->meta ?? [])['discount_amount'])
                    ? 'meta'
                    : ((float) $order->subtotal > 0 ? 'proportional' : 'raw');

                $rows[] = [
                    '#' . $t->id . ' ' . ($t->code ?? ''),
                    substr($this->ticketTypeName($t), 0, 24),
                    'E' . ($t->event_id ?? $t->marketplace_event_id ?? '?'),
                    'TT' . ($t->ticket_type_id ?? $t->marketplace_ticket_type_id ?? '?'),
                    number_format($price, 2),
                    number_format($currentEffective, 2) . " ({$source})",
                    number_format($proposed, 2),
                    number_format($proposedEffective, 2),
                    in_array($t->id, $eligibleTicketIds, true) ? 'yes' : 'no',
                ];
            }

            $allCount = $tickets->count();
            $eligibleCount = count($eligibleTicketIds);
            $isRestricted = $eligibleCount > 0 && $eligibleCount < $allCount;
            $bigDisplayDiff = $maxDelta > 0.5;
            $mismatched = $isRestricted || $bigDisplayDiff;

            if ($onlyRestricted && !$isRestricted) {
                continue;
            }
            if ($onlyMismatch && !$mismatched) {
                continue;
            }

            if ($mismatched) {
                $totalMismatched++;
            }

            $promo = $order->meta['promo_code'] ?? null;
            $restrictedTag = $isRestricted ? " [RESTRICTED {$eligibleCount}/{$allCount}]" : '';
            $diffTag = $bigDisplayDiff ? sprintf(' [Δmax=%.2f]', $maxDelta) : '';
            $this->newLine();
            $this->info(sprintf(
                'Order #%d %s — subtotal %s, discount %s, total %s — mc=%d %s%s%s',
                $order->id,
                $order->order_number ?? '',
                number_format((float) $order->subtotal, 2),
                number_format((float) $order->discount_amount, 2),
                number_format((float) $order->total, 2),
                (int) $order->marketplace_client_id,
                $order->created_at?->format('Y-m-d H:i') ?? '',
                $restrictedTag,
                $diffTag
            ));
            if ($promo) {
                $this->line(sprintf(
                    '  promo: %s (source=%s, value=%s, id=%s)',
                    $promo['code'] ?? '?',
                    $promo['source'] ?? '?',
                    $promo['value'] ?? '?',
                    $promo['id'] ?? '?'
                ));
            }
            $this->table(
                ['Ticket', 'Type', 'Event', 'TType', 'Price', 'Now', 'Disc Δ', 'Proposed', 'Eligible'],
                $rows
            );

            if ($backfill && $mismatched) {
                $written = 0;
                DB::transaction(function () use ($tickets, $allocations, &$written) {
                    foreach ($tickets as $t) {
                        $disc = (float) ($allocations[$t->id] ?? 0);
                        if ($disc <= 0) continue; // not eligible — nothing to write
                        $meta = $t->meta ?? [];
                        if (isset($meta['discount_amount'])) continue; // never overwrite
                        $meta['discount_amount'] = round($disc, 2);
                        $t->meta = $meta;
                        $t->saveQuietly();
                        $written++;
                    }
                });
                $this->info("  ✔ wrote {$written} ticket meta records");
                $totalWritten++;
                $totalTicketsWritten += $written;
            } elseif ($mismatched) {
                $this->warn('  (preview — pass --backfill to persist)');
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Seen: %d | Skipped (already done): %d | Mismatched: %d | Orders written: %d | Tickets written: %d',
            $totalSeen,
            $totalSkippedDone,
            $totalMismatched,
            $totalWritten,
            $totalTicketsWritten
        ));

        return self::SUCCESS;
    }

    /**
     * Determine which ticket IDs are eligible for this order's discount.
     * Returns ALL ticket ids when no promo data is found (fallback to
     * proportional-across-cart, which matches getEffectivePrice's
     * legacy fallback so unrestricted promos stay correct).
     */
    private function resolveEligibleTicketIds(Order $order, $tickets): array
    {
        $allIds = $tickets->pluck('id')->all();
        $promo = $order->meta['promo_code'] ?? null;
        if (!$promo || empty($promo['code'])) {
            return $allIds;
        }

        $source = $promo['source'] ?? null;
        $promoId = $promo['id'] ?? null;
        $code = $promo['code'] ?? null;

        if ($source === 'coupon') {
            $coupon = $promoId ? CouponCode::find($promoId) : null;
            if (!$coupon && $code) {
                $coupon = CouponCode::where('code', $code)->first();
            }
            if ($coupon) {
                return $this->filterByCoupon($coupon, $tickets);
            }
        }
        if ($source === 'organizer') {
            $orgPromo = $promoId ? MarketplaceOrganizerPromoCode::find($promoId) : null;
            if (!$orgPromo && $code) {
                $orgPromo = MarketplaceOrganizerPromoCode::where('code', $code)->first();
            }
            if ($orgPromo) {
                return $this->filterByOrganizerPromo($orgPromo, $tickets);
            }
        }

        // Source missing or model gone — try both tables before giving up.
        if (!$source && $code) {
            $orgPromo = MarketplaceOrganizerPromoCode::where('code', $code)->first();
            if ($orgPromo) {
                return $this->filterByOrganizerPromo($orgPromo, $tickets);
            }
            $coupon = CouponCode::where('code', $code)->first();
            if ($coupon) {
                return $this->filterByCoupon($coupon, $tickets);
            }
        }

        return $allIds;
    }

    private function filterByCoupon(CouponCode $coupon, $tickets): array
    {
        $applicableEventIds = !empty($coupon->applicable_events)
            ? array_map('intval', $coupon->applicable_events)
            : null;
        $organizerEventIds = null;
        if ($coupon->marketplace_organizer_id) {
            $organizerEventIds = Event::where('marketplace_organizer_id', $coupon->marketplace_organizer_id)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $applicableTicketTypes = !empty($coupon->applicable_ticket_types)
            ? array_map('intval', $coupon->applicable_ticket_types)
            : [];
        $hasEventFilter = $applicableEventIds !== null || $organizerEventIds !== null;
        $hasTicketTypeFilter = !empty($applicableTicketTypes);

        if (!$hasEventFilter && !$hasTicketTypeFilter) {
            return $tickets->pluck('id')->all();
        }

        return $tickets->filter(function ($t) use ($applicableEventIds, $organizerEventIds, $applicableTicketTypes, $hasEventFilter, $hasTicketTypeFilter) {
            if ($hasEventFilter) {
                $evId = (int) ($t->event_id ?? $t->marketplace_event_id ?? 0);
                if ($applicableEventIds !== null && !in_array($evId, $applicableEventIds, true)) return false;
                if ($organizerEventIds !== null && !in_array($evId, $organizerEventIds, true)) return false;
            }
            if ($hasTicketTypeFilter) {
                $ttId = (int) ($t->ticket_type_id ?? 0);
                if (!in_array($ttId, $applicableTicketTypes, true)) return false;
            }
            return true;
        })->pluck('id')->all();
    }

    private function filterByOrganizerPromo(MarketplaceOrganizerPromoCode $promo, $tickets): array
    {
        $appliesTo = $promo->applies_to;
        if ($appliesTo === 'specific_event') {
            $targetEventId = (int) $promo->marketplace_event_id;
            return $tickets->filter(function ($t) use ($targetEventId) {
                $eId = (int) ($t->event_id ?? $t->marketplace_event_id ?? 0);
                return $eId === $targetEventId;
            })->pluck('id')->all();
        }
        if ($appliesTo === 'ticket_type') {
            $allowedIds = $promo->getApplicableTicketTypeIdsList();
            return $tickets->filter(function ($t) use ($allowedIds) {
                $ttId = (int) ($t->marketplace_ticket_type_id ?? $t->ticket_type_id ?? 0);
                return $ttId > 0 && in_array($ttId, $allowedIds, true);
            })->pluck('id')->all();
        }
        return $tickets->pluck('id')->all();
    }

    /**
     * Distribute the order's discount across eligible tickets,
     * proportional to each ticket's price. Last eligible ticket gets
     * the rounding remainder so allocations sum to $discount exactly.
     *
     * Returns [ticket_id => discount_amount].
     */
    private function allocateDiscount(float $discount, $tickets, array $eligibleTicketIds): array
    {
        if ($discount <= 0 || empty($eligibleTicketIds)) {
            return [];
        }
        $eligible = $tickets->filter(fn ($t) => in_array($t->id, $eligibleTicketIds, true))->values();
        if ($eligible->isEmpty()) return [];

        $base = (float) $eligible->sum(fn ($t) => (float) $t->price);
        if ($base <= 0) return [];

        $allocations = [];
        $allocated = 0.0;
        $count = $eligible->count();
        foreach ($eligible as $i => $t) {
            if ($i === $count - 1) {
                $share = round($discount - $allocated, 2);
            } else {
                $share = round(((float) $t->price / $base) * $discount, 2);
                $allocated += $share;
            }
            $allocations[$t->id] = $share;
        }
        return $allocations;
    }

    private function ticketTypeName(Ticket $t): string
    {
        $raw = $t->ticketType?->name ?? $t->marketplaceTicketType?->name ?? 'Bilet';
        if (is_array($raw)) {
            return $raw['ro'] ?? $raw['en'] ?? reset($raw) ?: 'Bilet';
        }
        return (string) $raw;
    }
}
