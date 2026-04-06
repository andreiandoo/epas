@php
    $breakdown = $getRecord()->ticket_breakdown ?? [];
    $currency = $getRecord()->currency ?? 'RON';
    $totalQty = 0;
    $totalGross = 0;
    $totalCommission = 0;
@endphp

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
                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Mod comision</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($breakdown as $item)
                @php
                    $name = $item['ticket_type_name'] ?? $item['name'] ?? 'Tip bilet';
                    $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                    $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
                    $commPerTicket = (float) ($item['commission_per_ticket'] ?? $item['commission'] ?? 0);
                    $commission = (float) ($item['commission_amount'] ?? ($commPerTicket * $qty));
                    $gross = (float) ($item['gross'] ?? $item['total'] ?? ($price * $qty + ($item['commission_mode'] === 'added_on_top' ? $commission : 0)));
                    $commissionMode = $item['commission_mode'] ?? $item['commission_label'] ?? '';
                    $commissionRate = isset($item['commission_rate']) ? $item['commission_rate'] . '%' : '';
                    $commissionLabel = match($commissionMode) {
                        'added_on_top' => 'Peste preț' . ($commissionRate ? " ({$commissionRate})" : ''),
                        'included' => 'Inclus' . ($commissionRate ? " ({$commissionRate})" : ''),
                        default => $commissionMode,
                    };
                    $totalQty += $qty;
                    $totalGross += $gross;
                    $totalCommission += $commission;
                @endphp
                <tr>
                    <td class="py-2 px-3 font-medium text-gray-900 dark:text-white">{{ $name }}</td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 font-mono">{{ number_format($price, 2) }}</td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 font-semibold">{{ $qty }}</td>
                    <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300 font-mono">{{ number_format($gross, 2) }}</td>
                    <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">-{{ number_format($commission, 2) }}</td>
                    <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400 text-xs">{{ $commissionLabel }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                <td class="py-2 px-3 text-gray-900 dark:text-white">Total</td>
                <td class="py-2 px-3"></td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white">{{ $totalQty }}</td>
                <td class="py-2 px-3 text-right text-gray-900 dark:text-white font-mono">{{ number_format($totalGross, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3 text-right text-red-500 dark:text-red-400 font-mono">-{{ number_format($totalCommission, 2) }} {{ $currency }}</td>
                <td class="py-2 px-3"></td>
            </tr>
        </tfoot>
    </table>
</div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">Nu sunt detalii bilete disponibile.</p>
@endif
