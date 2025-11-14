<div>
    @php
        $order = $getRecord();
        $tickets = $order->tickets()->with('ticketType.event')->get();
    @endphp

    @if($tickets->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Code</th>
                        <th class="px-3 py-2 text-left">Ticket Type</th>
                        <th class="px-3 py-2 text-left">Event Name</th>
                        <th class="px-3 py-2 text-left">Event Date</th>
                        <th class="px-3 py-2 text-left">Start Time</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $ticket)
                        @php
                            $event = $ticket->ticketType?->event;
                            $eventTitle = is_array($event?->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : ($event?->title ?? 'N/A');
                        @endphp
                        <tr class="border-t dark:border-gray-700">
                            <td class="px-3 py-2">
                                <a href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('edit', ['record' => $ticket]) }}" class="text-primary-600 hover:underline">
                                    {{ $ticket->code }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ $ticket->ticketType?->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2">{{ $eventTitle }}</td>
                            <td class="px-3 py-2">{{ $event?->event_date?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td class="px-3 py-2">{{ $event?->start_time ?? 'N/A' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                                    {{ $ticket->status === 'valid' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($ticket->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 text-sm">No tickets found for this order.</p>
    @endif
</div>
