<x-filament-panels::page>
    @php
        $ticket = $this->record;
        $event = $ticket->ticketType?->event;
        $eventTitle = is_array($event?->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : ($event?->title ?? '');
        $venue = $event?->venue;
        $venueName = $venue ? ($venue->getTranslation('name', app()->getLocale()) ?? 'N/A') : 'N/A';
    @endphp

    {{-- Ticket Information Section --}}
    <x-filament::section>
        <x-slot name="heading">
            Ticket Information
        </x-slot>

        {{-- QR Code --}}
        <div class="flex justify-center py-4 mb-6">
            <div class="text-center">
                <div class="inline-block p-4 bg-white rounded-lg shadow-md">
                    <div class="w-48 h-48 bg-gray-200 flex items-center justify-center rounded">
                        <div class="text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">QR Code</p>
                            <p class="text-xs text-gray-500">{{ $ticket->code }}</p>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-600">
                    Scan this code at the event entrance
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Ticket Code</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $ticket->code }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</p>
                <p class="text-sm">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                        @if($ticket->status === 'valid') bg-green-100 text-green-800
                        @elseif($ticket->status === 'used') bg-gray-100 text-gray-800
                        @elseif($ticket->status === 'void') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ ucfirst($ticket->status) }}
                    </span>
                </p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Ticket Type</p>
                <p class="text-sm text-gray-900 dark:text-white">{{ $ticket->ticketType?->name ?? 'N/A' }}</p>
            </div>
            @if($ticket->seat_label)
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Seat</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $ticket->seat_label }}</p>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Event Details Section --}}
    @if($event)
        <x-filament::section>
            <x-slot name="heading">
                Event Details
            </x-slot>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Event</p>
                    <p class="text-sm text-gray-900 dark:text-white">
                        <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event]) }}" class="text-primary-600 hover:underline">
                            {{ $eventTitle }}
                        </a>
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Event Date</p>
                    <p class="text-sm text-gray-900 dark:text-white">
                        {{ $event->event_date ? $event->event_date->format('l, F j, Y') : 'N/A' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Start Time</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $event->start_time ?? 'N/A' }}</p>
                </div>
                @if($event->door_time)
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Door Time</p>
                        <p class="text-sm text-gray-900 dark:text-white">{{ $event->door_time }}</p>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- Order Details Section --}}
    @if($ticket->order)
        <x-filament::section>
            <x-slot name="heading">
                Order Details
            </x-slot>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Order ID</p>
                    <p class="text-sm text-gray-900 dark:text-white">
                        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $ticket->order]) }}" class="text-primary-600 hover:underline">
                            #{{ $ticket->order->id }}
                        </a>
                    </p>
                </div>
                @if($ticket->order->tenant)
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tenant</p>
                        <p class="text-sm text-gray-900 dark:text-white">
                            <a href="{{ \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $ticket->order->tenant]) }}" class="text-primary-600 hover:underline">
                                {{ $ticket->order->tenant->name }}
                            </a>
                        </p>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- Venue Section --}}
    @if($venue)
        <x-filament::section>
            <x-slot name="heading">
                Venue
            </x-slot>

            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Venue Name</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $venueName }}</p>
                </div>

                @if($venue->address)
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Address</p>
                        <p class="text-sm text-gray-900 dark:text-white">{{ $venue->address }}</p>
                        @if($venue->city || $venue->country)
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $venue->city }}{{ $venue->city && $venue->country ? ', ' : '' }}{{ $venue->country }}
                            </p>
                        @endif
                    </div>
                @endif

                @if($venue->lat && $venue->lng)
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Location Map</p>
                        <div class="aspect-video bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-600">Venue Location</p>
                                <p class="text-xs text-gray-500">{{ $venue->lat }}, {{ $venue->lng }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
