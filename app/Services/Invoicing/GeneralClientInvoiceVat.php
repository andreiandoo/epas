<?php

namespace App\Services\Invoicing;

use App\Models\MarketplaceClient;
use App\Models\MarketplacePayout;
use App\Services\Marketplace\SalesBreakdownService;
use Carbon\Carbon;

/**
 * Marketplace VAT on the "Client Divers" invoice (commission paid by the
 * customers on top of the ticket price). The commission of a decont falls in
 * two slices, by sale date:
 *
 *   before  sold before the marketplace's VAT start date: no VAT;
 *   taxed   sold on/after the start date: the commission is NET and the VAT is
 *           added on top, whether or not checkout already charged it to the
 *           customer (decision 2026-10-19: the VAT is owed on every sale from
 *           the start date, Ambilet pays what it did not collect).
 *
 * Lines are per ticket type (quantity x commission per ticket) and each one
 * carries its NET amount and its own vat_rate.
 */
class GeneralClientInvoiceVat
{
    public const BEFORE_NOTE = ' (vânzări înainte de înregistrarea în scopuri de TVA)';

    /**
     * @param  float  $baseCommission  commission of the decont excluding POS and kept commission
     * @param  array  $keptRows  rows of MarketplacePayout::getKeptCommissionRowsForPayout()
     * @return array{base: array<int, array>, kept: array<int, array>, subtotal: float, vat_amount: float, vat_rate: float}
     *   every line: net, vat, rate, slice ('before'|'taxed'), qty, unit_price, ticket_type_name
     */
    public function compute(MarketplacePayout $payout, MarketplaceClient $marketplace, float $baseCommission, array $keptRows): array
    {
        $baseCommission = round($baseCommission, 2);
        $rate = $marketplace->vat_payer ? (float) data_get($marketplace->settings, 'tax.vat_rate', 21) : 0.0;

        if ($rate <= 0) {
            return $this->result(
                $baseCommission > 0.005 ? [$this->line($baseCommission, 0.0, 'before')] : [],
                array_map(fn ($r) => ['row' => $r] + $this->rowLine($r, 0.0, 'before'), $keptRows)
            );
        }

        $since = $marketplace->vatPayerSince();
        $sinceUtc = $since ? Carbon::instance($since->toDateTime())->utc() : null;

        $periodStart = $payout->period_start ? $payout->period_start->copy()->startOfDay() : null;
        $periodEnd = $payout->period_end ? $payout->period_end->copy()->endOfDay() : null;

        // On-top commission rows per ticket type sold in [from, to) inside this decont's period.
        $onTopRows = function (?Carbon $from, ?Carbon $to) use ($payout, $periodStart, $periodEnd): array {
            if (!$payout->event) {
                return [];
            }
            $start = $from && (!$periodStart || $from->gt($periodStart)) ? $from : $periodStart;
            $end = $to ? $to->copy()->subSecond() : null;
            if ($periodEnd && (!$end || $end->gt($periodEnd))) {
                $end = $periodEnd;
            }
            if ($start && $end && $end->lt($start)) {
                return [];
            }
            $bd = app(SalesBreakdownService::class)
                ->build($payout->event, $start, $end, excludePos: true, dateColumn: 'paid_at', exactBounds: true);
            $rows = $payout->filterRowsToIssuer(array_values($bd['per_type'] ?? []));

            return array_values(array_filter($rows, fn ($row) => in_array($row['commission_mode'] ?? '', ['on_top', 'added_on_top'], true)
                && (float) ($row['commission_amount'] ?? 0) > 0.005));
        };
        $sum = fn (array $rows) => round(array_sum(array_map(fn ($r) => (float) ($r['commission_amount'] ?? 0), $rows)), 2);

        // No start date = the rate applies to the whole decont.
        $taxedRows = $onTopRows($sinceUtc, null);
        $taxed = $sum($taxedRows);
        // The decont snapshot is taken when the decont is created: a decont made
        // during its last day misses that day's later sales (e.g. payout 3559,
        // created the morning of 16.10 with period_end 16.10). When the live
        // sales of the whole period exceed the snapshot, the taxed slice stops
        // at the snapshot's moment.
        $beforeLive = $sinceUtc ? $sum($onTopRows(null, $sinceUtc)) : 0.0;
        if ($payout->created_at && abs($beforeLive + $taxed - $baseCommission) >= 0.01) {
            $snapshotRows = $onTopRows($sinceUtc, $payout->created_at->copy()->utc()->addSecond());
            if (abs($beforeLive + $sum($snapshotRows) - $baseCommission) < 0.01) {
                $taxedRows = $snapshotRows;
                $taxed = $sum($snapshotRows);
            }
        }
        if ($taxed > $baseCommission + 0.01) {
            // The decont snapshot holds less than the live sales: bill what it holds.
            $taxedRows = [];
            $taxed = $baseCommission;
        }
        // What the decont snapshot holds beyond the taxed slice is pre-VAT.
        $before = round($baseCommission - $taxed, 2);

        $base = [];
        if ($before > 0.005) {
            $beforeRows = $sinceUtc ? $onTopRows(null, $sinceUtc) : [];
            if ($beforeRows && abs($sum($beforeRows) - $before) < 0.01) {
                foreach ($beforeRows as $row) {
                    $base[] = $this->rowLine($row, 0.0, 'before');
                }
            } else {
                $base[] = $this->line($before, 0.0, 'before');
            }
        }
        if ($taxed > 0.005) {
            if ($taxedRows) {
                foreach ($taxedRows as $row) {
                    $base[] = $this->rowLine($row, $rate, 'taxed');
                }
            } else {
                $base[] = $this->line($taxed, $rate, 'taxed');
            }
        }

        // Kept commission (refunds without the fee): classified by the sale date
        // of the refunded ticket's order.
        $kept = [];
        if (!empty($keptRows)) {
            $slices = $sinceUtc
                ? [
                    'before' => $payout->getKeptCommissionRowsForPayout(null, $sinceUtc),
                    'taxed' => $payout->getKeptCommissionRowsForPayout($sinceUtc, null),
                ]
                : ['taxed' => $keptRows];
            foreach ($slices as $slice => $rows) {
                foreach ($rows as $row) {
                    if (round((float) ($row['commission_amount'] ?? 0), 2) <= 0) {
                        continue;
                    }
                    $kept[] = ['row' => $row] + $this->rowLine($row, $slice === 'taxed' ? $rate : 0.0, $slice);
                }
            }
        }

        return $this->result($base, $kept);
    }

    /**
     * Invoice items for a computed split. Lines are NET with their own rate.
     */
    public function items(array $computed, string $itemName, string $itemDescription): array
    {
        // The "before the VAT registration" note only means something next to taxed lines.
        $mixed = $computed['vat_amount'] > 0;
        $items = [];
        foreach ($computed['base'] as $line) {
            $description = $itemDescription;
            if (($line['ticket_type_name'] ?? '') !== '') {
                $description .= ' - bilete "' . $line['ticket_type_name'] . '"';
            }
            $items[] = $this->item($itemName, $description, $line, $mixed);
        }
        foreach ($computed['kept'] as $line) {
            $items[] = $this->item(
                'Comision reținut din rambursare',
                'Comision reținut din rambursare bilet(e) "' . ($line['row']['ticket_type_name'] ?? 'Bilet') . '"',
                $line,
                $mixed
            );
        }

        return $items;
    }

    /**
     * Full content (items + totals) of the general-client invoice of a decont,
     * from the decont's commission (excluding POS) and kept commission.
     *
     * @return array{items: array, subtotal: float, vat_rate: float, vat_amount: float, amount: float}
     */
    public function buildForPayout(MarketplacePayout $payout, MarketplaceClient $marketplace, string $itemName, string $itemDescription): array
    {
        $keptRows = [];
        if ($payout->commission_mode === 'added_on_top' || $payout->event?->getEffectiveCommissionMode() === 'added_on_top') {
            $keptRows = $payout->getKeptCommissionRowsForPayout();
        }

        $computed = $this->compute($payout, $marketplace, $payout->getCommissionExclPos(), $keptRows);

        return [
            'items' => $this->items($computed, $itemName, $itemDescription),
            'subtotal' => $computed['subtotal'],
            'vat_rate' => $computed['vat_rate'],
            'vat_amount' => $computed['vat_amount'],
            'amount' => round($computed['subtotal'] + $computed['vat_amount'], 2),
        ];
    }

    protected function item(string $name, string $description, array $line, bool $mixed): array
    {
        if ($mixed && $line['slice'] === 'before') {
            $description .= self::BEFORE_NOTE;
        }

        return [
            'name' => $name,
            'description' => $description,
            'quantity' => $line['qty'],
            'unit_price' => $line['unit_price'],
            'amount' => $line['net'],
            'vat_rate' => $line['rate'],
            'vat_amount' => $line['vat'],
        ];
    }

    /** One line from a per-ticket-type row: qty x commission per ticket when that is exact. */
    protected function rowLine(array $row, float $rate, string $slice): array
    {
        $net = round((float) ($row['commission_amount'] ?? 0), 2);
        $line = $this->line($net, $rate, $slice);
        $qty = (int) ($row['qty'] ?? 0);
        if ($qty > 0) {
            $unit = round($net / $qty, 2);
            if (abs(round($unit * $qty, 2) - $net) < 0.005) {
                $line['qty'] = $qty;
                $line['unit_price'] = $unit;
            }
        }
        $line['ticket_type_name'] = (string) ($row['ticket_type_name'] ?? '');

        return $line;
    }

    protected function line(float $net, float $rate, string $slice): array
    {
        return [
            'net' => $net,
            'vat' => $rate > 0 ? round($net * $rate / 100, 2) : 0.0,
            'rate' => $rate,
            'slice' => $slice,
            'qty' => 1,
            'unit_price' => $net,
            'ticket_type_name' => '',
        ];
    }

    protected function result(array $base, array $kept): array
    {
        $lines = array_merge($base, $kept);
        $subtotal = round(array_sum(array_column($lines, 'net')), 2);
        $vat = round(array_sum(array_column($lines, 'vat')), 2);
        $rates = array_filter(array_column($lines, 'rate'));

        return [
            'base' => $base,
            'kept' => $kept,
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'vat_rate' => $vat > 0 ? (float) max($rates ?: [0]) : 0.0,
        ];
    }
}
