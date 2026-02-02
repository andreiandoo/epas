<div class="space-y-3">
    @forelse($record->tickets as $ticket)
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $ticket->ticketType?->name ?? 'Bilet' }}
                    </span>
                    @if($ticket->code)
                        <span class="px-2 py-0.5 text-xs font-mono bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 rounded">
                            {{ $ticket->code }}
                        </span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    @if($ticket->event)
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ $ticket->event->getTranslation('title', app()->getLocale()) ?? $ticket->event->title ?? 'Eveniment' }}
                        </span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @if($ticket->attendee_name)
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ $ticket->attendee_name }}
                            @if($ticket->attendee_email)
                                ({{ $ticket->attendee_email }})
                            @endif
                        </span>
                    @endif
                </div>
            </div>
            <div class="text-right flex flex-col items-end gap-1">
                <div class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($ticket->price ?? 0, 2) }} {{ $record->currency ?? 'RON' }}
                </div>
                <span class="px-2 py-0.5 rounded text-xs {{ match($ticket->status) {
                    'valid' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                    'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                    'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                    'used' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                } }}">
                    {{ match($ticket->status) {
                        'valid' => 'Valid',
                        'pending' => 'In asteptare',
                        'cancelled' => 'Anulat',
                        'used' => 'Folosit',
                        default => ucfirst($ticket->status),
                    } }}
                </span>
                <div class="flex items-center gap-2">
                    @if($ticket->barcode)
                        <a href="#" onclick="navigator.clipboard.writeText('{{ $ticket->barcode }}'); alert('Barcode copiat!'); return false;"
                           class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Barcode
                        </a>
                    @endif
                    <a href="{{ \App\Filament\Marketplace\Resources\TicketResource::getUrl('view', ['record' => $ticket->id]) }}"
                       class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline inline-flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                        Vezi bilet
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="text-gray-500 dark:text-gray-400 text-center py-4">
            Nu exista bilete pentru aceasta comanda.
        </div>
    @endforelse
</div>
