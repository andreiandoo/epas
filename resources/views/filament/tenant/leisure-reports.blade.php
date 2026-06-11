<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end gap-3 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div>
                <label class="text-xs text-gray-500">De la</label>
                <input type="date" wire:model.live="from" class="fi-input rounded-lg block">
            </div>
            <div>
                <label class="text-xs text-gray-500">Până la</label>
                <input type="date" wire:model.live="to" class="fi-input rounded-lg block">
            </div>
            <div class="text-sm text-gray-600 ml-auto">
                Total: <strong>{{ $totalOrders }}</strong> comenzi ·
                <strong>{{ number_format(($totalRevenue ?? 0) / 100, 2) }}</strong> RON
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold mb-3">Venituri pe zi</h3>
                @if ($orders->isEmpty())
                    <p class="text-sm text-gray-500">Nicio comandă în intervalul selectat.</p>
                @else
                    @php $max = (int) $orders->max('total_cents') ?: 1; @endphp
                    <div class="space-y-1">
                        @foreach ($orders as $row)
                            <div class="flex items-center gap-2 text-sm">
                                <div class="w-20 text-gray-500 text-xs">{{ \Carbon\Carbon::parse($row->day)->format('d.m') }}</div>
                                <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded h-5 relative overflow-hidden">
                                    <div class="bg-emerald-500 h-full" style="width: {{ ($row->total_cents / $max) * 100 }}%"></div>
                                </div>
                                <div class="w-24 text-right font-mono text-xs">
                                    {{ number_format($row->total_cents / 100, 2) }}
                                </div>
                                <div class="w-12 text-right text-xs text-gray-500">
                                    {{ $row->orders_count }}c
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold mb-3">Breakdown pe canal</h3>
                @if ($perChannel->isEmpty())
                    <p class="text-sm text-gray-500">Niciun canal cu vânzări în interval.</p>
                @else
                    @php $maxCh = (int) $perChannel->max('total_cents') ?: 1; @endphp
                    <div class="space-y-2">
                        @foreach ($perChannel as $row)
                            <div>
                                <div class="flex items-center justify-between text-xs">
                                    <span>{{ $channels[$row->channel] ?? $row->channel }}</span>
                                    <span class="font-mono">{{ number_format($row->total_cents / 100, 2) }} RON · {{ $row->orders_count }} cmd</span>
                                </div>
                                <div class="bg-gray-100 dark:bg-gray-700 rounded h-3 mt-1 overflow-hidden">
                                    <div class="bg-violet-500 h-full" style="width: {{ ($row->total_cents / $maxCh) * 100 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
