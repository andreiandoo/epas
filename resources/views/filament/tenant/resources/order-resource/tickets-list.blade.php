@php
    $tickets = $getRecord()->tickets->load(['ticketType.event']);
    $groupedTickets = $tickets->groupBy('ticket_type_id');
@endphp

<div class="space-y-2">
    @foreach($groupedTickets as $ticketTypeId => $ticketGroup)
        @php
            $first = $ticketGroup->first();
            $event = $first->ticketType?->event;
            $ticketType = $first->ticketType;
            $quantity = $ticketGroup->count();
        @endphp
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
            <div class="flex-1">
                <div class="font-medium text-gray-900">
                    {{ $event?->getTranslation('title', 'ro') ?? 'Eveniment necunoscut' }}
                </div>
                <div class="text-sm text-gray-600">
                    {{ $ticketType?->name ?? 'Tip bilet necunoscut' }}
                </div>
                @if($event)
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $event->event_date?->format('d M Y') }} {{ $event->start_time ? '• ' . $event->start_time : '' }}
                    </div>
                @endif
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                    &times;{{ $quantity }}
                </span>
                <div class="text-sm font-medium text-gray-900 mt-1">
                    {{ number_format(($ticketType?->price_cents ?? 0) / 100, 2) }} {{ $ticketType?->currency ?? 'RON' }}
                </div>
            </div>
        </div>
    @endforeach
</div>
