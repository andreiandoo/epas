<?php

namespace App\Console\Commands;

use App\Models\MarketplaceRefundItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill for marketplace_refund_items that were created with
 * commission_amount=0 and/or status='pending', and never caught up because
 * the refund was processed outside Tixello (e.g. directly in Netopia) or
 * because the parent order was a legacy_import that never populated
 * order.commission_amount.
 *
 * Two problems fixed in one pass, both scoped strictly to refund items
 * whose parent refund_request is NOT linked to a payout via
 * marketplace_payout_id — that gate ensures we never mutate a value that
 * already backs an invoice line at Oblio.
 *
 * 1. commission_amount = 0 → recompute from
 *    ticket.price × ticket_type.commission_rate/100 + ticket_type.commission_fixed
 *
 * 2. status = 'pending' → 'refunded', when parent ticket.status='refunded'
 *    AND parent refund_request.status IN ('approved','processing','completed','refunded').
 *    Reflects the real-world state — the refund happened, just outside
 *    Tixello's button so PaymentRefundService never got to flip the flag.
 *
 * Idempotent: rerun-safe (updates only rows where the target value
 * differs from what's already stored). Ships with --dry-run so operator
 * can preview the changeset before applying.
 *
 * Scope guard: never touches refund_items whose refundRequest has a
 * non-null marketplace_payout_id — those already back a decont/factura
 * with numbers frozen at emit-time. If a linked item genuinely needs
 * fixing, do it manually on that specific payout with the operator
 * approving each change (rare).
 */
class BackfillRefundItemCommissionCommand extends Command
{
    protected $signature = 'refunds:backfill-commission
        {--dry-run : Print what would change without touching the DB}
        {--limit=0 : Optional cap on rows processed (0 = no cap)}';

    protected $description = 'Backfill commission_amount + status on unlinked marketplace_refund_items so the Vânzări card + kept-commission math match reality.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info(($dry ? '[DRY-RUN] ' : '') . 'Scanning unlinked refund items with commission_amount=0 OR status=pending...');

        $query = MarketplaceRefundItem::query()
            ->with(['refundRequest', 'ticket.ticketType'])
            ->whereHas('refundRequest', fn ($q) => $q->whereNull('marketplace_payout_id'))
            ->whereHas('ticket', fn ($q) => $q->whereIn('status', ['refunded', 'cancelled']))
            ->where(function ($q) {
                $q->where('commission_amount', '=', 0)
                    ->orWhere('status', 'pending');
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $items = $query->get();
        $this->info('Candidate rows: ' . $items->count());

        $updatedCommission = 0;
        $updatedStatus = 0;
        $skipped = 0;
        $failed = 0;
        $deltaCommission = 0.0;
        $rows = [];

        DB::beginTransaction();

        try {
            foreach ($items as $item) {
                $ticket = $item->ticket;
                $tt = $ticket?->ticketType;
                $req = $item->refundRequest;
                if (!$ticket || !$tt || !$req) {
                    $skipped++;
                    continue;
                }

                $reqStatus = $req->status;
                $canFlipStatus = in_array($reqStatus, ['approved', 'processing', 'completed', 'refunded'], true);

                $ticketPrice = (float) ($ticket->price ?? 0);
                $rate = (float) ($tt->commission_rate ?? 0);
                $fixed = (float) ($tt->commission_fixed ?? 0);
                $newCommission = round(($ticketPrice * $rate / 100) + $fixed, 2);

                $currentCommission = (float) $item->commission_amount;
                $currentStatus = $item->status;

                $changes = [];

                if ($currentCommission == 0.0 && $newCommission > 0.0) {
                    $changes['commission_amount'] = $newCommission;
                    $deltaCommission += ($newCommission - $currentCommission);
                    $updatedCommission++;
                }

                if ($currentStatus === 'pending' && $canFlipStatus && $ticket->status === 'refunded') {
                    $changes['status'] = 'refunded';
                    $updatedStatus++;
                }

                if (empty($changes)) {
                    $skipped++;
                    continue;
                }

                $rows[] = [
                    'item' => $item->id,
                    'req' => $req->id,
                    'ticket' => $ticket->id,
                    'tt' => $tt->name,
                    'price' => $ticketPrice,
                    'mode' => $tt->commission_mode,
                    'rate' => $rate,
                    'was_comm' => $currentCommission,
                    'new_comm' => $changes['commission_amount'] ?? $currentCommission,
                    'was_status' => $currentStatus,
                    'new_status' => $changes['status'] ?? $currentStatus,
                ];

                if (!$dry) {
                    try {
                        $item->update($changes);
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->error("  failed refund_item {$item->id}: {$e->getMessage()}");
                    }
                }
            }

            if ($dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Aborted: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['refund_item', 'refund_req', 'ticket', 'ticket_type', 'price', 'mode', 'rate%', 'was_comm', 'new_comm', 'was_status', 'new_status'],
            array_map(fn ($r) => [
                $r['item'], $r['req'], $r['ticket'],
                mb_strimwidth((string) $r['tt'], 0, 30, '…'),
                number_format($r['price'], 2),
                $r['mode'],
                $r['rate'],
                number_format($r['was_comm'], 2),
                number_format($r['new_comm'], 2),
                $r['was_status'],
                $r['new_status'],
            ], $rows)
        );

        $this->newLine();
        $verb = $dry ? 'WOULD update' : 'updated';
        $this->info("commission_amount {$verb}: {$updatedCommission} rows (+{$deltaCommission} lei kept-commission newly visible)");
        $this->info("status {$verb}:            {$updatedStatus} rows");
        $this->info("skipped:                   {$skipped}");
        if ($failed > 0) {
            $this->error("failed:                    {$failed}");
        }

        if ($dry) {
            $this->newLine();
            $this->comment('Dry-run — no rows written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
