<x-filament-panels::page>
    <div class="max-w-2xl mx-auto space-y-6">
        <form wire:submit.prevent="submitScan" class="space-y-3">
            <label class="block text-sm font-medium">Scanează QR sau introdu codul biletului</label>
            <x-leisure.qr-scanner-input
                wire-model="scanInput"
                placeholder="Cod bilet sau scanare QR..."
            />
            <p class="text-xs text-gray-500">Apasă Enter pentru a verifica. Cititoarele HID Bluetooth trimit Enter automat.</p>
        </form>

        @if ($lastError)
            <div class="p-4 rounded-xl border-2 border-red-500 bg-red-50 dark:bg-red-900/30">
                <div class="flex items-start gap-3">
                    <div class="text-3xl">❌</div>
                    <div>
                        <div class="font-bold text-red-700 dark:text-red-300">Eroare</div>
                        <div class="text-sm text-red-600 dark:text-red-200 mt-1">{{ $lastError }}</div>
                    </div>
                </div>
            </div>
        @endif

        @if ($lastScan)
            @if ($lastScan['already_used'])
                <div class="p-5 rounded-xl border-2 border-amber-500 bg-amber-50 dark:bg-amber-900/30">
                    <div class="flex items-start gap-3">
                        <div class="text-4xl">⚠️</div>
                        <div class="flex-1">
                            <div class="font-bold text-amber-700 dark:text-amber-300">Bilet folosit deja!</div>
                            <div class="text-sm mt-2">Cod: <span class="font-mono">{{ $lastScan['code'] }}</span></div>
                            <div class="text-sm">Scanat la: {{ $lastScan['scanned_at'] }}</div>
                            <div class="text-sm">Tip: {{ $lastScan['ticket_type'] }}</div>
                            <div class="text-sm">Client: {{ $lastScan['customer'] }}</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-5 rounded-xl border-2 border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30">
                    <div class="flex items-start gap-3">
                        <div class="text-4xl">✅</div>
                        <div class="flex-1">
                            <div class="font-bold text-emerald-700 dark:text-emerald-300">Bilet valid — INTRARE PERMISĂ</div>
                            <div class="text-sm mt-2">Cod: <span class="font-mono">{{ $lastScan['code'] }}</span></div>
                            <div class="text-sm">Tip: <strong>{{ $lastScan['ticket_type'] }}</strong></div>
                            <div class="text-sm">Client: {{ $lastScan['customer'] }}</div>
                            <div class="text-sm">Ora intrare: {{ $lastScan['scanned_at'] }}</div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
