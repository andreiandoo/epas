<div class="space-y-3">
    @forelse($record->tickets as $ticket)
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex-1">
                <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ $ticket->ticketType?->name ?? 'Bilet' }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @if($ticket->attendee_name)
                        {{ $ticket->attendee_name }}
                        @if($ticket->attendee_email)
                            ({{ $ticket->attendee_email }})
                        @endif
                    @else
                        Cod: {{ $ticket->code }}
                    @endif
                </div>
            </div>
            <div class="text-right">
                <div class="font-semibold">
                    {{ number_format($ticket->price ?? 0, 2) }} {{ $record->currency ?? 'RON' }}
                </div>
                <div class="text-xs">
                    <span class="px-2 py-0.5 rounded {{ match($ticket->status) {
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
                </div>
            </div>
        </div>
    @empty
        <div class="text-gray-500 dark:text-gray-400 text-center py-4">
            Nu exista bilete pentru aceasta comanda.
        </div>
    @endforelse
</div>
