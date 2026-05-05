<x-filament-panels::page>
    @php
        $statusOptions = [
            'paid'                => 'Plătit',
            'confirmed'           => 'Confirmat',
            'completed'           => 'Finalizat',
            'failed'              => 'Eșuat',
            'expired'             => 'Expirat',
            'cancelled'           => 'Anulat',
            'refunded'            => 'Rambursat',
            'partially_refunded'  => 'Rambursat parțial',
            'pending'             => 'În așteptare',
        ];
        $periodOptions = [
            'today'      => 'Azi',
            '7d'         => '7 zile',
            '30d'        => '30 zile',
            'this_month' => 'Luna curentă',
            'last_month' => 'Luna trecută',
            'this_year'  => 'Anul curent',
            'custom'     => 'Personalizat',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 p-5 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Filtre raport</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Alege perioada, evenimentele și statusurile, apoi apasă "Generează raport".</p>
            </div>

            {{-- Period --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Perioadă</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($periodOptions as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="period" value="{{ $key }}" class="peer sr-only">
                            <span class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-white/10 bg-white dark:bg-gray-800 peer-checked:bg-primary-500 peer-checked:text-white peer-checked:ring-primary-500 transition">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @if($period === 'custom')
                    <div class="grid grid-cols-2 gap-3 mt-3 max-w-md">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">De la</label>
                            <input type="date" wire:model.live="customFrom" class="w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Până la</label>
                            <input type="date" wire:model.live="customTo" class="w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800 text-sm">
                        </div>
                    </div>
                @endif
            </div>

            {{-- Date column --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Bază dată</label>
                <div class="flex gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="dateColumn" value="paid_at" class="text-primary-600"><span>După data plății</span></label>
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="dateColumn" value="created_at" class="text-primary-600"><span>După data creării</span></label>
                </div>
            </div>

            {{-- Filter mode --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Selectează după</label>
                <div class="flex gap-4 text-sm mb-3">
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="filterBy" value="event" class="text-primary-600"><span>Eveniment(e)</span></label>
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="filterBy" value="organizer" class="text-primary-600"><span>Organizator</span></label>
                </div>

                @if($filterBy === 'event')
                    <select wire:model.live="eventIds" multiple size="6" class="w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800 text-sm">
                        @foreach($this->eventOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Ține <kbd>Ctrl</kbd>/<kbd>Cmd</kbd> apăsat pentru selecție multiplă. Maxim 500 evenimente afișate.</p>
                @else
                    <select wire:model.live="organizerId" class="w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-gray-800 text-sm">
                        <option value="">— Alege organizator —</option>
                        @foreach($this->organizerOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Toate evenimentele acestui organizator vor fi incluse.</p>
                @endif
            </div>

            {{-- Statuses --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Status comenzi</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($statusOptions as $value => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" wire:model.live="statuses" value="{{ $value }}" class="peer sr-only">
                            <span class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-white/10 bg-white dark:bg-gray-800 peer-checked:bg-primary-500 peer-checked:text-white peer-checked:ring-primary-500 transition">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- View mode --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Mod afișare</label>
                <div class="flex gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="viewMode" value="compact" class="text-primary-600"><span>Compact (per tip bilet)</span></label>
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="viewMode" value="extended" class="text-primary-600"><span>Extins (per comandă)</span></label>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-white/10">
                @php
                    $canGenerate = !empty($statuses) && (
                        ($filterBy === 'event' && !empty($eventIds))
                        || ($filterBy === 'organizer' && $organizerId)
                    );
                @endphp
                <button
                    wire:click="generate"
                    @disabled(!$canGenerate)
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <x-heroicon-o-play class="w-4 h-4" />
                    Generează raport
                </button>
                <button
                    wire:click="exportCsv"
                    @disabled(!$canGenerate || (!$compactData && empty($extendedRows)))
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    Export CSV
                </button>
                @if(!$canGenerate)
                    <span class="text-xs text-gray-500">Selectează cel puțin un eveniment/organizator și un status.</span>
                @endif
            </div>
        </div>

        {{-- Summary --}}
        @if($summary)
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
                @php
                    $cards = [
                        ['Comenzi', $summary['orders'] ?? 0, ''],
                        ['Bilete', $summary['qty'] ?? 0, ''],
                        ['Brut', number_format($summary['gross'] ?? 0, 2), 'RON'],
                        ['Comision', number_format($summary['commission'] ?? 0, 2), 'RON'],
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

        {{-- Compact table --}}
        @if($viewMode === 'compact' && $compactData)
            <div class="rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Eveniment</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tip bilet</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Preț</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Brut</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Comision</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Discount</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Extras</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Net</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Mod comision</th>
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
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">#</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Data</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Eveniment</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Client</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Bilete</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Brut</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Comision</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Refund</th>
                                <th class="text-right py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Net</th>
                                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($extendedRows as $r)
                                <tr>
                                    <td class="py-2 px-3">
                                        <a href="{{ url('/marketplace/orders/' . $r['order_id']) }}" target="_blank" class="text-primary-600 hover:underline font-mono text-xs">{{ $r['order_number'] }}</a>
                                    </td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-300 text-xs">{{ optional($r['paid_at'])->format('d.m.Y H:i') ?? optional($r['created_at'])->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="py-2 px-3 text-gray-900 dark:text-white">{{ $r['event_title'] }}</td>
                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300">
                                        <div class="font-medium">{{ $r['customer_name'] ?: '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $r['customer_email'] }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right font-semibold text-gray-900 dark:text-white">{{ $r['tickets'] }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-gray-900 dark:text-white">{{ number_format($r['gross'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-red-500">-{{ number_format($r['commission'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono {{ $r['refund'] > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $r['refund'] > 0 ? '-' . number_format($r['refund'], 2) : '0.00' }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ number_format($r['net'], 2) }}</td>
                                    <td class="py-2 px-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1
                                            @if(in_array($r['status'], ['paid','confirmed','completed'])) bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20
                                            @elseif(in_array($r['status'], ['failed','cancelled','expired'])) bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20
                                            @elseif(in_array($r['status'], ['refunded','partially_refunded'])) bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20
                                            @else bg-gray-50 text-gray-700 ring-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20 @endif
                                        ">{{ $r['status'] }}</span>
                                    </td>
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
