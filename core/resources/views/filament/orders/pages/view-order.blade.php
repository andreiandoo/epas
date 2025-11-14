<x-filament-panels::page>
    @php
        $order = $this->record;
        $tickets = $order->tickets()->with('ticketType.event')->get();
        $firstTicket = $tickets->first();
        $event = $firstTicket?->ticketType?->event;

        $eventTitle = '';
        $eventDescription = '';

        if ($event) {
            $titleData = $event->title;
            $eventTitle = is_array($titleData) ? ($titleData['en'] ?? $titleData['ro'] ?? reset($titleData)) : ($titleData ?? '');

            $descData = $event->short_description;
            $eventDescription = is_array($descData) ? ($descData['en'] ?? $descData['ro'] ?? reset($descData)) : ($descData ?? '');
        }
    @endphp

    {{-- Order Header --}}
    <div class="mb-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
        <div class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Order #{{ $order->id }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $order->created_at->format('F j, Y \a\t H:i') }}</p>
                </div>
                <div>
                    <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full
                        @if($order->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($order->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @elseif(in_array($order->status, ['cancelled', 'failed'])) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                        @elseif($order->status === 'refunded') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                        @endif">
                        {{ ucfirst($order->status) }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tenant</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        @if($order->tenant)
                            <a href="{{ \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $order->tenant]) }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                {{ $order->tenant->name }}
                            </a>
                        @else
                            N/A
                        @endif
                    </p>
                    @if($order->tenant?->domain)
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $order->tenant->domain }}</p>
                    @endif
                </div>

                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Customer</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        @if($order->customer)
                            <a href="{{ \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $order->customer]) }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                {{ $order->customer->full_name ?? $order->customer->email }}
                            </a>
                        @else
                            {{ $order->customer_email ?? 'N/A' }}
                        @endif
                    </p>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Total</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format(($order->total_cents ?? 0) / 100, 2) }} RON</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tickets->count() }} {{ $tickets->count() === 1 ? 'ticket' : 'tickets' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tickets Section --}}
    <x-filament::section>
        <x-slot name="heading">
            Tickets ({{ $tickets->count() }})
        </x-slot>

        @if($tickets->count() > 0)
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($tickets as $ticket)
                    @php
                        $ticketEvent = $ticket->ticketType?->event;
                        $ticketEventTitle = '';
                        if ($ticketEvent) {
                            $titleData = $ticketEvent->title;
                            $ticketEventTitle = is_array($titleData) ? ($titleData['en'] ?? $titleData['ro'] ?? reset($titleData)) : ($titleData ?? 'N/A');
                        }
                    @endphp
                    <div class="p-4 transition-shadow bg-white border border-gray-200 rounded-lg hover:shadow-md dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <a href="{{ \App\Filament\Resources\Tickets\TicketResource::getUrl('view', ['record' => $ticket]) }}" class="text-sm font-mono font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $ticket->code }}
                                </a>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $ticket->ticketType?->name ?? 'N/A' }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md shrink-0
                                {{ $ticket->status === 'valid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ ucfirst($ticket->status) }}
                            </span>
                        </div>
                        @if($ticketEvent)
                            <div class="pt-3 mt-3 border-t border-gray-100 dark:border-gray-700">
                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $ticketEventTitle }}</p>
                                <div class="flex items-center gap-3 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>📅 {{ $ticketEvent->event_date?->format('M j, Y') ?? 'N/A' }}</span>
                                    @if($ticketEvent->start_time)
                                        <span>🕐 {{ $ticketEvent->start_time }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No tickets found for this order.</p>
        @endif
    </x-filament::section>

    {{-- Event Details Section --}}
    @if($event)
        <x-filament::section>
            <x-slot name="heading">
                Event Details
            </x-slot>

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
        </x-filament::section>
    @endif

    {{-- Order Metadata Section --}}
    @if(!empty($order->meta))
        <x-filament::section>
            <x-slot name="heading">
                Order Metadata
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left">Key</th>
                            <th class="px-3 py-2 text-left">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->meta as $key => $value)
                            <tr class="border-t dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $key }}</td>
                                <td class="px-3 py-2">{{ is_array($value) ? json_encode($value) : $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
