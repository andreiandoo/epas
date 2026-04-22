<x-filament-panels::page>
    @if(!$marketplace)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">No marketplace account found.</p>
        </div>
    @else
        @php $d = $data; @endphp

        <!-- Month Navigation -->
        <div class="flex items-center justify-between p-4 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <button wire:click="previousMonth" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <x-heroicon-o-chevron-left class="w-4 h-4" />
                Luna anterioară
            </button>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $d['month_label'] }}</h2>
            @if(!$d['is_current_month'])
            <button wire:click="nextMonth" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Luna următoare
                <x-heroicon-o-chevron-right class="w-4 h-4" />
            </button>
            @else
            <div></div>
            @endif
        </div>

        <!-- Summary Card -->
        <div class="grid grid-cols-1 gap-3 mb-5 lg:grid-cols-4">
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Total de plată</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($d['grand_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Comision ticketing ({{ $d['commission_rate'] }}%)</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Servicii extra</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['services_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
            <div class="p-4 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Încasări totale</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['revenue_total'], 2) }} {{ $d['currency'] }}</p>
            </div>
        </div>

        <!-- Ticketing Breakdown per Event -->
        <div class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="flex items-center gap-2 mb-4">
                <x-heroicon-o-ticket class="w-5 h-5 text-indigo-500" />
                <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">Comision ticketing per eveniment ({{ $d['commission_rate'] }}% din încasări)</h3>
            </div>

            @if(count($d['events']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Eveniment</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Data</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Locație</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Comenzi</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Bilete</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Încasări</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Comision marketplace</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Comision Tixello</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($d['events'] as $event)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-3 py-2.5">
                                @if($event['event_id'])
                                <a href="{{ route('filament.marketplace.resources.events.edit', $event['event_id']) }}" class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $event['event_name'] }}
                                </a>
                                @else
                                <span class="font-medium text-gray-900 dark:text-white">{{ $event['event_name'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $event['event_date'] ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">
                                @if($event['venue'])
                                    {{ $event['venue'] }}@if($event['city']), {{ $event['city'] }}@endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($event['order_count']) }}</td>
                            <td class="px-3 py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($event['ticket_count']) }}</td>
                            <td class="px-3 py-2.5 text-right font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ number_format($event['revenue'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right text-amber-600 dark:text-amber-400 whitespace-nowrap">{{ number_format($event['marketplace_commission'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-rose-600 dark:text-rose-400 whitespace-nowrap">{{ number_format($event['tixello_commission'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                            <td colspan="3" class="px-3 py-2.5 font-semibold text-gray-900 dark:text-white">Total</td>
                            <td class="px-3 py-2.5 text-right font-medium text-gray-700 dark:text-gray-300">{{ number_format(collect($d['events'])->sum('order_count')) }}</td>
                            <td class="px-3 py-2.5 text-right font-medium text-gray-700 dark:text-gray-300">{{ number_format(collect($d['events'])->sum('ticket_count')) }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ number_format($d['revenue_total'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-amber-600 dark:text-amber-400 whitespace-nowrap">{{ number_format($d['marketplace_commission_total'], 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Nicio comandă în această lună.</p>
            @endif
        </div>

        <!-- Service Orders Breakdown -->
        @foreach($d['services_by_type'] as $type => $service)
        <div class="p-5 mb-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    @switch($type)
                        @case('featuring')
                            <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                            @break
                        @case('email')
                            <x-heroicon-o-envelope class="w-5 h-5 text-blue-500" />
                            @break
                        @case('tracking')
                            <x-heroicon-o-chart-bar class="w-5 h-5 text-green-500" />
                            @break
                        @case('campaign')
                            <x-heroicon-o-megaphone class="w-5 h-5 text-purple-500" />
                            @break
                    @endswitch
                    <h3 class="text-sm font-semibold tracking-wide text-gray-900 uppercase dark:text-white">{{ $service['label'] }}</h3>
                </div>
                <span class="text-sm font-bold {{ $service['total'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ number_format($service['total'], 2) }} {{ $d['currency'] }}
                </span>
            </div>

            @if(count($service['orders']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Comandă</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Eveniment</th>
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Organizator</th>
                            @if($type !== 'tracking')
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Pachet</th>
                            @else
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Plan</th>
                            @endif
                            <th class="px-3 py-2 text-xs font-medium text-left text-gray-500 dark:text-gray-400">Data</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-gray-500 dark:text-gray-400">Valoare totală</th>
                            <th class="px-3 py-2 text-xs font-medium text-right text-rose-600 dark:text-rose-400">Cotă Tixello (50%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($service['orders'] as $order)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $order['order_number'] ?? '#' . $order['id'] }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $order['event_name'] }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $order['organizer_name'] }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $order['config_label'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $order['created_at'] }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 whitespace-nowrap dark:text-gray-300">{{ number_format($order['gross_total'] ?? $order['total'] * 2, 2) }} {{ $d['currency'] }}</td>
                            <td class="px-3 py-2 font-medium text-right text-rose-600 whitespace-nowrap dark:text-rose-400">{{ number_format($order['total'], 2) }} {{ $d['currency'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Nicio comandă pentru acest serviciu în luna selectată.</p>
            @endif
        </div>
        @endforeach

        <!-- Grand Total Footer -->
        <div class="p-5 bg-white border-2 shadow-sm dark:bg-gray-800 rounded-xl border-rose-200 dark:border-rose-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Total de plată către Tixello</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $d['month_label'] }}</p>
                </div>
                <p class="text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($d['grand_total'], 2) }} <span class="text-lg">{{ $d['currency'] }}</span></p>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Comision ticketing</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($d['ticketing_total'], 2) }} {{ $d['currency'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase dark:text-gray-400">Servicii extra</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($d['services_total'], 2) }} {{ $d['currency'] }}</p>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
