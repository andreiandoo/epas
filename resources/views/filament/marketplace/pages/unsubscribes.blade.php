<x-filament-panels::page>
    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="p-5 bg-white border rounded-xl border-gray-200 dark:bg-gray-900 dark:border-white/10">
            <p class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Total dezabonări</p>
            <p class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">{{ number_format($total, 0, ',', '.') }}</p>
        </div>
        <div class="p-5 bg-white border rounded-xl border-gray-200 dark:bg-gray-900 dark:border-white/10">
            <p class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Cu motiv completat</p>
            <p class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">
                {{ number_format($withReason, 0, ',', '.') }}
                <span class="text-base font-medium text-gray-400">/ {{ number_format($total, 0, ',', '.') }}</span>
            </p>
        </div>
    </div>

    {{-- Breakdown by reason --}}
    <div class="p-5 bg-white border rounded-xl border-gray-200 dark:bg-gray-900 dark:border-white/10">
        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">De ce se dezabonează</h2>
        <div class="mt-4 space-y-3">
            @forelse ($breakdown as $row)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-700 dark:text-gray-300">{{ $row['label'] }}</span>
                        <span class="font-medium text-gray-950 dark:text-white">
                            {{ number_format($row['count'], 0, ',', '.') }}
                            <span class="text-gray-400">· {{ $row['percent'] }}%</span>
                        </span>
                    </div>
                    <div class="w-full h-2 mt-1 overflow-hidden bg-gray-100 rounded-full dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ max(1, $row['percent']) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nicio dezabonare încă.</p>
            @endforelse
        </div>
    </div>

    {{-- Recent unsubscribes --}}
    <div class="bg-white border rounded-xl border-gray-200 dark:bg-gray-900 dark:border-white/10">
        <h2 class="px-5 py-4 text-sm font-semibold border-b text-gray-950 dark:text-white border-gray-200 dark:border-white/10">
            Ultimele dezabonări
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-5 py-3 font-medium text-left">Email</th>
                        <th class="px-5 py-3 font-medium text-left">Motiv</th>
                        <th class="px-5 py-3 font-medium text-left">Detalii</th>
                        <th class="px-5 py-3 font-medium text-left whitespace-nowrap">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($recent as $r)
                        <tr>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $r['email'] }}</td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $r['reason'] }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $r['detail'] ?: '—' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $r['date'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">Nicio dezabonare încă.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
