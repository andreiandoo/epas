<x-filament-panels::page>
    @if(!$this->editionId)
        <div class="text-center py-12">
            <x-heroicon-o-presentation-chart-bar class="w-16 h-16 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Active Edition</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Select or create a festival edition to view the cashless dashboard.</p>
        </div>
    @else
        <div class="space-y-6">
            {{-- Edition Selector --}}
            <div class="flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Edition:</label>
                <select wire:model.live="editionId" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                    @foreach($this->getEditions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            @livewire(\App\Filament\Tenant\Widgets\Cashless\CashlessKpiCards::class, ['editionId' => $this->editionId])

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @livewire(\App\Filament\Tenant\Widgets\Cashless\SalesByCategoryChart::class, ['editionId' => $this->editionId])
                @livewire(\App\Filament\Tenant\Widgets\Cashless\TopVendorsChart::class, ['editionId' => $this->editionId])
            </div>

            @livewire(\App\Filament\Tenant\Widgets\Cashless\HourlySalesChart::class, ['editionId' => $this->editionId])
        </div>
    @endif
</x-filament-panels::page>
