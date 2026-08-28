{{-- One event card in the visitor history. Vars: $ev, $isCust (bool), $upcoming (bool). --}}
@php
    $badgeClass = $upcoming
        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';

    // Order status → [text color, human label]
    $orderStatusMap = [
        'paid'               => ['text-green-600 dark:text-green-400', 'Plătită'],
        'confirmed'          => ['text-green-600 dark:text-green-400', 'Confirmată'],
        'completed'          => ['text-green-600 dark:text-green-400', 'Finalizată'],
        'partially_refunded' => ['text-amber-600 dark:text-amber-400', 'Parțial rambursată'],
        'refunded'           => ['text-amber-600 dark:text-amber-400', 'Rambursată'],
        'cancelled'          => ['text-red-600 dark:text-red-400', 'Anulată'],
        'expired'            => ['text-red-600 dark:text-red-400', 'Expirată'],
        'failed'             => ['text-red-600 dark:text-red-400', 'Eșuată'],
        'pending'            => ['text-gray-500', 'În așteptare'],
    ];
    $eventUrl = !empty($ev['event_id'])
        ? \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $ev['event_id']])
        : null;
@endphp
<div class="rounded-lg px-2.5 py-1.5 {{ $upcoming ? 'border border-green-200 dark:border-green-800 bg-green-50/60 dark:bg-green-900/15' : 'border-b border-gray-50 dark:border-gray-800' }}">
    <div class="flex items-center justify-between gap-2">
        @if($eventUrl)
            <a href="{{ $eventUrl }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline truncate">{{ $ev['event_title'] }}</a>
        @else
            <span class="text-xs font-medium text-gray-800 dark:text-gray-100 truncate">{{ $ev['event_title'] }}</span>
        @endif
        <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $badgeClass }}">
            {{ $isCust ? (($ev['total'] ?? 0) . ' bilete') : (($ev['tickets_sold'] ?? 0) . ' vândute') }}
        </span>
    </div>
    @if(!empty($ev['event_date']))
        <div class="text-[11px] text-gray-400">{{ $ev['event_date'] }}</div>
    @endif
    @if($isCust && !empty($ev['orders']))
        <div class="mt-1 space-y-0.5 pl-2 border-l-2 {{ $upcoming ? 'border-green-200 dark:border-green-800' : 'border-gray-100 dark:border-gray-700' }}">
            @foreach($ev['orders'] as $o)
                @php [$sColor, $sLabel] = $orderStatusMap[$o['status']] ?? ['text-gray-500', ucfirst($o['status'])]; @endphp
                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    <a href="{{ \App\Filament\Marketplace\Resources\OrderResource::getUrl('view', ['record' => $o['order_id']]) }}"
                       target="_blank" rel="noopener" title="Status: {{ $sLabel }}"
                       class="font-semibold hover:underline {{ $sColor }}">{{ $o['order_number'] }}</a>
                    <span class="text-gray-400"> · {{ $o['tickets'] }} bilete · {{ $o['order_date'] }} · {{ $o['payment'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
