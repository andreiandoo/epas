<x-filament::page>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Capacity</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($record->capacity_total ?? 0) }}</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Seated Capacity</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($record->capacity_seated ?? 0) }}</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Standing Capacity</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($record->capacity_standing ?? 0) }}</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Events</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $record->events()->count() }}</div>
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-6 mt-8 md:grid-cols-2">
        <x-filament::section heading="Venue Information">
            <dl class="grid grid-cols-1 gap-4 text-sm">
                @if($record->address)
                    <div><dt class="text-gray-500">Address</dt><dd class="font-medium">{{ $record->address }}</dd></div>
                @endif
                @if($record->city)
                    <div><dt class="text-gray-500">City</dt><dd class="font-medium">{{ $record->city }}</dd></div>
                @endif
                @if($record->state)
                    <div><dt class="text-gray-500">State</dt><dd class="font-medium">{{ $record->state }}</dd></div>
                @endif
                @if($record->country)
                    <div><dt class="text-gray-500">Country</dt><dd class="font-medium">{{ $record->country }}</dd></div>
                @endif
                @if($record->phone)
                    <div><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $record->phone }}</dd></div>
                @endif
                @if($record->email)
                    <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $record->email }}</dd></div>
                @endif
            </dl>
        </x-filament::section>

        <x-filament::section heading="Recent Events">
            @php
                $recentEvents = $record->events()
                    ->orderBy('event_date', 'desc')
                    ->limit(5)
                    ->get();
            @endphp
            @if($recentEvents->count() > 0)
                <ul class="space-y-2 text-sm">
                    @foreach($recentEvents as $event)
                        <li class="flex justify-between items-center">
                            <span class="font-medium">{{ $event->getTranslation('title', app()->getLocale()) ?? $event->title }}</span>
                            <span class="text-gray-500">{{ $event->event_date?->format('M d, Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-600">No events yet for this venue.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament::page>
