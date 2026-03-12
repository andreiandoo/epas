<x-filament-panels::page>
    <div class="space-y-6">

        @if($this->successMessage)
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4">
                <p class="text-green-800 dark:text-green-200 font-medium">{{ $this->successMessage }}</p>
            </div>
        @endif

        {{-- Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Setări notificări SMS</h3>

            <div class="space-y-4">
                {{-- Transactional --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">SMS Tranzacționale</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Confirmări automate la achiziția de bilet cu link de acces</p>
                    </div>
                    <button wire:click="toggleTransactional" type="button"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $this->transactionalEnabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600' }}"
                            role="switch" aria-checked="{{ $this->transactionalEnabled ? 'true' : 'false' }}">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $this->transactionalEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>

                {{-- Promotional --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">SMS Promoționale</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Campanii promoționale către clienți</p>
                    </div>
                    <button wire:click="togglePromotional" type="button"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $this->promotionalEnabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600' }}"
                            role="switch" aria-checked="{{ $this->promotionalEnabled ? 'true' : 'false' }}">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $this->promotionalEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Credits Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Credite SMS Tranzacționale</p>
                    <x-heroicon-o-chat-bubble-bottom-center-text class="h-5 w-5 text-primary-500" />
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($transactionalCredits) }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    {{ $pricing['transactional']['price'] ?? '0.40' }} EUR / SMS
                    @if ($clientCurrency === 'RON' && $eurToRon)
                        ({{ number_format(($pricing['transactional']['price'] ?? 0.40) * $eurToRon, 4) }} RON)
                    @endif
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Credite SMS Promoționale</p>
                    <x-heroicon-o-megaphone class="h-5 w-5 text-yellow-500" />
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($promotionalCredits) }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    {{ $pricing['promotional']['price'] ?? '0.50' }} EUR / SMS
                    @if ($clientCurrency === 'RON' && $eurToRon)
                        ({{ number_format(($pricing['promotional']['price'] ?? 0.50) * $eurToRon, 4) }} RON)
                    @endif
                </p>
            </div>
        </div>

        {{-- Purchase Credits --}}
        @if($this->transactionalEnabled || $this->promotionalEnabled)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Achiziționează credite SMS</h3>

                <div class="space-y-6">
                    @if($this->transactionalEnabled)
                        <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                             x-data="{ qty: @entangle('transactionalQuantity'), price: {{ $pricing['transactional']['price'] ?? 0.40 }}, eurToRon: {{ $eurToRon ?? 'null' }} }">
                            <p class="font-medium text-gray-900 dark:text-white mb-3">SMS Tranzacționale</p>
                            <div class="flex items-end gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Număr SMS-uri</label>
                                    <input type="number" x-model.number="qty" min="1" step="10"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Preț/SMS</label>
                                    <p class="text-sm font-medium py-2">
                                        <span x-text="price.toFixed(2) + ' EUR'"></span>
                                        <template x-if="eurToRon">
                                            <span class="text-gray-400 text-xs" x-text="'(' + (price * eurToRon).toFixed(4) + ' RON)'"></span>
                                        </template>
                                    </p>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Total</label>
                                    <p class="text-lg font-bold text-primary-600">
                                        <span x-text="(qty * price).toFixed(2) + ' EUR'"></span>
                                        <template x-if="eurToRon">
                                            <span class="text-sm font-normal text-gray-400" x-text="'(' + (qty * price * eurToRon).toFixed(2) + ' RON)'"></span>
                                        </template>
                                    </p>
                                </div>
                                <button wire:click="purchaseCredits('transactional')" wire:loading.attr="disabled"
                                        class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                    <span wire:loading.remove wire:target="purchaseCredits('transactional')">Cumpără credite</span>
                                    <span wire:loading wire:target="purchaseCredits('transactional')">Se procesează...</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($this->promotionalEnabled)
                        <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                             x-data="{ qty: @entangle('promotionalQuantity'), price: {{ $pricing['promotional']['price'] ?? 0.50 }}, eurToRon: {{ $eurToRon ?? 'null' }} }">
                            <p class="font-medium text-gray-900 dark:text-white mb-3">SMS Promoționale</p>
                            <div class="flex items-end gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Număr SMS-uri</label>
                                    <input type="number" x-model.number="qty" min="1" step="10"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Preț/SMS</label>
                                    <p class="text-sm font-medium py-2">
                                        <span x-text="price.toFixed(2) + ' EUR'"></span>
                                        <template x-if="eurToRon">
                                            <span class="text-gray-400 text-xs" x-text="'(' + (price * eurToRon).toFixed(4) + ' RON)'"></span>
                                        </template>
                                    </p>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Total</label>
                                    <p class="text-lg font-bold text-yellow-600">
                                        <span x-text="(qty * price).toFixed(2) + ' EUR'"></span>
                                        <template x-if="eurToRon">
                                            <span class="text-sm font-normal text-gray-400" x-text="'(' + (qty * price * eurToRon).toFixed(2) + ' RON)'"></span>
                                        </template>
                                    </p>
                                </div>
                                <button wire:click="purchaseCredits('promotional')" wire:loading.attr="disabled"
                                        class="px-6 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                    <span wire:loading.remove wire:target="purchaseCredits('promotional')">Cumpără credite</span>
                                    <span wire:loading wire:target="purchaseCredits('promotional')">Se procesează...</span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Recent SMS Logs --}}
        @if(count($recentLogs) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ultimele SMS-uri trimise</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3">Data</th>
                                <th class="px-6 py-3">Telefon</th>
                                <th class="px-6 py-3">Tip</th>
                                <th class="px-6 py-3">Eveniment</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($recentLogs as $log)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-6 py-3 whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap font-mono text-xs">{{ $log->phone }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $log->type === 'transactional' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200' }}">
                                            {{ $log->type === 'transactional' ? 'Tranzacțional' : 'Promoțional' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 max-w-[200px] truncate">{{ $log->event?->getTranslation('title', 'ro') ?? '-' }}</td>
                                    <td class="px-6 py-3">
                                        @php
                                            $statusColors = [
                                                'queued' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                'sent' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200',
                                                'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
                                                'undelivered' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200',
                                                'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                                            ];
                                            $statusLabels = [
                                                'queued' => 'În așteptare',
                                                'sent' => 'Trimis',
                                                'delivered' => 'Livrat',
                                                'undelivered' => 'Nelivrat',
                                                'failed' => 'Eșuat',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$log->status] ?? $statusColors['queued'] }}">
                                            {{ $statusLabels[$log->status] ?? $log->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">{{ number_format($log->cost, 2) }} {{ $log->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
