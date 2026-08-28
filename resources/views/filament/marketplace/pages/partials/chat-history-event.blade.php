{{-- One event card in the visitor history. Vars: $ev, $isCust (bool), $upcoming (bool). --}}
@php
    $badgeClass = $upcoming
        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
@endphp
<div class="rounded-lg px-2.5 py-1.5 {{ $upcoming ? 'border border-green-200 dark:border-green-800 bg-green-50/60 dark:bg-green-900/15' : 'border-b border-gray-50 dark:border-gray-800' }}">
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-medium text-gray-800 dark:text-gray-100 truncate">{{ $ev['event_title'] }}</span>
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
                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-gray-600 dark:text-gray-300">{{ $o['order_number'] }}</span>
                    · {{ $o['tickets'] }} bilete · {{ $o['order_date'] }} · {{ $o['payment'] }}
                </div>
            @endforeach
        </div>
    @endif
</div>
