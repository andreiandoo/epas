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
                <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;{{ match($ticket->status) {
                    'valid' => 'background:#dcfce7;color:#166534;',
                    'used' => 'background:#e5e7eb;color:#374151;',
                    'cancelled' => 'background:#fee2e2;color:#991b1b;',
                    default => 'background:#fef3c7;color:#92400e;',
                } }}">
                    {{ match($ticket->status) {
                        'valid' => 'Valid',
                        'used' => 'Folosit',
                        'cancelled' => 'Anulat',
                        'pending' => 'În așteptare',
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
