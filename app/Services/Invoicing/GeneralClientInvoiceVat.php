<?php

namespace App\Services\Invoicing;

use App\Models\MarketplaceClient;
use App\Models\MarketplacePayout;
use App\Models\Order;
use App\Services\Marketplace\SalesBreakdownService;
use Carbon\Carbon;

/**
 * Marketplace VAT on the "Client Divers" invoice (commission paid by the
 * customers on top of the ticket price). The commission of a decont falls in
 * up to three slices, by sale date:
 *
 *   before      sold before the marketplace's VAT start date: no VAT;
 *   uncollected sold on/after the start date but before checkout began adding
 *               the VAT (the customer paid the commission without it): the
 *               VAT is owed anyway, so it is extracted from what was paid
 *               (net = paid / (1 + rate));
 *   collected   sold once checkout added the VAT: the commission is net and
 *               the customer paid commission + VAT.
 *
 * Every line returned carries its NET amount and its own vat_rate.
 */
class GeneralClientInvoiceVat
{
    /** @var array<int, ?Carbon> */
    protected static array $collectionStart = [];

    /**
     * @param  float  $baseCommission  commission of the decont excluding POS and kept commission
     * @param  array  $keptRows  rows of MarketplacePayout::getKeptCommissionRowsForPayout()
     * @return array{base: array<int, array{net: float, vat: float, rate: float, slice: string}>, kept: array<int, array{row: array, net: float, vat: float, rate: float, slice: string}>, subtotal: float, vat_amount: float, vat_rate: float}
     */
    public function compute(MarketplacePayout $payout, MarketplaceClient $marketplace, float $baseCommission, array $keptRows): array
    {
        $rate = $marketplace->vat_payer ? (float) data_get($marketplace->settings, 'tax.vat_rate', 21) : 0.0;

        if ($rate <= 0) {
            return $this->result(
                [['net' => round($baseCommission, 2), 'vat' => 0.0, 'rate' => 0.0, 'slice' => 'before']],
                array_map(fn ($r) => ['row' => $r, 'net' => round((float) ($r['commission_amount'] ?? 0), 2), 'vat' => 0.0, 'rate' => 0.0, 'slice' => 'before'], $keptRows)
            );
        }

        $since = $marketplace->vatPayerSince();
        $sinceUtc = $since ? Carbon::instance($since->toDateTime())->utc() : null;
        $collectFrom = $this->collectionStart($marketplace);
        if ($collectFrom && $sinceUtc && $collectFrom->lt($sinceUtc)) {
            $collectFrom = $sinceUtc->copy();
        }

        $periodStart = $payout->period_start ? $payout->period_start->copy()->startOfDay() : null;
        $periodEnd = $payout->period_end ? $payout->period_end->copy()->endOfDay() : null;

        // On-top commission sold in [from, to) inside this decont's period.
        $onTopBetween = function (?Carbon $from, ?Carbon $to) use ($payout, $periodStart, $periodEnd): float {
            if (!$payout->event) {
                return 0.0;
            }
            $start = $from && (!$periodStart || $from->gt($periodStart)) ? $from : $periodStart;
            $end = $to ? $to->copy()->subSecond() : null;
            if ($periodEnd && (!$end || $end->gt($periodEnd))) {
                $end = $periodEnd;
            }
            if ($start && $end && $end->lt($start)) {
                return 0.0;
            }
            $bd = app(SalesBreakdownService::class)
                ->build($payout->event, $start, $end, excludePos: true, dateColumn: 'paid_at', exactBounds: true);
            $rows = $payout->filterRowsToIssuer(array_values($bd['per_type'] ?? []));
            $sum = 0.0;
            foreach ($rows as $row) {
                if (in_array($row['commission_mode'] ?? '', ['on_top', 'added_on_top'], true)) {
                    $sum += (float) ($row['commission_amount'] ?? 0);
                }
            }

            return round($sum, 2);
        };

        $uncollected = $onTopBetween($sinceUtc, $collectFrom);
        $collected = $collectFrom ? $onTopBetween($collectFrom, null) : 0.0;
        // What the decont snapshot holds beyond the taxable slices is pre-VAT.
        $collected = min($collected, round($baseCommission, 2));
        $uncollected = min($uncollected, round($baseCommission - $collected, 2));
        $before = round($baseCommission - $collected - $uncollected, 2);

        $base = [];
        if ($before > 0.005) {
            $base[] = ['net' => $before, 'vat' => 0.0, 'rate' => 0.0, 'slice' => 'before'];
        }
        if ($uncollected > 0.005) {
            $base[] = $this->extracted($uncollected, $rate);
        }
        if ($collected > 0.005) {
            $base[] = ['net' => $collected, 'vat' => round($collected * $rate / 100, 2), 'rate' => $rate, 'slice' => 'collected'];
        }

        // Kept commission (refunds without the fee): classified by the sale date
        // of the refunded ticket's order.
        $kept = [];
        if (!empty($keptRows)) {
            $slices = [
                'before' => $sinceUtc ? $payout->getKeptCommissionRowsForPayout(null, $sinceUtc) : [],
                'uncollected' => $payout->getKeptCommissionRowsForPayout($sinceUtc, $collectFrom),
                'collected' => $collectFrom ? $payout->getKeptCommissionRowsForPayout($collectFrom, null) : [],
            ];
            foreach ($slices as $slice => $rows) {
                foreach ($rows as $row) {
                    $amount = round((float) ($row['commission_amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }
                    $line = match ($slice) {
                        'before' => ['net' => $amount, 'vat' => 0.0, 'rate' => 0.0, 'slice' => 'before'],
                        'uncollected' => $this->extracted($amount, $rate),
                        'collected' => ['net' => $amount, 'vat' => round($amount * $rate / 100, 2), 'rate' => $rate, 'slice' => 'collected'],
                    };
                    $kept[] = ['row' => $row] + $line;
                }
            }
        }

        return $this->result($base, $kept);
    }

    /** VAT taken out of an amount the customer paid without it. */
    protected function extracted(float $gross, float $rate): array
    {
        $net = round($gross * 100 / (100 + $rate), 2);

        return ['net' => $net, 'vat' => round($gross - $net, 2), 'rate' => $rate, 'slice' => 'uncollected'];
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

    /** When checkout started adding the VAT: the first order carrying the snapshot. */
    protected function collectionStart(MarketplaceClient $marketplace): ?Carbon
    {
        if (!array_key_exists($marketplace->id, static::$collectionStart)) {
            $first = Order::where('marketplace_client_id', $marketplace->id)
                ->whereNotNull('meta->vat_rate')
                ->min('created_at');
            static::$collectionStart[$marketplace->id] = $first ? Carbon::parse($first, 'UTC') : null;
        }

        return static::$collectionStart[$marketplace->id]?->copy();
    }
}
