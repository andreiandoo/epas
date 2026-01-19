@php
    $tickets = $record->tickets->load(['ticketType.event']);
    $tenantId = $record->tenant_id;
@endphp

<div class="space-y-2">
    @foreach($tickets as $ticket)
        @php
            $event = $ticket->ticketType?->event;
            $ticketType = $ticket->ticketType;
            $ticketUrl = route('filament.tenant.resources.tickets.view', ['record' => $ticket->id, 'tenant' => $tenantId]);
        @endphp
        <a href="{{ $ticketUrl }}" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer block">
            <div class="flex-1">
                <div class="font-medium text-gray-900 dark:text-white">
                    {{ $event?->getTranslation('title', 'ro') ?? 'Eveniment necunoscut' }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $ticketType?->name ?? 'Tip bilet necunoscut' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1 font-mono">
                    {{ $ticket->code }}
                </div>
            </div>
            <div class="text-right flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ match($ticket->status) {
                    'valid' => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400',
                    'used' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-400',
                    'cancelled' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400',
                    default => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400',
                } }}">
                    {{ match($ticket->status) {
                        'valid' => 'Valid',
                        'used' => 'Folosit',
                        'cancelled' => 'Anulat',
                        'pending' => 'In asteptare',
                        default => ucfirst($ticket->status ?? 'N/A'),
                    } }}
                </span>
                <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ number_format(($ticketType?->price_cents ?? 0) / 100, 2) }} {{ $ticketType?->currency ?? 'RON' }}
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </div>
        </a>
    @endforeach
</div>
