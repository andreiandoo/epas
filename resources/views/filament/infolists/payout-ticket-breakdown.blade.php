@php
    $record = $getRecord();
    $breakdown = $record->ticket_breakdown ?? [];
    $currency = $record->currency ?? 'RON';

    // Defalcare pe nivel de preț is now DERIVED FROM ticket_breakdown JSON
    // directly — the same source as "Detalii bilete" above. Previously it
    // re-queried the DB by period bounds, which (a) didn't reflect what the
    // operator actually selected for the payout, and (b) showed identical
    // data on consecutive payouts whose period_start/end overlapped.
    //
    // Each row from the breakdown becomes one split row. The promo column is
    // left empty here — the saved breakdown aggregates by ticket type, so
    // promo-tier granularity is lost. If we ever need promo splits, the
    // write path needs to emit one row per (type × price × promo) tuple.
    $splitInvoiced = $record->invoicedCommission() > 0.005;
    $splitPosSet = ($splitInvoiced && !empty($breakdown)) ? array_flip($record->getPosTicketTypeIds()) : [];
    $splitRows = collect($breakdown)->map(function ($item) use ($splitInvoiced, $splitPosSet) {
        $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
        $qty = (int) ($item['qty'] ?? $item['quantity'] ?? $item['tickets'] ?? 0);
        $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
        $itemMode = $item['commission_mode'] ?? null;
        $isOnTop = in_array($itemMode, ['added_on_top', 'on_top'], true);
        $commission = $commPerTicket * $qty;
        // Prefer the snapshot's stored gross when present — matches
        // MarketplacePayout::getBreakdownTotals() so the two views agree.
        // The stored value is SUM(tickets.price) from the DB, which can drift
        // from a naive price × qty when `price` is a weighted average
        // (e.g. payout 3296 ADULT: stored 9102 vs 28.27 × 322 = 9102.94).
        $gross = isset($item['gross'])
            ? (float) $item['gross']
            : ($price * $qty + ($isOnTop ? $commission : 0));
        $net = isset($item['net']) ? (float) $item['net'] : ($gross - $commission);
        $invoiced = $splitInvoiced && !$isOnTop && !isset($splitPosSet[$item['ticket_type_id'] ?? 0]);
        if ($invoiced) {
            $net += $commission;
        }

        return [
            'ticket_type_id' => $item['ticket_type_id'] ?? null,
            'ticket_type_name' => $item['ticket_type_name'] ?? '',
            'display_name' => $item['ticket_type_name'] ?? '',
            'is_reduced' => false,
            'price' => $price,
            'qty' => $qty,
            'gross' => round($gross, 2),
            'commission_per_ticket' => $commPerTicket,
            'commission_amount' => round($commission, 2),
            'commission_mode' => $itemMode,
            'commission_type' => $item['commission_type'] ?? null,
            'commission_rate' => $item['commission_rate'] ?? null,
            'commission_fixed' => $item['commission_fixed'] ?? null,
            'net' => round($net, 2),
            'invoiced' => $invoiced,
            'promo_code' => null,
            'promo_label' => '',
        ];
    })->sortBy('ticket_type_name')->values()->all();
    // Fallback for legacy payouts whose snapshot pre-dates per-row discount.
    // New payouts (built by SalesBreakdownService) carry `discount` and
    // `extras` directly on each row, so this map only matters as a fallback.
    $hasPerRowDiscount = !empty($breakdown) && array_key_exists('discount', $breakdown[0] ?? []);
    $discountsByType = (!empty($breakdown) && !$hasPerRowDiscount) ? $record->getDiscountsPerTicketType() : [];
    $posTypeIds = !empty($breakdown) ? $record->getPosTicketTypeIds() : [];
    $posTypeIdsSet = array_flip($posTypeIds);
    $totalQty = 0;
    $totalGross = 0;
    $totalCommission = 0;
    $totalNetTickets = 0;
    $totalDiscounts = 0;
    $totalExtras = 0;
    $totalNetFinal = 0;
    // Pre-pass to know whether to render the Extras column.
    $hasExtras = collect($breakdown)->contains(fn ($i) => (float) ($i['extras'] ?? 0) > 0);
    // Comision inclus facturat separat: nu se scade din ce primește organizatorul.
    $commissionInvoiced = $record->invoicedCommission() > 0.005;
@endphp

@once
<style>
    /* Zero out padding + gap on the Filament section that wraps the ticket breakdown.
       Section is tagged via ->extraAttributes(['class' => 'ep-breakdown-section']) in
       PayoutResource.php. The actual padded element carries fi-section-content. */
    .ep-breakdown-section .fi-section-content,
    .ep-breakdown-section .fi-section-content-ctn {
        padding: 0 !important;
        gap: 0 !important;
    }
</style>
@endonce

@if(!empty($breakdown))
@php
    // Rândurile se calculează întâi, ca să știm ce coloane au conținut
    // (Reduceri / Extras apar doar când există).
    $rows = [];
    foreach ($breakdown as $item) {
        $ticketTypeId = $item['ticket_type_id'] ?? null;
        $isPos = $ticketTypeId && isset($posTypeIdsSet[$ticketTypeId]);
        $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
        $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
        $commPerTicket = (float) ($item['commission_per_ticket'] ?? $item['commission'] ?? 0);
        $commission = (float) ($item['commission_amount'] ?? ($commPerTicket * $qty));
        $commissionMode = $item['commission_mode'] ?? $item['commission_label'] ?? '';
        $isOnTop = in_array($commissionMode, ['added_on_top', 'on_top'], true);
        // Brutul stocat pe rând (aceeași sursă ca getBreakdownTotals); price × qty doar pe snapshot-uri vechi.
        $gross = isset($item['gross'])
            ? (float) $item['gross']
            : ($price * $qty + ($isOnTop ? $commission : 0));
        $netTickets = $gross - $commission;
        $discounts = $hasPerRowDiscount
            ? (float) ($item['discount'] ?? 0)
            : (float) ($discountsByType[$ticketTypeId] ?? 0);
        $extras = (float) ($item['extras'] ?? 0);
        $netFinal = (float) ($item['net'] ?? ($netTickets - $discounts - $extras));
        $rowInvoiced = $commissionInvoiced && !$isPos && !$isOnTop;
        if ($rowInvoiced) {
            $netTickets += $commission;
            $netFinal += $commission;
        }

        $commissionType = $item['commission_type'] ?? null;
        $commissionRateRaw = $item['commission_rate'] ?? null;
        $commissionFixedRaw = $item['commission_fixed'] ?? null;
        $rateLabel = match (true) {
            $commissionType === 'percentage' && $commissionRateRaw !== null => $commissionRateRaw . '%',
            $commissionType === 'fixed' && $commissionFixedRaw !== null => number_format((float) $commissionFixedRaw, 2) . ' ' . $currency,
            $commissionType === 'both' && ($commissionRateRaw !== null || $commissionFixedRaw !== null) => trim(
                ($commissionRateRaw !== null ? $commissionRateRaw . '%' : '')
                . ($commissionRateRaw !== null && $commissionFixedRaw !== null ? ' + ' : '')
                . ($commissionFixedRaw !== null ? number_format((float) $commissionFixedRaw, 2) . ' ' . $currency : '')
            ),
            $commissionRateRaw !== null => $commissionRateRaw . '%',
            default => '',
        };
        $modeLabel = match ($commissionMode) {
            'added_on_top', 'on_top' => 'comision peste preț' . ($rateLabel ? " {$rateLabel}" : ''),
            'included' => 'comision inclus' . ($rateLabel ? " {$rateLabel}" : ''),
            default => (string) $commissionMode,
        };

        $rows[] = compact('isPos', 'price', 'qty', 'commission', 'gross', 'netTickets', 'discounts', 'extras', 'netFinal', 'rowInvoiced', 'modeLabel')
            + ['name' => $item['ticket_type_name'] ?? $item['name'] ?? 'Tip bilet'];

        // Biletele POS se văd, dar nu intră în totaluri.
        if (!$isPos) {
            $totalQty += $qty;
            $totalGross += $gross;
            $totalCommission += $commission;
            $totalNetTickets += $netTickets;
            $totalDiscounts += $discounts;
            $totalExtras += $extras;
            $totalNetFinal += $netFinal;
        }
    }
    $hasDiscounts = collect($rows)->contains(fn ($r) => $r['discounts'] > 0.005);
    $hasExtras = collect($rows)->contains(fn ($r) => $r['extras'] > 0.005);
    $showNetTickets = $hasDiscounts || $hasExtras;
    $money = fn ($v) => number_format((float) $v, 2, ',', '.');
    $th = 'padding:10px 12px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;white-space:nowrap;';
    $td = 'padding:10px 12px;font-size:13px;font-variant-numeric:tabular-nums;white-space:nowrap;text-align:right;';
@endphp
<div class="overflow-x-auto">
    <table class="w-full" style="border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid rgba(127,127,127,.2);">
                <th style="{{ $th }}text-align:left;">Tip bilet</th>
                <th style="{{ $th }}text-align:right;">Bilete</th>
                <th style="{{ $th }}text-align:right;">Brut</th>
                <th style="{{ $th }}text-align:right;">Comision</th>
                @if($showNetTickets)<th style="{{ $th }}text-align:right;">Net bilete</th>@endif
                @if($hasDiscounts)<th style="{{ $th }}text-align:right;">Reduceri</th>@endif
                @if($hasExtras)<th style="{{ $th }}text-align:right;" title="Asigurare bilete + suprataxă card cultural alocate proporțional">Extras</th>@endif
                <th style="{{ $th }}text-align:right;">Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                <tr style="border-bottom:1px solid rgba(127,127,127,.1);{{ $r['isPos'] ? 'background:rgba(127,127,127,.06);' : '' }}">
                    <td style="padding:10px 12px;font-size:13px;">
                        <div style="font-weight:500;">
                            {{ $r['name'] }}
                            @if($r['isPos'])
                                <span title="Vândut prin aplicație/POS — nu intră în calculul decontului" style="display:inline-block;margin-left:6px;padding:0 6px;border-radius:9999px;background:rgba(127,127,127,.15);font-size:10px;font-weight:600;color:#6b7280;">POS</span>
                            @endif
                        </div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:1px;">
                            {{ $r['modeLabel'] }}@if($r['isPos']) · nu intră în decont @endif
                        </div>
                    </td>
                    <td style="{{ $td }}">{{ $r['qty'] }} <span style="color:#9ca3af;">× {{ $money($r['price']) }}</span></td>
                    <td style="{{ $td }}">{{ $money($r['gross']) }}</td>
                    <td style="{{ $td }}{{ $r['rowInvoiced'] || $r['isPos'] ? 'color:#9ca3af;' : 'color:#dc2626;' }}">
                        {{ $r['rowInvoiced'] ? '' : '−' }}{{ $money($r['commission']) }}
                        @if($r['rowInvoiced'])<div style="font-size:10px;">facturat separat</div>@endif
                    </td>
                    @if($showNetTickets)<td style="{{ $td }}">{{ $money($r['netTickets']) }}</td>@endif
                    @if($hasDiscounts)<td style="{{ $td }}color:#dc2626;">{{ $r['discounts'] > 0.005 ? '−' . $money($r['discounts']) : '—' }}</td>@endif
                    @if($hasExtras)<td style="{{ $td }}color:#dc2626;">{{ $r['extras'] > 0.005 ? '−' . $money($r['extras']) : '—' }}</td>@endif
                    <td style="{{ $td }}font-weight:600;">{{ $money($r['netFinal']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background:rgba(127,127,127,.06);">
                <td style="padding:10px 12px;font-size:13px;font-weight:600;">
                    Total
                    @if(!empty($posTypeIds))<span style="font-size:11px;font-weight:400;color:#9ca3af;"> fără POS</span>@endif
                </td>
                <td style="{{ $td }}font-weight:600;">{{ $totalQty }}</td>
                <td style="{{ $td }}font-weight:600;">{{ $money($totalGross) }}</td>
                <td style="{{ $td }}font-weight:600;{{ $commissionInvoiced ? 'color:#9ca3af;' : 'color:#dc2626;' }}">{{ $commissionInvoiced ? '' : '−' }}{{ $money($totalCommission) }}</td>
                @if($showNetTickets)<td style="{{ $td }}font-weight:600;">{{ $money($totalNetTickets) }}</td>@endif
                @if($hasDiscounts)<td style="{{ $td }}font-weight:600;color:#dc2626;">−{{ $money($totalDiscounts) }}</td>@endif
                @if($hasExtras)<td style="{{ $td }}font-weight:600;color:#dc2626;">−{{ $money($totalExtras) }}</td>@endif
                <td style="{{ $td }}font-weight:700;color:#059669;">{{ $money($totalNetFinal) }} {{ $currency }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400" style="padding:16px;">Nu sunt detalii bilete disponibile.</p>
@endif

@if(!empty($splitRows))
<details style="border-top:1px solid rgba(127,127,127,.2);">
    <summary style="cursor:pointer;padding:10px 12px;font-size:12px;color:#6b7280;">
        Defalcare pe nivel de preț <span style="color:#9ca3af;">· fiecare tip de bilet pe preț unitar și cod de reducere</span>
    </summary>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tip bilet</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Preț</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Total brut</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Comision</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Net bilete</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tip reducere</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Mod comision</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @php
                $splitTotalQty = 0;
                $splitTotalGross = 0;
                $splitTotalCommission = 0;
                $splitTotalNet = 0;
            @endphp
            @foreach($splitRows as $sr)
                @php
                    $sCommType = $sr['commission_type'] ?? null;
                    $sCommRate = $sr['commission_rate'] ?? null;
                    $sCommFixed = $sr['commission_fixed'] ?? null;
                    $sRateLabel = match (true) {
                        $sCommType === 'percentage' && $sCommRate !== null
                            => $sCommRate . '%',
                        $sCommType === 'fixed' && $sCommFixed !== null
                            => number_format((float) $sCommFixed, 2) . ' ' . $currency,
                        $sCommType === 'both' && ($sCommRate !== null || $sCommFixed !== null)
                            => trim(
                                ($sCommRate !== null ? $sCommRate . '%' : '')
                                . ($sCommRate !== null && $sCommFixed !== null ? ' + ' : '')
                                . ($sCommFixed !== null ? number_format((float) $sCommFixed, 2) . ' ' . $currency : '')
                            ),
                        $sCommRate !== null => $sCommRate . '%',
                        default => '',
                    };
                    $sCommissionLabel = match($sr['commission_mode'] ?? '') {
                        'added_on_top', 'on_top' => 'Peste preț' . ($sRateLabel ? " ({$sRateLabel})" : ''),
                        'included' => 'Inclus' . ($sRateLabel ? " ({$sRateLabel})" : ''),
                        default => $sr['commission_mode'] ?? '',
                    };
                    $splitTotalQty += $sr['qty'];
                    $splitTotalGross += $sr['gross'];
                    $splitTotalCommission += $sr['commission_amount'];
                    $splitTotalNet += $sr['net'];
                @endphp
                <tr class="{{ $sr['is_reduced'] ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : '' }}">
                    <td class="py-2 px-3 font-medium text-gray-900 dark:text-white">
                        {{ $sr['display_name'] }}
                    </td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 font-mono">{{ number_format($sr['price'], 2) }}</td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 font-semibold">{{ $sr['qty'] }}</td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 font-mono">{{ number_format($sr['gross'], 2) }}</td>
                    <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">{{ $sr['invoiced'] ? '' : '-' }}{{ number_format($sr['commission_amount'], 2) }}</td>
                    <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono font-semibold">{{ number_format($sr['net'], 2) }}</td>
                    <td class="py-2 px-3 text-left text-xs {{ $sr['is_reduced'] ? 'text-emerald-700 dark:text-emerald-300 font-medium' : 'text-gray-400 dark:text-gray-500' }}">{{ $sr['promo_label'] }}</td>
                    <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400 text-xs">{{ $sCommissionLabel }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                <td class="py-2 px-3 text-gray-900 dark:text-white">Total</td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white">{{ $splitTotalQty }}</td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($splitTotalGross, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">{{ $commissionInvoiced ? '' : '-' }}{{ number_format($splitTotalCommission, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($splitTotalNet, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3"></td>
            </tr>
        </tfoot>
    </table>
    </div>
</details>
@endif

@php
    // Refunds accounted for IN THIS payout — i.e. linked via
    // marketplace_refund_requests.marketplace_payout_id = $record->id.
    // The page used to show every refund for the event, which made the
    // section misleading on payouts that hadn't claimed any refunds and
    // duplicated refund visibility across multiple payouts of the same
    // event. Each refund now appears on AT MOST one payout — the one
    // it's linked to.
    $eventId = $record->event_id ?? null;
    $refundRows = [];
    $refundTotals = ['qty' => 0, 'face' => 0.0, 'commission' => 0.0, 'net' => 0.0];
    if ($eventId) {
        $refundItems = \App\Models\MarketplaceRefundItem::query()
            ->whereHas('refundRequest', function ($q) use ($record) {
                $q->whereIn('status', ['refunded', 'partially_refunded'])
                    ->where('marketplace_payout_id', $record->id);
            })
            ->where('status', 'refunded')
            ->with('ticketType:id,name')
            ->get();

        $grouped = $refundItems->groupBy('ticket_type_id');
        foreach ($grouped as $ttId => $items) {
            $first = $items->first();
            $name = $first?->ticketType?->name ?? 'Tip bilet';
            $qty = $items->count();
            $face = (float) $items->sum('face_value');
            $commission = (float) $items->sum('commission_amount');
            $refunded = (float) $items->sum('refund_amount');
            $commissionReturned = $items->where('commission_refunded', true)->count();

            // Net offset on organizer balance: face that was paid out is now
            // pulled back. Commission may or may not have been returned —
            // when commission_refunded=true we offset that too.
            $netOffset = -$face + $items->where('commission_refunded', true)->sum('commission_amount');

            $refundRows[] = [
                'ticket_type_name' => $name,
                'qty' => $qty,
                'face' => $face,
                'commission' => $commission,
                'commission_returned_qty' => $commissionReturned,
                'refunded' => $refunded,
                'net_offset' => round($netOffset, 2),
            ];

            $refundTotals['qty']        += $qty;
            $refundTotals['face']       += $face;
            $refundTotals['commission'] += $commission;
            $refundTotals['net']        += $netOffset;
        }
    }
@endphp

@if(!empty($refundRows))
<div class="overflow-x-auto mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
    <div class="px-3 mb-2 flex items-center gap-2">
        <x-heroicon-o-arrow-uturn-left class="w-4 h-4 text-amber-500" />
        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Bilete rambursate</h4>
        <span class="text-xs text-gray-500">{{ $refundTotals['qty'] }} {{ $refundTotals['qty'] === 1 ? 'bilet' : 'bilete' }}</span>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tip bilet</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Valoare nominală</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Comision</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Comision returnat</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Sumă rambursată</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase" title="Impact pe net-ul organizatorului">Impact net</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($refundRows as $r)
                <tr>
                    <td class="py-2 px-3 text-gray-900 dark:text-white">{{ $r['ticket_type_name'] }}</td>
                    <td class="py-2 px-3 text-right text-amber-500 font-semibold">-{{ $r['qty'] }}</td>
                    <td class="py-2 px-3 text-right font-mono text-amber-500">-{{ number_format($r['face'], 2) }} {{ $currency }}</td>
                    <td class="py-2 px-3 text-right font-mono text-gray-600 dark:text-gray-300">{{ number_format($r['commission'], 2) }} {{ $currency }}</td>
                    <td class="py-2 px-3 text-right text-xs text-gray-500">
                        @if($r['commission_returned_qty'] === $r['qty'])
                            <span class="text-emerald-600 dark:text-emerald-400">da ({{ $r['commission_returned_qty'] }}/{{ $r['qty'] }})</span>
                        @elseif($r['commission_returned_qty'] === 0)
                            <span class="text-gray-400">nu</span>
                        @else
                            parțial ({{ $r['commission_returned_qty'] }}/{{ $r['qty'] }})
                        @endif
                    </td>
                    <td class="py-2 px-3 text-right font-mono text-amber-500">-{{ number_format($r['refunded'], 2) }} {{ $currency }}</td>
                    <td class="py-2 px-3 text-right font-mono font-semibold text-amber-600 dark:text-amber-400">{{ number_format($r['net_offset'], 2) }} {{ $currency }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                <td class="py-2 px-3 text-gray-900 dark:text-white">Total rambursări</td>
                <td class="py-2 px-3 text-right text-amber-500">-{{ $refundTotals['qty'] }}</td>
                <td class="py-2 px-3 text-right font-mono text-amber-500">-{{ number_format($refundTotals['face'], 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format($refundTotals['commission'], 2) }} {{ $currency }}</td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3 text-right font-mono text-amber-600 dark:text-amber-400">{{ number_format($refundTotals['net'], 2) }} {{ $currency }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endif
