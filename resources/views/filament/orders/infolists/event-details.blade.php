<div>
    @php
        $order = $getRecord();
        $firstTicket = $order->tickets()->with('ticketType.event')->first();
        $event = $firstTicket?->ticketType?->event;

        if ($event) {
            $eventTitle = is_array($event->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : $event->title;
            $eventDescription = is_array($event->short_description) ? ($event->short_description['en'] ?? $event->short_description['ro'] ?? reset($event->short_description)) : ($event->short_description ?? '');
        }
    @endphp

    @if($event)
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Event ID</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $event->id }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Event Name</p>
                    <p class="text-sm text-gray-900 dark:text-white">
                        <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event]) }}" class="text-primary-600 hover:underline">
                            {{ $eventTitle }}
                        </a>
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Date</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $event->event_date?->format('Y-m-d') ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Start Time</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $event->start_time ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">End Time</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $event->end_time ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Door Time</p>
                    <p class="text-sm text-gray-900 dark:text-white">{{ $event->door_time ?? 'N/A' }}</p>
                </div>
            </div>

            @if($eventDescription)
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Short Description</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $eventDescription }}</p>
                </div>
            @endif

            @if($event->poster_url)
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Event Poster</p>
                    <img src="{{ $event->poster_url }}" alt="Event Poster" class="max-w-xs rounded-lg shadow-md">
                </div>
            @endif
        </div>
    @else
        <p class="text-gray-500 text-sm">No event information available.</p>
    @endif
</div>
