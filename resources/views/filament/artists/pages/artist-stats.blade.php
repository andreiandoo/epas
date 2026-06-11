<x-filament::page>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        {{-- Events stat card --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-calendar" class="h-6 w-6 text-gray-400" />
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Events (last 12 months)</h3>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $record->eventsLastYearCount() }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tickets sold stat card --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-ticket" class="h-6 w-6 text-gray-400" />
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Tickets sold (last 12 months)</h3>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format(($record->ticketsSoldLastYear()['sold'] ?? 0)) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Avg tickets per event stat card --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-chart-bar" class="h-6 w-6 text-gray-400" />
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg tickets / event</h3>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format(($record->ticketsSoldLastYear()['avg_per_event'] ?? 0), 1) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Avg price stat card --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-currency-dollar" class="h-6 w-6 text-gray-400" />
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg price</h3>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ optional(($record->ticketsSoldLastYear()['avg_price'] ?? null), fn($v) => number_format($v, 2) . ' ' . ($record->currency ?? '')) ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 mt-8 md:grid-cols-2">
        <x-filament::section heading="Followers">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">Facebook</dt><dd class="font-medium">{{ number_format($record->followers_facebook ?? 0) }}</dd></div>
                <div><dt class="text-gray-500">Instagram</dt><dd class="font-medium">{{ number_format($record->followers_instagram ?? 0) }}</dd></div>
                <div><dt class="text-gray-500">TikTok</dt><dd class="font-medium">{{ number_format($record->followers_tiktok ?? 0) }}</dd></div>
                <div><dt class="text-gray-500">YouTube</dt><dd class="font-medium">{{ number_format($record->followers_youtube ?? 0) }}</dd></div>
                <div><dt class="text-gray-500">Spotify (monthly)</dt><dd class="font-medium">{{ number_format($record->spotify_monthly_listeners ?? 0) }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Recent activity">
            <p class="text-sm text-gray-600">Hook up your events & orders to populate this.</p>
        </x-filament::section>
    </div>
</x-filament::page>
