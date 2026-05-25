@php
    $record = $getRecord();
    $breakdown = $record->ticket_breakdown ?? [];
    $currency = $record->currency ?? 'RON';

    // Second table: per-(type × unit price × promo code) split, computed
    // on-the-fly so the snapshot stays an aggregate. Includes the promo
    // label per row and a "(redus)" name suffix when applicable.
    //
    // Period bounds are derived from PAYOUT TIMESTAMPS, not the saved
    // period_start/period_end (which were historically set to the event's
    // lifetime, causing consecutive payouts to show identical splits):
    //   • periodStart = previous active payout's created_at (event creation
    //     when this is the first payout)
    //   • periodEnd   = this payout's created_at
    // exactBounds=true so the "> previous_created_at" cut is strict — orders
    // created at the previous payout's timestamp belong to that payout, not
    // this one.
    $splitRows = [];
    if ($record->event_id) {
        $splitEvent = $record->event ?? \App\Models\Event::find($record->event_id);
        if ($splitEvent) {
            try {
                $previousPayout = \App\Models\MarketplacePayout::query()
                    ->where('event_id', $record->event_id)
                    ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
                    ->where('id', '!=', $record->id)
                    ->where('created_at', '<', $record->created_at)
                    ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
                    ->orderByDesc('created_at')
                    ->first(['id', 'created_at']);

                $splitStart = $previousPayout?->created_at
                    ?? ($splitEvent->created_at ? \Illuminate\Support\Carbon::parse($splitEvent->created_at) : null);
                $splitEnd = \Illuminate\Support\Carbon::parse($record->created_at);

                $splitRows = app(\App\Services\Marketplace\SalesBreakdownService::class)
                    ->buildPayoutSplitTable($splitEvent, $splitStart, $splitEnd, true, 'created_at', true);
            } catch (\Throwable $e) {
                $splitRows = [];
            }
        }
    }
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
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Discounts</th>
                @if($hasExtras)
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase" title="Asigurare bilete + suprataxă card cultural alocate proportional">Extras</th>
                @endif
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Net final</th>
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Mod comision</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($breakdown as $item)
                @php
                    $ticketTypeId = $item['ticket_type_id'] ?? null;
                    $isPos = $ticketTypeId && isset($posTypeIdsSet[$ticketTypeId]);
                    $name = $item['ticket_type_name'] ?? $item['name'] ?? 'Tip bilet';
                    $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                    $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
                    $commPerTicket = (float) ($item['commission_per_ticket'] ?? $item['commission'] ?? 0);
                    $commission = (float) ($item['commission_amount'] ?? ($commPerTicket * $qty));
                    $commissionMode = $item['commission_mode'] ?? $item['commission_label'] ?? '';
                    $isOnTop = in_array($commissionMode, ['added_on_top', 'on_top'], true);
                    // Total brut afiseaza ce a platit clientul. Calculam mereu din
                    // price/qty/commission, ca sa fim consistenti intre snapshot-uri
                    // noi (gross stocat = price*qty) si vechi (gross nestocat).
                    $gross = $price * $qty + ($isOnTop ? $commission : 0);
                    // Net bilete = ce primeste organizatorul din vanzarea biletelor, inainte de discount/extras.
                    // included: gross = price*qty (comision in pret) -> net = gross - commission
                    // added_on_top: gross = price*qty + commission -> net = gross - commission = price*qty
                    $netTickets = $gross - $commission;
                    // New snapshot (SalesBreakdownService) carries `discount` per row.
                    // Legacy snapshot: fall back to record-level allocation by ticket type.
                    $discounts = $hasPerRowDiscount
                        ? (float) ($item['discount'] ?? 0)
                        : (float) ($discountsByType[$ticketTypeId] ?? 0);
                    // Extras (insurance, cultural-card surcharge) are a deduction on the
                    // organizer's net just like the discount; legacy snapshots don't track them.
                    $extras = (float) ($item['extras'] ?? 0);
                    if ($extras > 0) $hasExtras = true;
                    $netFinal = (float) ($item['net'] ?? ($netTickets - $discounts - $extras));
                    // Build the parenthesized rate label from commission_type so
                    // fixed-amount commissions also show their value (was: only %).
                    $commissionType = $item['commission_type'] ?? null;
                    $commissionRateRaw = $item['commission_rate'] ?? null;
                    $commissionFixedRaw = $item['commission_fixed'] ?? null;
                    $rateLabel = match (true) {
                        $commissionType === 'percentage' && $commissionRateRaw !== null
                            => $commissionRateRaw . '%',
                        $commissionType === 'fixed' && $commissionFixedRaw !== null
                            => number_format((float) $commissionFixedRaw, 2) . ' ' . $currency,
                        $commissionType === 'both' && ($commissionRateRaw !== null || $commissionFixedRaw !== null)
                            => trim(
                                ($commissionRateRaw !== null ? $commissionRateRaw . '%' : '')
                                . ($commissionRateRaw !== null && $commissionFixedRaw !== null ? ' + ' : '')
                                . ($commissionFixedRaw !== null ? number_format((float) $commissionFixedRaw, 2) . ' ' . $currency : '')
                            ),
                        // Legacy snapshots without commission_type — fall back to rate-only.
                        $commissionRateRaw !== null => $commissionRateRaw . '%',
                        default => '',
                    };
                    $commissionLabel = match($commissionMode) {
                        'added_on_top', 'on_top' => 'Peste preț' . ($rateLabel ? " ({$rateLabel})" : ''),
                        'included' => 'Inclus' . ($rateLabel ? " ({$rateLabel})" : ''),
                        default => $commissionMode,
                    };
                    // POS rows are shown for transparency but excluded from totals
                    if (!$isPos) {
                        $totalQty += $qty;
                        $totalGross += $gross;
                        $totalCommission += $commission;
                        $totalNetTickets += $netTickets;
                        $totalDiscounts += $discounts;
                        $totalExtras += $extras;
                        $totalNetFinal += $netFinal;
                    }
                @endphp
                <tr class="{{ $isPos ? 'bg-gray-100 dark:bg-gray-800' : '' }}">
                    <td class="py-2 px-3 font-medium text-gray-900 dark:text-white">
                        @if($isPos)
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline-block w-4 h-4 mr-1.5 -mt-0.5 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" title="Vândut prin aplicație/POS — nu intră în calculul decontului"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                        @endif
                        {{ $name }}
                    </td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300' }} font-mono">{{ number_format($price, 2) }}</td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300' }} font-semibold">{{ $qty }}</td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300' }} font-mono">{{ number_format($gross, 2) }}</td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-red-500 dark:text-red-400' }} font-mono">-{{ number_format($commission, 2) }}</td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300' }} font-mono">{{ number_format($netTickets, 2) }}</td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-red-500 dark:text-red-400' }} font-mono">{{ $discounts > 0 ? '-' . number_format($discounts, 2) : '0.00' }}</td>
                    @if($hasExtras)
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-red-500 dark:text-red-400' }} font-mono">{{ $extras > 0 ? '-' . number_format($extras, 2) : '0.00' }}</td>
                    @endif
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-900 dark:text-gray-100' : 'text-gray-900 dark:text-white' }} font-mono font-semibold">{{ number_format($netFinal, 2) }}</td>
                    <td class="py-2 px-3 text-right {{ $isPos ? 'text-gray-700 dark:text-gray-300' : 'text-gray-500 dark:text-gray-400' }} text-xs">{{ $commissionLabel }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                <td class="py-2 px-3 text-gray-900 dark:text-white">
                    Total
                    @if(!empty($posTypeIds))
                        <span class="ml-1 text-[10px] font-normal text-gray-500 dark:text-gray-400">(excl. POS)</span>
                    @endif
                </td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white">{{ $totalQty }}</td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($totalGross, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">-{{ number_format($totalCommission, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($totalNetTickets, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">{{ $totalDiscounts > 0 ? '-' . number_format($totalDiscounts, 2) : '0.00' }} {{ $currency }}</td>
                @if($hasExtras)
                <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">{{ $totalExtras > 0 ? '-' . number_format($totalExtras, 2) : '0.00' }} {{ $currency }}</td>
                @endif
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($totalNetFinal, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3"></td>
            </tr>
        </tfoot>
    </table>
</div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">Nu sunt detalii bilete disponibile.</p>
@endif

@if(!empty($splitRows))
<div class="overflow-x-auto mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
    <div class="px-3 mb-2 flex items-center gap-2">
        <x-heroicon-o-tag class="w-4 h-4 text-emerald-500" />
        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Defalcare pe nivel de preț</h4>
        <span class="text-xs text-gray-500">fiecare tip de bilet împărțit pe preț unitar și cod de reducere</span>
    </div>
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
                    <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">-{{ number_format($sr['commission_amount'], 2) }}</td>
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
                <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">-{{ number_format($splitTotalCommission, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($splitTotalNet, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3"></td>
            </tr>
        </tfoot>
    </table>
</div>
@endif

@php
    // Refunds attached to orders for this event. Pulled live from
    // marketplace_refund_items so the section reflects the current state
    // even if the payout snapshot was generated before a refund happened.
    // Grouped per ticket type with totals; commission-refunded marker
    // tells the operator whether the platform returned the on-top
    // commission too (relevant for accounting).
    $eventId = $record->event_id ?? null;
    $refundRows = [];
    $refundTotals = ['qty' => 0, 'face' => 0.0, 'commission' => 0.0, 'net' => 0.0];
    if ($eventId) {
        $refundItems = \App\Models\MarketplaceRefundItem::query()
            ->whereHas('refundRequest', function ($q) use ($eventId) {
                $q->whereIn('status', ['refunded', 'partially_refunded'])
                    ->whereHas('order', function ($q2) use ($eventId) {
                        $q2->where(fn ($q3) => $q3->where('event_id', $eventId)
                                                  ->orWhere('marketplace_event_id', $eventId));
                    });
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
