<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Month navigation --}}
        <div class="flex items-center justify-between">
            <x-filament::button wire:click="previousMonth" color="gray" icon="heroicon-o-chevron-left" size="sm">
                Luna anterioară
            </x-filament::button>
            <h2 class="text-lg font-bold">{{ $monthLabel }}</h2>
            <x-filament::button wire:click="nextMonth" color="gray" icon="heroicon-o-chevron-right" icon-position="after" size="sm">
                Luna următoare
            </x-filament::button>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-100 border border-green-300"></span> Disponibil</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-300"></span> Limitat (&lt;30%)</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-100 border border-red-300"></span> Sold out</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-gray-200 border border-gray-300"></span> Închis</span>
        </div>

        {{-- Capacity grid --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300 sticky left-0 bg-gray-50 dark:bg-gray-800 z-10 min-w-[120px]">Tip bilet</th>
                        @foreach($days as $day)
                            <th class="px-2 py-2 text-center font-medium text-gray-500 dark:text-gray-400 min-w-[70px] {{ $day['is_past'] ? 'opacity-50' : '' }}">
                                <div class="text-xs text-gray-400">{{ $day['weekday'] }}</div>
                                <div>{{ $day['label'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($ticketTypes as $tt)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200 sticky left-0 bg-white dark:bg-gray-900 z-10">
                                {{ $tt->name }}
                                <div class="text-xs text-gray-400">{{ number_format($tt->price, 2) }} {{ $tt->currency ?? 'RON' }} | Cap: {{ $tt->daily_capacity }}</div>
                            </td>
                            @foreach($days as $day)
                                @php
                                    $cell = $day['ticket_types'][$tt->id] ?? null;
                                    $capacity = $cell['capacity'] ?? $tt->daily_capacity;
                                    $sold = $cell['sold'] ?? 0;
                                    $reserved = $cell['reserved'] ?? 0;
                                    $available = max(0, $capacity - $sold - $reserved);
                                    $isClosed = $cell['is_closed'] ?? false;
                                    $priceOverride = $cell['price_override'] ?? null;

                                    $bgClass = 'bg-white dark:bg-gray-900';
                                    if (!$day['is_open'] || $isClosed) {
                                        $bgClass = 'bg-gray-100 dark:bg-gray-800';
                                    } elseif ($available <= 0) {
                                        $bgClass = 'bg-red-50 dark:bg-red-900/20';
                                    } elseif ($capacity > 0 && ($available / $capacity) < 0.3) {
                                        $bgClass = 'bg-amber-50 dark:bg-amber-900/20';
                                    } else {
                                        $bgClass = 'bg-green-50 dark:bg-green-900/20';
                                    }
                                @endphp
                                <td class="px-1 py-1 text-center {{ $bgClass }} {{ $day['is_past'] ? 'opacity-40' : '' }}">
                                    @if(!$day['is_open'] || $isClosed)
                                        <div class="text-xs text-gray-400">
                                            @if($isClosed)
                                                <button wire:click="toggleClosed('{{ $day['date'] }}', {{ $tt->id }})" class="hover:text-blue-500" title="Click pentru a deschide">
                                                    Închis
                                                </button>
                                            @else
                                                —
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-xs font-semibold {{ $available <= 0 ? 'text-red-600' : 'text-gray-700 dark:text-gray-200' }}">
                                            {{ $sold }}/{{ $capacity }}
                                        </div>
                                        @if($priceOverride)
                                            <div class="text-[10px] text-purple-600">{{ number_format($priceOverride, 0) }}₽</div>
                                        @endif
                                        @if(!$day['is_past'])
                                            <div class="flex gap-0.5 justify-center mt-0.5">
                                                <button wire:click="toggleClosed('{{ $day['date'] }}', {{ $tt->id }})" class="text-[10px] text-gray-400 hover:text-red-500" title="Închide">✕</button>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals row --}}
        <div class="text-sm text-gray-500">
            <strong>Total bilete vândute luna aceasta:</strong>
            {{ collect($days)->flatMap(fn($d) => collect($d['ticket_types'])->pluck('sold'))->sum() }}
        </div>
    </div>
</x-filament-panels::page>
