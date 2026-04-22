@php
    $record = $getRecord();
    $breakdown = $record->ticket_breakdown ?? [];
    $currency = $record->currency ?? 'RON';
    $discountsByType = !empty($breakdown) ? $record->getDiscountsPerTicketType() : [];
    $posTypeIds = !empty($breakdown) ? $record->getPosTicketTypeIds() : [];
    $posTypeIdsSet = array_flip($posTypeIds);
    $totalQty = 0;
    $totalGross = 0;
    $totalCommission = 0;
    $totalNetTickets = 0;
    $totalDiscounts = 0;
    $totalNetFinal = 0;
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
                    $gross = (float) ($item['gross'] ?? $item['total'] ?? ($price * $qty + ($item['commission_mode'] === 'added_on_top' ? $commission : 0)));
                    // Net bilete = ce primeste organizatorul din vanzarea biletelor, inainte de discount.
                    // included: gross = price*qty (comision in pret) -> net = gross - commission
                    // added_on_top: gross = price*qty + commission -> net = gross - commission = price*qty
                    $netTickets = $gross - $commission;
                    $discounts = (float) ($discountsByType[$ticketTypeId] ?? 0);
                    $netFinal = $netTickets - $discounts;
                    $commissionMode = $item['commission_mode'] ?? $item['commission_label'] ?? '';
                    $commissionRate = isset($item['commission_rate']) ? $item['commission_rate'] . '%' : '';
                    $commissionLabel = match($commissionMode) {
                        'added_on_top' => 'Peste preț' . ($commissionRate ? " ({$commissionRate})" : ''),
                        'included' => 'Inclus' . ($commissionRate ? " ({$commissionRate})" : ''),
                        default => $commissionMode,
                    };
                    // POS rows are shown for transparency but excluded from totals
                    if (!$isPos) {
                        $totalQty += $qty;
                        $totalGross += $gross;
                        $totalCommission += $commission;
                        $totalNetTickets += $netTickets;
                        $totalDiscounts += $discounts;
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
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($totalNetFinal, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3"></td>
            </tr>
        </tfoot>
    </table>
</div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">Nu sunt detalii bilete disponibile.</p>
@endif
