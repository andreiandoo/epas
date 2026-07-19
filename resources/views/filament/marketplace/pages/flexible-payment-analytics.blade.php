<x-filament-panels::page>
    @php($d = $this->data)

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">GMV Rate</x-slot>
            <div class="text-2xl font-bold">{{ number_format($d['installments_gmv'] ?? 0, 2, ',', '.') }} RON</div>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">GMV BNPL</x-slot>
            <div class="text-2xl font-bold">{{ number_format($d['bnpl_gmv'] ?? 0, 2, ',', '.') }} RON</div>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Total încasat</x-slot>
            <div class="text-2xl font-bold text-success-600">{{ number_format($d['collected'] ?? 0, 2, ',', '.') }} RON</div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">Sold de încasat (DSO)</x-slot>
            <div class="text-2xl font-bold text-warning-600">{{ number_format($d['outstanding'] ?? 0, 2, ',', '.') }} RON</div>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Rată finalizare</x-slot>
            <div class="text-2xl font-bold">{{ $d['completion_rate'] ?? 0 }}%</div>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Rată default</x-slot>
            <div class="text-2xl font-bold {{ ($d['default_rate'] ?? 0) > 10 ? 'text-danger-600' : '' }}">{{ $d['default_rate'] ?? 0 }}%</div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-filament::section>
            <x-slot name="heading">Debitări viitoare</x-slot>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Următoarele 7 zile</span>
                    <span class="font-semibold">{{ $d['next7_count'] ?? 0 }} rate · {{ number_format($d['next7_sum'] ?? 0, 2, ',', '.') }} RON</span>
                </div>
                <div class="flex justify-between">
                    <span>Următoarele 30 zile</span>
                    <span class="font-semibold">{{ $d['next30_count'] ?? 0 }} rate · {{ number_format($d['next30_sum'] ?? 0, 2, ',', '.') }} RON</span>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Planuri după status</x-slot>
            <div class="space-y-1">
                @forelse(($d['by_status'] ?? []) as $status => $count)
                    <div class="flex justify-between">
                        <span class="capitalize">{{ $status }}</span>
                        <span class="font-semibold">{{ $count }}</span>
                    </div>
                @empty
                    <div class="text-gray-500">Niciun plan încă.</div>
                @endforelse
                <div class="flex justify-between border-t pt-1 mt-1">
                    <span class="font-semibold">Total</span>
                    <span class="font-bold">{{ $d['total'] ?? 0 }}</span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
