<div>
    @php
        $ticket = $getRecord();
        $event = $ticket->ticketType?->event;
        $venue = $event?->venue;
    @endphp

    @if($venue)
        <div class="space-y-3">
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Venue Name</p>
                <p class="text-sm text-gray-900 dark:text-white">{{ $venue->name }}</p>
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
                        {{-- TODO: Integrate actual map (Google Maps, OpenStreetMap, etc.) --}}
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
    @else
        <p class="text-sm text-gray-500">Venue information not available</p>
    @endif
</div>
