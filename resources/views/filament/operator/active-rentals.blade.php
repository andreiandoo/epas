<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <h2 class="text-lg font-semibold mb-4">Pornește rental nou</h2>
            <form wire:submit.prevent="startRental" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div>
                    <label class="text-xs text-gray-500">Cod bilet</label>
                    <x-leisure.qr-scanner-input wire-model="startTicketCode" placeholder="Scan QR bilet" />
                </div>
                <div>
                    <label class="text-xs text-gray-500">Cod resursă (QR echipament)</label>
                    <x-leisure.qr-scanner-input wire-model="startResourceCode" placeholder="Scan QR barcă/kayak" />
                </div>
                <button type="submit" class="fi-btn bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                    ▶ Start rental
                </button>
            </form>
        </div>

        <div class="space-y-3">
            <h2 class="text-lg font-semibold">Active acum ({{ $active->count() }})</h2>

            @forelse ($active as $r)
                @php
                    $overtime = $r->current_overtime_minutes;
                    $cssBorder = $overtime > 0 ? 'border-red-500' : 'border-gray-200 dark:border-gray-700';
                @endphp
                <div class="p-4 rounded-xl border-2 {{ $cssBorder }} bg-white dark:bg-gray-800 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">@if($r->physicalResource->resource_type === 'boat')🛶@elseif($r->physicalResource->resource_type === 'kayak')🚣@elseif($r->physicalResource->resource_type === 'bike')🚲@else🎫@endif</span>
                            <span class="font-semibold text-lg">{{ $r->physicalResource->name }}</span>
                            <span class="text-xs text-gray-500 font-mono">{{ $r->physicalResource->qr_code }}</span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                            Început: {{ $r->started_at->format('H:i') }}
                            · Plan. sfârșit: {{ $r->planned_end_at->format('H:i') }}
                            · Bilet: <span class="font-mono">{{ $r->ticket?->code ?? '—' }}</span>
                        </div>
                        @if ($overtime > 0)
                            <div class="text-red-600 dark:text-red-400 text-sm font-semibold mt-1">
                                ⚠ Depășire: {{ $overtime }} min
                            </div>
                        @endif
                    </div>
                    <button
                        wire:click="endRental({{ $r->id }})"
                        wire:confirm="Sigur încheiezi rental-ul pentru {{ $r->physicalResource->name }}?"
                        class="fi-btn bg-rose-600 hover:bg-rose-700 text-white rounded-lg"
                    >
                        ■ Finalizează
                    </button>
                </div>
            @empty
                <div class="text-center py-12 text-gray-500">
                    Niciun rental activ momentan.
                </div>
            @endforelse
        </div>
    </div>

    <script>
        // Auto-refresh page every 30 seconds to keep overtime counters fresh.
        setInterval(() => {
            if (!document.hidden) {
                @this.dispatch('$refresh');
            }
        }, 30000);
    </script>
</x-filament-panels::page>
