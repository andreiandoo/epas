@php
    /** @var array $summary */
    /** @var array $events */
    /** @var string $currency */
    /** @var string $title */
    /** @var string $icon */
    /** @var string $iconColor */
    /** @var string $accentClass */
    /** @var string $headerBg */
    /** @var string $headerText */

    $impactColors = [
        'high' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        'medium' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'low' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    ];
    $impactLabels = ['high' => 'HIGH', 'medium' => 'MED', 'low' => 'LOW'];
@endphp

<div class="overflow-hidden bg-white border shadow-sm dark:bg-gray-800 rounded-xl {{ $accentClass }}">
    <div class="flex items-center gap-2 px-4 py-3 border-b {{ $headerBg }} {{ $accentClass }}">
        <x-dynamic-component :component="$icon" class="w-5 h-5 {{ $iconColor }}" />
        <h3 class="font-semibold {{ $headerText }}">{{ $title }}</h3>
        <span class="ml-auto text-xs font-medium {{ $headerText }} opacity-70">{{ number_format($summary['events_count']) }} evenimente</span>
    </div>

    {{-- Bucket KPIs --}}
    <div class="grid grid-cols-2 gap-2 p-3 border-b border-gray-100 dark:border-gray-700/50">
        <div>
            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Pierdut</p>
            <p class="text-base font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['total_lost'], 2) }} {{ $currency }}</p>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Câștigat</p>
            <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['total_earned'], 2) }} {{ $currency }}</p>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Invitații / bilete</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ number_format($summary['total_invitations']) }} / {{ number_format($summary['total_paid_tickets']) }}
                <span class="text-[10px] text-rose-600 dark:text-rose-400">({{ number_format($summary['invitation_ratio_pct'], 1) }}%)</span>
            </p>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Organizatori</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($summary['unique_organizers']) }}</p>
        </div>
    </div>

    {{-- Events table --}}
    @if(count($events) > 0)
    <div class="overflow-x-auto max-h-[600px]">
        <table class="w-full text-xs">
            <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900/80">
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-2 py-2 text-left text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Eveniment</th>
                    <th class="px-2 py-2 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Vând.</th>
                    <th class="px-2 py-2 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Inv.</th>
                    <th class="px-2 py-2 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Câștig</th>
                    <th class="px-2 py-2 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Pierd.</th>
                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">Imp.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $ev)
                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="px-2 py-2">
                        <a href="{{ route('filament.marketplace.resources.events.edit', ['record' => $ev['event_id'], 'tab' => 'vanzari']) }}"
                           class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 text-xs">
                            {{ $ev['event_title'] ?? ('Event #' . $ev['event_id']) }}
                        </a>
                        <div class="flex flex-wrap items-center gap-1 mt-0.5 text-[10px] text-gray-500 dark:text-gray-400">
                            @if($ev['event_date'] ?? null)<span>{{ $ev['event_date'] }}</span>@endif
                            @if($ev['venue_city'] ?? null)<span>·</span><span>{{ $ev['venue_city'] }}</span>@endif
                            @if($ev['organizer_name'] ?? null)<span>·</span><a href="{{ route('filament.marketplace.resources.organizers.edit', ['record' => $ev['organizer_id']]) }}" class="text-indigo-500 hover:underline">{{ $ev['organizer_name'] }}</a>@endif
                        </div>
                    </td>
                    <td class="px-2 py-2 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($ev['paid_tickets']) }}</td>
                    <td class="px-2 py-2 text-right text-rose-600 dark:text-rose-400 font-medium tabular-nums">{{ number_format($ev['invitations']) }} <span class="text-[10px] font-normal">({{ number_format($ev['invitation_ratio_pct'], 0) }}%)</span></td>
                    <td class="px-2 py-2 text-right text-emerald-700 dark:text-emerald-400 tabular-nums">{{ number_format($ev['commission_earned'], 0) }}</td>
                    <td class="px-2 py-2 text-right text-rose-700 dark:text-rose-400 font-bold tabular-nums">{{ number_format($ev['lost_commission_estimate'], 0) }}</td>
                    <td class="px-2 py-2 text-center">
                        <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-semibold rounded {{ $impactColors[$ev['impact']] ?? 'bg-gray-100 text-gray-700' }}">{{ $impactLabels[$ev['impact']] ?? '—' }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Niciun eveniment în acest bucket.
    </div>
    @endif
</div>
