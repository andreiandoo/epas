<x-filament::page>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <x-filament::stats::card
            heading="Events (last 12 months)"
            :value="$record->eventsLastYearCount()"
            icon="heroicon-o-calendar"
        />
        <x-filament::stats::card
            heading="Tickets sold (last 12 months)"
            :value="number_format(($record->ticketsSoldLastYear()['sold'] ?? 0))"
            icon="heroicon-o-ticket"
        />
        <x-filament::stats::card
            heading="Avg tickets / event"
            :value="number_format(($record->ticketsSoldLastYear()['avg_per_event'] ?? 0), 1)"
            icon="heroicon-o-chart-bar"
        />
        <x-filament::stats::card
            heading="Avg price"
            :value="optional(($record->ticketsSoldLastYear()['avg_price'] ?? null), fn($v) => number_format($v, 2) . ' ' . ($record->currency ?? '')) ?? '—'"
            icon="heroicon-o-currency-dollar"
        />
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
