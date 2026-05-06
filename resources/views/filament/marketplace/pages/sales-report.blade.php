<x-filament-panels::page>
    {{-- Collapsible filters wrapper. Generate sets $filtersOpen=false so
         the result is in the viewport without manual scrolling; the
         "Modifică filtrele" button or the section header re-opens the
         form to let the user tweak. --}}
    <div x-data x-show="$wire.filtersOpen" x-transition>
        {{ $this->form }}
    </div>

    <button x-data
            x-show="!$wire.filtersOpen"
            x-on:click="$wire.toggleFilters()"
            class="inline-flex items-center gap-2 px-3 py-2 mb-2 rounded-lg ring-1 ring-gray-200 dark:ring-white/10 bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <x-heroicon-o-funnel class="w-4 h-4" />
        Modifică filtrele
    </button>

    <div class="mt-6 space-y-6">
        @if($summary)
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @php
                    $cards = [
                        ['Comenzi', $summary['orders'] ?? 0, ''],
                        ['Bilete', $summary['qty'] ?? 0, ''],
                        ['Brut', number_format($summary['gross'] ?? 0, 2), 'RON'],
                        ['Comision', number_format($summary['commission'] ?? 0, 2), 'RON'],
                        ['Discount', number_format($summary['discount'] ?? 0, 2), 'RON'],
                        ['Net', number_format($summary['net'] ?? 0, 2), 'RON'],
                    ];
                @endphp
                @foreach($cards as [$label, $value, $unit])
                    <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white font-mono">{{ $value }} <span class="text-xs font-normal text-gray-500">{{ $unit }}</span></div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Deconturi existente pentru evenimentele selectate --}}
        @if(!empty($relatedPayouts))
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10 flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-primary-500" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Deconturi existente pentru aceste evenimente</h3>
                    <span class="ml-auto text-xs text-gray-500">{{ count($relatedPayouts) }} {{ count($relatedPayouts) === 1 ? 'decont' : 'deconturi' }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">#</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Eveniment</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Perioadă</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Brut</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Comision</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($relatedPayouts as $p)
                                <tr>
                                    <td class="py-2 px-3">
                                        <a href="{{ $p['url'] }}" target="_blank" class="text-primary-600 hover:underline font-mono text-xs">{{ $p['reference'] ?? '#' . $p['id'] }}</a>
                                    </td>
                                    <td class="py-2 px-3 text-gray-900 dark:text-white">{{ $p['event_title'] }}</td>
                                    <td class="py-2 px-3">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1
                                            @if($p['status'] === 'completed') bg-green-600 text-white ring-green-700
                                            @elseif($p['status'] === 'approved') bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20
                                            @elseif($p['status'] === 'processing') bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20
                                            @elseif(in_array($p['status'], ['rejected','cancelled'])) bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20
                                            @else bg-gray-50 text-gray-700 ring-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20 @endif
                                        ">{{ $p['status'] }}</span>
                                    </td>
                                    <td class="py-2 px-3 text-xs text-gray-500">
                                        {{ optional($p['period_start'])->format('d.m.Y') ?? '—' }}
                                        →
                                        {{ optional($p['period_end'])->format('d.m.Y') ?? '—' }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format($p['gross_amount'], 2) }} {{ $p['currency'] }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500">-{{ number_format($p['commission'], 2) }} {{ $p['currency'] }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ number_format($p['amount'], 2) }} {{ $p['currency'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @php $viewMode = $data['viewMode'] ?? 'compact'; @endphp

        {{-- Compact table --}}
        @if($viewMode === 'compact' && $compactData)
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Eveniment</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Tip bilet</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Qty</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Preț</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Brut</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Comision</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Discount</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Extras</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Net</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Mod</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($compactData['rows'] as $r)
                                <tr class="{{ $r['is_pos'] ? 'bg-gray-50 dark:bg-gray-800/30' : '' }}">
                                    <td class="py-2 px-3 text-gray-900 dark:text-white">{{ $r['event_title'] }}</td>
                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300">
                                        @if($r['is_pos'])
                                            <span title="POS — exclus din totaluri" class="inline-flex items-center gap-1">
                                                <x-heroicon-o-device-phone-mobile class="w-3.5 h-3.5 text-gray-500" />
                                                {{ $r['ticket_type_name'] }}
                                            </span>
                                        @else
                                            {{ $r['ticket_type_name'] }}
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right font-semibold {{ $r['is_pos'] ? 'text-gray-500' : 'text-gray-900 dark:text-white' }}">{{ $r['qty'] }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-600 dark:text-gray-300">{{ number_format($r['price'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-900 dark:text-white">{{ number_format($r['gross'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500 dark:text-red-400">-{{ number_format($r['commission'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500 dark:text-red-400">{{ $r['discount'] > 0 ? '-' . number_format($r['discount'], 2) : '0.00' }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500 dark:text-red-400">{{ $r['extras'] > 0 ? '-' . number_format($r['extras'], 2) : '0.00' }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ number_format($r['net'], 2) }}</td>
                                    <td class="py-2 px-3 text-right text-xs text-gray-500">
                                        @php
                                            $mode = $r['commission_mode'] ?? '';
                                            $label = match($mode) { 'added_on_top','on_top' => 'Peste preț', 'included' => 'Inclus', default => $mode };
                                            $type = $r['commission_type'] ?? null;
                                            $rate = $r['commission_rate'] ?? null;
                                            $fixed = $r['commission_fixed'] ?? null;
                                            $tail = match(true) {
                                                $type === 'percentage' && $rate !== null => $rate.'%',
                                                $type === 'fixed' && $fixed !== null => number_format((float)$fixed, 2).' RON',
                                                $type === 'both' => trim(($rate !== null ? $rate.'%' : '').($rate !== null && $fixed !== null ? ' + ' : '').($fixed !== null ? number_format((float)$fixed, 2).' RON' : '')),
                                                $rate !== null => $rate.'%',
                                                default => '',
                                            };
                                        @endphp
                                        {{ $tail ? "{$label} ({$tail})" : $label }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="py-6 text-center text-sm text-gray-500">Nicio vânzare în perioada selectată.</td></tr>
                            @endforelse
                        </tbody>
                        @if(!empty($compactData['rows']))
                            <tfoot>
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold bg-gray-50 dark:bg-gray-800/50">
                                    <td class="py-2 px-3 text-gray-900 dark:text-white" colspan="2">Total (excl. POS)</td>
                                    <td class="py-2 px-3 text-right text-gray-900 dark:text-white">{{ $compactData['totals']['qty'] }}</td>
                                    <td class="py-2 px-3"></td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-900 dark:text-white">{{ number_format($compactData['totals']['gross'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500">-{{ number_format($compactData['totals']['commission'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500">{{ $compactData['totals']['discount'] > 0 ? '-' . number_format($compactData['totals']['discount'], 2) : '0.00' }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500">{{ $compactData['totals']['extras'] > 0 ? '-' . number_format($compactData['totals']['extras'], 2) : '0.00' }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-900 dark:text-white">{{ number_format($compactData['totals']['net'], 2) }}</td>
                                    <td class="py-2 px-3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endif

        {{-- Extended table --}}
        @if($viewMode === 'extended' && !empty($extendedRows))
            @php $totalPages = (int) ceil(($extendedTotal ?? 0) / max(1, $extendedPerPage)); @endphp
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">#</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Data</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Eveniment</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Client</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Tip bilet</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Bilete</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Brut</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Comision</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Mod</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Discount</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Refund</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($extendedRows as $r)
                                <tr>
                                    <td class="py-2 px-3">
                                        <a href="{{ url('/marketplace/orders/' . $r['order_id']) }}" target="_blank" class="text-primary-600 hover:underline font-mono text-xs">{{ $r['order_number'] }}</a>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] ring-1
                                                @if($r['status'] === 'confirmed') bg-green-600 text-white ring-green-700
                                                @elseif(in_array($r['status'], ['paid','completed'])) bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20
                                                @elseif(in_array($r['status'], ['failed','cancelled','expired'])) bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20
                                                @elseif(in_array($r['status'], ['refunded','partially_refunded'])) bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20
                                                @else bg-gray-50 text-gray-700 ring-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20 @endif
                                            ">{{ $r['status'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-300 text-xs">{{ optional($r['paid_at'])->format('d.m.Y H:i') ?? optional($r['created_at'])->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="py-2 px-3 text-gray-900 dark:text-white">{{ $r['event_title'] }}</td>
                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300">
                                        <div class="font-medium">{{ $r['customer_name'] ?: '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $r['customer_email'] }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-xs">{{ $r['ticket_types'] ?: '—' }}</td>
                                    <td class="py-2 px-3 text-right font-semibold text-gray-900 dark:text-white">{{ $r['tickets'] }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-900 dark:text-white">{{ number_format($r['gross'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500">-{{ number_format($r['commission'], 2) }}</td>
                                    <td class="py-2 px-3 text-xs text-gray-500">{{ $r['commission_mode'] ?? '' }}</td>
                                    <td class="py-2 px-3 text-right font-mono {{ ($r['discount'] ?? 0) > 0 ? 'text-red-500' : 'text-gray-400' }}">
                                        {{ ($r['discount'] ?? 0) > 0 ? '-' . number_format($r['discount'], 2) : '0.00' }}
                                        @if(!empty($r['promo_code']))
                                            <div class="text-[10px] text-gray-500 mt-0.5">{{ $r['promo_code'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono {{ $r['refund'] > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $r['refund'] > 0 ? '-' . number_format($r['refund'], 2) : '0.00' }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ number_format($r['net'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($totalPages > 1)
                    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-white/10">
                        <div class="text-xs text-gray-500">
                            Pagina <span class="font-medium text-gray-900 dark:text-white">{{ $extendedPage }}</span> din <span class="font-medium text-gray-900 dark:text-white">{{ $totalPages }}</span>
                            · Total comenzi: <span class="font-medium text-gray-900 dark:text-white">{{ $extendedTotal }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="changeExtendedPage({{ max(1, $extendedPage - 1) }})" @disabled($extendedPage <= 1) class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-sm disabled:opacity-50">←</button>
                            <button wire:click="changeExtendedPage({{ min($totalPages, $extendedPage + 1) }})" @disabled($extendedPage >= $totalPages) class="px-3 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-sm disabled:opacity-50">→</button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if($viewMode === 'extended' && empty($extendedRows) && $summary !== null)
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 p-6 text-center text-sm text-gray-500">
                Nicio comandă găsită cu filtrele alese.
            </div>
        @endif
    </div>
</x-filament-panels::page>
