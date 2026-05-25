<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplacePayout;
use App\Services\Marketplace\SalesBreakdownService;
use Illuminate\Console\Command;

/**
 * Recompute ticket_breakdown (and optionally gross_amount/commission_amount/
 * amount) on an existing MarketplacePayout to reflect ONLY this payout's
 * slice — i.e. the tickets created after the previous payout for the same
 * event/organizer.
 *
 * Background: when this payout was originally created, the period_start
 * defaulted to the EVENT's created_at (not the previous payout's
 * created_at), so its ticket_breakdown snapshot included tickets that
 * were already covered by earlier payouts. This skews the per-payout
 * displays (Detalii bilete, Defalcare pe nivel de preț, Suma brută online)
 * even though the stored amount/gross/commission may be correct.
 *
 * Default behaviour: rewrite ticket_breakdown only; leave amount/gross/
 * commission untouched (they typically reflect the actual money paid).
 * Pass --rewrite-amounts to also recompute gross_amount, commission_amount,
 * amount from the new breakdown — use only when the original payout's
 * amounts were also wrong.
 *
 * Idempotent. Safe to re-run.
 */
class RecomputePayoutBreakdownCommand extends Command
{
    protected $signature = 'payouts:recompute-breakdown
        {payout_id : The MarketplacePayout id}
        {--dry-run : Report what would change without writing}
        {--rewrite-amounts : Also update gross_amount, commission_amount, amount}';

    protected $description = 'Recompute a payout\'s ticket_breakdown to reflect only its own slice (after previous payouts).';

    public function handle(SalesBreakdownService $service): int
    {
        $payoutId = (int) $this->argument('payout_id');
        $payout = MarketplacePayout::find($payoutId);

        if (!$payout) {
            $this->error("Payout {$payoutId} not found.");
            return self::FAILURE;
        }

        if (!$payout->event_id) {
            $this->error("Payout {$payoutId} has no event_id — cannot recompute (organizer-level payout).");
            return self::FAILURE;
        }

        $event = Event::find($payout->event_id);
        if (!$event) {
            $this->error("Event {$payout->event_id} not found.");
            return self::FAILURE;
        }

        $organizerId = (int) $payout->marketplace_organizer_id;

        // For this payout, periodStart = previous active payout's created_at
        // (or event created_at if this is the first). previous = older than
        // THIS payout's created_at, since later payouts shouldn't influence
        // an earlier payout's slice.
        $previousPayout = MarketplacePayout::query()
            ->where('event_id', $payout->event_id)
            ->where('marketplace_organizer_id', $organizerId)
            ->where('id', '!=', $payout->id)
            ->where('created_at', '<', $payout->created_at)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->orderByDesc('created_at')
            ->first(['id', 'reference', 'created_at']);

        $periodStartCarbon = $previousPayout?->created_at
            ?? ($event->created_at ? \Illuminate\Support\Carbon::parse($event->created_at) : null);
        // periodEnd = this payout's created_at — anything created after is
        // for a future payout, not this one.
        $periodEndCarbon = \Illuminate\Support\Carbon::parse($payout->created_at);

        $this->info("Payout {$payout->reference} (id={$payout->id})");
        $this->line("  event_id={$payout->event_id} organizer_id={$organizerId}");
        $this->line('  previous payout: ' . ($previousPayout ? "{$previousPayout->reference} created {$previousPayout->created_at}" : 'none (this is the first)'));
        $this->line('  new periodStart: ' . ($periodStartCarbon?->toDateTimeString() ?? 'null'));
        $this->line('  new periodEnd:   ' . $periodEndCarbon->toDateTimeString());
        $this->newLine();

        $newBreakdown = $service->buildForPayout($event, $periodStartCarbon, $periodEndCarbon, exactBounds: true);

        $oldRows = count($payout->ticket_breakdown ?? []);
        $newRows = count($newBreakdown);

        $newGross = 0.0;
        $newCommission = 0.0;
        $newNet = 0.0;
        foreach ($newBreakdown as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $price = (float) ($row['price'] ?? $row['unit_price'] ?? 0);
            $commPer = (float) ($row['commission_per_ticket'] ?? 0);
            $isOnTop = in_array($row['commission_mode'] ?? null, ['added_on_top', 'on_top'], true);
            $commission = $commPer * $qty;
            $gross = $price * $qty + ($isOnTop ? $commission : 0);
            $net = isset($row['net'])
                ? (float) $row['net']
                : ($gross - $commission - (float) ($row['discount'] ?? 0) - (float) ($row['extras'] ?? 0));

            $newGross += $gross;
            $newCommission += $commission;
            $newNet += $net;
        }
        $newGross = round($newGross, 2);
        $newCommission = round($newCommission, 2);
        $newNet = round($newNet, 2);

        $this->info('Comparison:');
        $this->line(sprintf('  ticket_breakdown rows: %d -> %d', $oldRows, $newRows));
        $this->line(sprintf('  gross_amount:          %.2f -> %.2f', (float) $payout->gross_amount, $newGross));
        $this->line(sprintf('  commission_amount:     %.2f -> %.2f', (float) $payout->commission_amount, $newCommission));
        $this->line(sprintf('  amount (net):          %.2f -> %.2f', (float) $payout->amount, $newNet));
        $this->newLine();

        // Also surface period changes so the Defalcare table reads the same
        // slice (it falls back to the saved period_start/period_end when the
        // blade can't derive its own bounds).
        $oldStart = $payout->period_start?->toDateString();
        $oldEnd = $payout->period_end?->toDateString();
        $newStart = $periodStartCarbon?->toDateString();
        $newEnd = $periodEndCarbon->toDateString();

        $this->line(sprintf('  period_start:          %s -> %s', $oldStart ?? 'null', $newStart ?? 'null'));
        $this->line(sprintf('  period_end:            %s -> %s', $oldEnd ?? 'null', $newEnd));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('--dry-run — no changes written.');
            return self::SUCCESS;
        }

        $updates = [
            'ticket_breakdown' => !empty($newBreakdown) ? $newBreakdown : null,
            'period_start' => $newStart,
            'period_end' => $newEnd,
        ];

        if ($this->option('rewrite-amounts')) {
            $updates['gross_amount'] = $newGross;
            $updates['commission_amount'] = $newCommission;
            $updates['amount'] = $newNet;
            $this->warn('--rewrite-amounts: amount/gross/commission will be overwritten.');
        }

        $payout->update($updates);

        $this->info('Saved.');
        return self::SUCCESS;
    }
}
