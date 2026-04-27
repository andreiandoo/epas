@php
    /** @var \App\Models\Tour $tour */
    use Illuminate\Support\Str;

    $events = $tour->events()
        ->with(['venue:id,name,city', 'ticketTypes:id,event_id,name,price_cents,quota_total,quota_sold', 'artists:id,name,slug'])
        ->orderBy('event_date')
        ->get();

    $period = $tour->period;
    $totalCapacity = $tour->total_capacity;
    $totalSold = $tour->total_sold;
    $cities = $tour->cities;
    $artists = $tour->distinct_artists;

    $fmtPeriod = function ($p) {
        if (!$p['start']) return '—';
        if (!$p['end'] || $p['start']->isSameDay($p['end'])) {
            return $p['start']->format('d.m.Y');
        }
        return $p['start']->format('d.m.Y') . ' → ' . $p['end']->format('d.m.Y');
    };

    $fmtCapacity = fn ($v) => $v < 0 ? '∞' : number_format($v);

    $eventTitle = function ($event) {
        $t = $event->title;
        if (is_array($t)) return $t['ro'] ?? $t['en'] ?? reset($t) ?? '—';
        return $t ?? '—';
    };

    $venueName = function ($venue) {
        if (!$venue) return '—';
        $n = $venue->name;
        if (is_array($n)) return $n['ro'] ?? $n['en'] ?? reset($n) ?? '—';
        return $n ?? '—';
    };
@endphp

@if($events->isEmpty())
    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Niciun eveniment atașat momentan. Atașează un eveniment din editarea acelui eveniment → tab <strong>Turneu</strong>.
        </p>
    </div>
@else
    {{-- Summary cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Evenimente</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $events->count() }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Perioadă</div>
            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $fmtPeriod($period) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Capacitate totală</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fmtCapacity($totalCapacity) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Bilete vândute</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($totalSold) }}</div>
        </div>
    </div>

    {{-- Cities chips --}}
    @if($cities->isNotEmpty())
        <div class="mb-3">
            <div class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Orașe ({{ $cities->count() }})</div>
            <div class="flex flex-wrap gap-1">
                @foreach($cities as $city)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $city }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Artists chips --}}
    @if($artists->isNotEmpty())
        <div class="mb-4">
            <div class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Artiști implicați ({{ $artists->count() }})</div>
            <div class="flex flex-wrap gap-1">
                @foreach($artists as $artist)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">{{ $artist->name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Per-event table --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <th class="py-2 px-3">Data</th>
                    <th class="py-2 px-3">Eveniment</th>
                    <th class="py-2 px-3">Venue · Oraș</th>
                    <th class="py-2 px-3 text-right">Capacitate</th>
                    <th class="py-2 px-3 text-right">Vândute</th>
                    <th class="py-2 px-3">Tipuri bilete</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($events as $event)
                    @php
                        $eventCap = $event->total_capacity;
                        $eventSold = \App\Models\Ticket::whereHas('order', function ($q) use ($event) {
                            $q->where('event_id', $event->id)
                                ->whereIn('status', ['paid', 'confirmed', 'completed']);
                        })->whereIn('status', ['valid', 'used'])->count();
                        $editUrl = '/marketplace/events/' . $event->id . '/edit';
                    @endphp
                    <tr class="bg-white dark:bg-gray-900">
                        <td class="py-2 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                            {{ $event->event_date?->format('d.m.Y') ?? '—' }}
                        </td>
                        <td class="py-2 px-3">
                            <a href="{{ $editUrl }}" target="_blank" class="font-medium text-primary-600 hover:underline">
                                {{ $eventTitle($event) }}
                            </a>
                        </td>
                        <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                            {{ $venueName($event->venue) }}
                            @if($event->venue?->city)
                                <span class="text-gray-400"> · {{ $event->venue->city }}</span>
                            @endif
                        </td>
                        <td class="py-2 px-3 text-right font-mono text-gray-700 dark:text-gray-300">
                            {{ $fmtCapacity($eventCap) }}
                        </td>
                        <td class="py-2 px-3 text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $eventSold > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                                {{ number_format($eventSold) }}
                                @if($eventCap > 0)
                                    <span class="ml-1 text-[10px] text-gray-400">/ {{ number_format($eventCap) }}</span>
                                @endif
                            </span>
                        </td>
                        <td class="py-2 px-3">
                            @if($event->ticketTypes->isEmpty())
                                <span class="text-xs text-gray-400">—</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($event->ticketTypes as $tt)
                                        @php
                                            $price = $tt->price_cents ? number_format($tt->price_cents / 100, 2) . ' lei' : 'Gratis';
                                            $sold = (int) ($tt->quota_sold ?? 0);
                                            $quota = (int) ($tt->quota_total ?? 0);
                                            $isInvitation = (bool) ($tt->meta['is_invitation'] ?? false);
                                        @endphp
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] {{ $isInvitation ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}"
                                              title="{{ $tt->name }} · {{ $price }} · vândute {{ $sold }}{{ $quota > 0 ? ' / ' . $quota : '' }}">
                                            <strong>{{ Str::limit($tt->name, 18) }}</strong>
                                            <span class="text-gray-500">· {{ $price }}</span>
                                            <span class="text-gray-500">· {{ $sold }}{{ $quota > 0 ? '/' . $quota : '' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
