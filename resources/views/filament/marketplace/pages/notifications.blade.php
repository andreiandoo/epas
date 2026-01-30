<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Cards --}}
        @php
            $marketplaceClientId = $this->getMarketplaceClientId();
            $totalCount = \App\Models\MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)->count();
            $unreadCount = \App\Models\MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)->unread()->count();
            $todayCount = \App\Models\MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)->whereDate('created_at', today())->count();
        @endphp

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-primary-100 dark:bg-primary-900/30">
                        <x-heroicon-o-bell class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total notificari</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalCount) }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-warning-100 dark:bg-warning-900/30">
                        <x-heroicon-o-bell-alert class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Necitite</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($unreadCount) }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-success-100 dark:bg-success-900/30">
                        <x-heroicon-o-calendar class="w-6 h-6 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Astazi</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($todayCount) }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Notifications Table --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
