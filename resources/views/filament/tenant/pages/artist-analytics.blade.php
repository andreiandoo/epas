@php
    $months   = $seriesMonths   ?? [];
    $evSeries = $seriesEvents   ?? [];
    $txSeries = $seriesTickets  ?? [];
    $rvSeries = $seriesRevenue  ?? [];

    $types  = $record->artistTypes?->map(fn($t) => $t->getTranslation('name', app()->getLocale()))->all() ?? [];
    $genres = $record->artistGenres?->map(fn($g) => $g->getTranslation('name', app()->getLocale()))->all() ?? [];

    $followers = [
        'Spotify' => $record->spotify_monthly_listeners ?? null,
        'YouTube' => $record->followers_youtube ?? null,
        'Instagram' => $record->followers_instagram ?? null,
        'Facebook' => $record->followers_facebook ?? null,
        'TikTok' => $record->followers_tiktok ?? null,
    ];

    $socialLinks = collect([
        'Website' => $record->website, 'Facebook' => $record->facebook_url,
        'Instagram' => $record->instagram_url, 'TikTok' => $record->tiktok_url,
        'YouTube' => $record->youtube_url, 'Spotify' => $record->spotify_url,
    ])->filter();

    $cs = $coreStats ?? ['total_tickets' => 0, 'unique_buyers' => 0, 'total_revenue' => 0];
    $totalEvents  = is_array($evSeries) ? array_sum($evSeries) : 0;
    $totalTickets = $cs['total_tickets'];
    $uniqueBuyers = $cs['unique_buyers'];
    $totalRevenue = $cs['total_revenue'];
    $avgTicketsPerEvent = $totalEvents > 0 ? round($totalTickets / $totalEvents, 1) : 0;
    $avgTicketPrice     = $totalTickets > 0 ? round($totalRevenue / $totalTickets, 2) : 0;

    $topVenues   = $topVenues   ?? collect();
    $topCities   = $topCities   ?? collect();
    $topCounties = $topCounties ?? collect();
    $artistEvents = $artistEvents ?? collect();

    $fmt = fn(?int $n) => (!$n) ? '—' : ($n >= 1000000 ? round($n/1000000,1).'M' : ($n >= 1000 ? round($n/1000,1).'K' : number_format($n)));
@endphp

<x-filament-panels::page>
    {{-- Hero / Artist Header --}}
    <div class="rounded-xl bg-gradient-to-r from-gray-900 to-gray-800 border border-gray-700/50 p-6 mb-6">
        <div class="flex items-center gap-5">
            @php
                $image = $record->main_image_url
                    ? asset('storage/' . $record->main_image_url)
                    : 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF&size=120';
            @endphp
            <img src="{{ $image }}" alt="{{ $record->name }}" class="w-20 h-20 rounded-full object-cover border-2 border-gray-600">
            <div>
                <h2 class="text-2xl font-bold text-white">{{ $record->name }}</h2>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                    @foreach($types as $type)
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">{{ $type }}</span>
                    @endforeach
                    @foreach($genres as $genre)
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-500/10 text-gray-300 border border-gray-500/20">{{ $genre }}</span>
                    @endforeach
                    @if($record->city || $record->country)
                        <span class="text-xs text-gray-400">{{ collect([$record->city, $record->country])->filter()->join(', ') }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Social Stats --}}
        <div class="flex items-center gap-4 mt-5 flex-wrap">
            @foreach($followers as $platform => $count)
                @if($count)
                    <div class="px-3 py-2 rounded-lg bg-gray-800/80 border border-gray-700/50 text-center min-w-[80px]">
                        <div class="text-xs text-gray-400">{{ $platform }}</div>
                        <div class="text-sm font-bold text-white mt-0.5">{{ $fmt($count) }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Social Links --}}
        @if($socialLinks->isNotEmpty())
            <div class="flex items-center gap-2 mt-3 flex-wrap">
                @foreach($socialLinks as $label => $url)
                    <a href="{{ $url }}" target="_blank" class="text-xs text-indigo-400 hover:text-indigo-300 transition">{{ $label }}</a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
        @php
            $kpiItems = [
                ['label' => 'Events', 'value' => $totalEvents, 'color' => 'text-white'],
                ['label' => 'Tickets Sold', 'value' => number_format($totalTickets), 'color' => 'text-emerald-400'],
                ['label' => 'Unique Buyers', 'value' => number_format($uniqueBuyers), 'color' => 'text-blue-400'],
                ['label' => 'Revenue', 'value' => number_format($totalRevenue, 2) . ' RON', 'color' => 'text-amber-400'],
                ['label' => 'Avg Tickets/Event', 'value' => $avgTicketsPerEvent, 'color' => 'text-cyan-400'],
                ['label' => 'Avg Ticket Price', 'value' => number_format($avgTicketPrice, 2) . ' RON', 'color' => 'text-purple-400'],
            ];
        @endphp
        @foreach($kpiItems as $kpi)
            <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                <div class="text-[11px] uppercase tracking-wider text-gray-400">{{ $kpi['label'] }}</div>
                <div class="text-xl font-bold {{ $kpi['color'] }} mt-1">{{ $kpi['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    @if(!empty($months))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                <div class="text-sm font-semibold text-gray-300 mb-3">Events per Month</div>
                <canvas id="eventsChart" height="180"></canvas>
            </div>
            <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                <div class="text-sm font-semibold text-gray-300 mb-3">Tickets Sold per Month</div>
                <canvas id="ticketsChart" height="180"></canvas>
            </div>
            <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                <div class="text-sm font-semibold text-gray-300 mb-3">Revenue per Month</div>
                <canvas id="revenueChart" height="180"></canvas>
            </div>
        </div>
    @endif

    {{-- Top Venues / Cities / Counties --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Top Venues --}}
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">Top Venues</div>
            <div class="divide-y divide-gray-800">
                @forelse($topVenues as $v)
                    @php
                        $vName = is_array($v->name) ? ($v->name[app()->getLocale()] ?? $v->name['en'] ?? array_values((array)$v->name)[0] ?? '') : ($v->name ?? '');
                        if (is_string($vName) && str_starts_with($vName, '{')) { $d = json_decode($vName, true); $vName = $d['en'] ?? $d['ro'] ?? reset($d) ?: $vName; }
                    @endphp
                    <div class="px-4 py-2 flex justify-between items-center text-xs">
                        <span class="text-gray-300 truncate">{{ $vName }}</span>
                        <span class="text-gray-400 font-mono">{{ number_format($v->tickets_count) }}</span>
                    </div>
                @empty
                    <div class="px-4 py-3 text-xs text-gray-500">No data</div>
                @endforelse
            </div>
        </div>

        {{-- Top Cities --}}
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">Top Cities</div>
            <div class="divide-y divide-gray-800">
                @forelse($topCities as $c)
                    <div class="px-4 py-2 flex justify-between items-center text-xs">
                        <span class="text-gray-300">{{ $c->name }}</span>
                        <span class="text-gray-400 font-mono">{{ number_format($c->tickets_count) }} <span class="text-gray-500">/ {{ $c->fans_count }} fans</span></span>
                    </div>
                @empty
                    <div class="px-4 py-3 text-xs text-gray-500">No data</div>
                @endforelse
            </div>
        </div>

        {{-- Top Counties --}}
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">Top Counties</div>
            <div class="divide-y divide-gray-800">
                @forelse($topCounties as $c)
                    <div class="px-4 py-2 flex justify-between items-center text-xs">
                        <span class="text-gray-300">{{ $c->name }}</span>
                        <span class="text-gray-400 font-mono">{{ number_format($c->tickets_count) }} <span class="text-gray-500">/ {{ $c->fans_count }} fans</span></span>
                    </div>
                @empty
                    <div class="px-4 py-3 text-xs text-gray-500">No data</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Events Table --}}
    @if($artistEvents->isNotEmpty())
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">Recent Events ({{ $artistEvents->count() }})</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-gray-800/50">
                            <th class="text-left px-3 py-2 text-gray-400 font-semibold">Date</th>
                            <th class="text-left px-3 py-2 text-gray-400 font-semibold">Event</th>
                            <th class="text-left px-3 py-2 text-gray-400 font-semibold">Venue</th>
                            <th class="text-left px-3 py-2 text-gray-400 font-semibold">Organizer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        @foreach($artistEvents->take(20) as $ev)
                            @php
                                $evTitle = is_array($ev->title) ? ($ev->title[app()->getLocale()] ?? $ev->title['en'] ?? $ev->title['ro'] ?? array_values($ev->title)[0] ?? '—') : ($ev->title ?? '—');
                                $evVenue = $ev->venue ? (is_array($ev->venue->name) ? ($ev->venue->name[app()->getLocale()] ?? $ev->venue->name['en'] ?? array_values($ev->venue->name)[0] ?? '') : $ev->venue->name) : '—';
                                if (is_string($evVenue) && str_starts_with($evVenue, '{')) { $d = json_decode($evVenue, true); $evVenue = $d['en'] ?? $d['ro'] ?? reset($d) ?: $evVenue; }
                                $isPast = $ev->event_date && $ev->event_date < now()->toDateString();
                            @endphp
                            <tr class="{{ $isPast ? 'opacity-50' : '' }}">
                                <td class="px-3 py-2 text-gray-300 whitespace-nowrap">{{ $ev->event_date ? \Carbon\Carbon::parse($ev->event_date)->format('d M Y') : '—' }}</td>
                                <td class="px-3 py-2 text-gray-200 font-medium">{{ \Illuminate\Support\Str::limit($evTitle, 50) }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ \Illuminate\Support\Str::limit($evVenue, 30) }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ $ev->tenant?->public_name ?? $ev->tenant?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Audience Personas --}}
    @php $personas = $audiencePersonas['personas'] ?? []; $aTotals = $audiencePersonas['totals'] ?? []; @endphp
    @if(!empty($personas))
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4 mb-6">
            <div class="text-sm font-semibold text-gray-300 mb-4">Audience Personas ({{ $aTotals['total_customers'] ?? 0 }} buyers)</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($personas as $persona)
                    <div class="rounded-lg bg-gray-800/50 border border-gray-700/30 p-4">
                        <div class="text-xs font-semibold text-indigo-400 uppercase mb-2">{{ $persona['label'] }}</div>
                        <div class="text-lg font-bold text-white">{{ $persona['age_group'] }} / {{ ucfirst($persona['gender']) }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $persona['count'] }} buyers ({{ $persona['percentage'] }}%)</div>
                        <div class="text-xs text-gray-400">Avg spend: {{ number_format($persona['avg_spend'], 2) }} RON</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Geographic Intelligence --}}
    @php $geo = $geoIntelligence ?? []; @endphp
    @if(!empty($geo))
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">Geographic Intelligence</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="bg-gray-800/50">
                        <th class="text-left px-3 py-2 text-gray-400">City</th>
                        <th class="text-right px-3 py-2 text-gray-400">Events</th>
                        <th class="text-right px-3 py-2 text-gray-400">Tickets</th>
                        <th class="text-right px-3 py-2 text-gray-400">Revenue</th>
                        <th class="text-left px-3 py-2 text-gray-400">Recommended Venue</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-800/50">
                        @foreach(array_slice($geo, 0, 15) as $g)
                            <tr>
                                <td class="px-3 py-2 text-gray-200 font-medium">{{ $g['city'] }} <span class="text-gray-500">{{ $g['country'] }}</span></td>
                                <td class="px-3 py-2 text-gray-400 text-right">{{ $g['events_count'] }}</td>
                                <td class="px-3 py-2 text-gray-300 text-right font-mono">{{ number_format($g['tickets_sold']) }}</td>
                                <td class="px-3 py-2 text-emerald-400 text-right font-mono">{{ number_format($g['total_revenue'], 0) }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ $g['recommended_venue'] ?? '—' }} @if($g['recommended_capacity'] ?? 0) <span class="text-gray-500">({{ number_format($g['recommended_capacity']) }} cap)</span> @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Performance Deep Dive --}}
    @php $perf = $performanceDeepDive ?? []; $perfEvents = $perf['events'] ?? []; $loyalty = $perf['customer_loyalty'] ?? []; $roleComp = $perf['role_comparison'] ?? []; @endphp
    @if(!empty($perfEvents))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            {{-- Customer Loyalty --}}
            @if(!empty($loyalty) && ($loyalty['total'] ?? 0) > 0)
                <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                    <div class="text-sm font-semibold text-gray-300 mb-3">Customer Loyalty</div>
                    <div class="grid grid-cols-4 gap-3 text-center">
                        <div><div class="text-lg font-bold text-white">{{ $loyalty['total'] }}</div><div class="text-[10px] text-gray-400">Total</div></div>
                        <div><div class="text-lg font-bold text-gray-400">{{ $loyalty['one_time'] }}</div><div class="text-[10px] text-gray-400">One-time</div></div>
                        <div><div class="text-lg font-bold text-blue-400">{{ $loyalty['repeat'] }}</div><div class="text-[10px] text-gray-400">Repeat</div></div>
                        <div><div class="text-lg font-bold text-amber-400">{{ $loyalty['superfan'] }}</div><div class="text-[10px] text-gray-400">Superfan (3+)</div></div>
                    </div>
                    <div class="mt-3 text-center text-xs"><span class="text-emerald-400 font-semibold">{{ $loyalty['repeat_rate'] }}%</span> <span class="text-gray-400">repeat rate</span></div>
                </div>
            @endif

            {{-- Role Comparison --}}
            @if(!empty($roleComp))
                <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                    <div class="text-sm font-semibold text-gray-300 mb-3">Role Comparison</div>
                    @foreach($roleComp as $role => $stats)
                        <div class="flex justify-between items-center py-2 border-b border-gray-800/50 text-xs">
                            <span class="text-gray-200 font-medium">{{ $role }}</span>
                            <span class="text-gray-400">{{ $stats['events'] }} events, avg {{ $stats['avg_sold'] }} sold, {{ $stats['avg_sell_through'] }}% ST</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Sales Intelligence --}}
    @php $sales = $salesIntelligence ?? []; $priceSens = $sales['price_sensitivity'] ?? []; $purchaseTiming = $sales['purchase_timing'] ?? []; @endphp
    @if(!empty($priceSens) || !empty($purchaseTiming))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            {{-- Price Sensitivity --}}
            @if(!empty($priceSens))
                <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                    <div class="text-sm font-semibold text-gray-300 mb-3">Price Sensitivity</div>
                    @foreach($priceSens as $ps)
                        <div class="flex justify-between items-center py-1.5 text-xs">
                            <span class="text-gray-300">{{ $ps['range'] }} RON</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-1.5 bg-gray-800 rounded-full overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" style="width: {{ min($ps['sell_through'], 100) }}%"></div></div>
                                <span class="text-gray-400 font-mono w-12 text-right">{{ $ps['sell_through'] }}%</span>
                            </div>
                        </div>
                    @endforeach
                    @if($sales['fee_comparison'] ?? null)
                        <div class="mt-3 pt-2 border-t border-gray-800 text-xs">
                            <span class="text-gray-400">Fee range:</span>
                            <span class="text-white font-semibold">{{ number_format($sales['fee_comparison']['min_fee'] ?? 0) }} - {{ number_format($sales['fee_comparison']['max_fee'] ?? 0) }} EUR</span>
                            <span class="ml-2 {{ ($sales['fee_comparison']['in_range'] ?? false) ? 'text-emerald-400' : 'text-amber-400' }}">Avg revenue: {{ number_format($sales['fee_comparison']['avg_revenue'] ?? 0) }}</span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Purchase Timing --}}
            @if(!empty($purchaseTiming))
                <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4">
                    <div class="text-sm font-semibold text-gray-300 mb-3">Purchase Timing <span class="text-gray-500 text-[10px]">(avg {{ $sales['avg_lead_days'] ?? 0 }}d before event)</span></div>
                    @php $timingLabels = ['super_early' => '90+ days', 'early_bird' => '31-90 days', 'last_month' => '8-30 days', 'last_week' => '2-7 days', 'last_minute' => 'Last minute']; @endphp
                    @foreach($timingLabels as $key => $label)
                        @if(($purchaseTiming[$key] ?? 0) > 0)
                            <div class="flex justify-between items-center py-1.5 text-xs">
                                <span class="text-gray-300">{{ $label }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-24 h-1.5 bg-gray-800 rounded-full overflow-hidden"><div class="h-full bg-blue-500 rounded-full" style="width: {{ min($purchaseTiming[$key], 100) }}%"></div></div>
                                    <span class="text-gray-400 font-mono w-12 text-right">{{ $purchaseTiming[$key] }}%</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- City Expansion Planner --}}
    @php $expansion = $expansionPlanner ?? []; @endphp
    @if(!empty($expansion))
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">City Expansion Opportunities</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="bg-gray-800/50">
                        <th class="text-left px-3 py-2 text-gray-400">City</th>
                        <th class="text-right px-3 py-2 text-gray-400">Similar Events</th>
                        <th class="text-right px-3 py-2 text-gray-400">Est. Demand</th>
                        <th class="text-right px-3 py-2 text-gray-400">Sell-Through</th>
                        <th class="text-center px-3 py-2 text-gray-400">Confidence</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-800/50">
                        @foreach(array_slice($expansion, 0, 10) as $exp)
                            <tr>
                                <td class="px-3 py-2 text-gray-200 font-medium">{{ $exp['city'] }}</td>
                                <td class="px-3 py-2 text-gray-400 text-right">{{ $exp['similar_events'] }}</td>
                                <td class="px-3 py-2 text-gray-300 text-right">{{ number_format($exp['estimated_demand']) }}</td>
                                <td class="px-3 py-2 text-gray-300 text-right">{{ $exp['similar_sell_through'] }}%</td>
                                <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $exp['confidence'] === 'high' ? 'bg-emerald-500/10 text-emerald-400' : ($exp['confidence'] === 'medium' ? 'bg-amber-500/10 text-amber-400' : 'bg-gray-500/10 text-gray-400') }}">{{ $exp['confidence'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Upcoming Events Analysis --}}
    @php $upcoming = $upcomingAnalysis ?? []; @endphp
    @if(!empty($upcoming))
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-700/50 text-sm font-semibold text-gray-300">Upcoming Events Tracker</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="bg-gray-800/50">
                        <th class="text-left px-3 py-2 text-gray-400">Event</th>
                        <th class="text-left px-3 py-2 text-gray-400">Venue</th>
                        <th class="text-right px-3 py-2 text-gray-400">Days Left</th>
                        <th class="text-right px-3 py-2 text-gray-400">Sold / Cap</th>
                        <th class="text-right px-3 py-2 text-gray-400">Sell-Through</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-800/50">
                        @foreach($upcoming as $ue)
                            <tr>
                                <td class="px-3 py-2"><span class="text-gray-200 font-medium">{{ \Illuminate\Support\Str::limit($ue['title'], 40) }}</span><br><span class="text-gray-500">{{ $ue['date'] }}</span></td>
                                <td class="px-3 py-2 text-gray-400">{{ $ue['venue'] ?? '—' }} <span class="text-gray-500">{{ $ue['city'] ?? '' }}</span></td>
                                <td class="px-3 py-2 text-right {{ ($ue['days_until'] ?? 999) <= 7 ? 'text-red-400 font-semibold' : 'text-gray-300' }}">{{ $ue['days_until'] ?? '—' }}d</td>
                                <td class="px-3 py-2 text-right text-gray-300 font-mono">{{ $ue['sold'] }} / {{ $ue['capacity'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-right"><span class="{{ ($ue['sell_through'] ?? 0) >= 80 ? 'text-emerald-400' : (($ue['sell_through'] ?? 0) >= 50 ? 'text-blue-400' : 'text-gray-400') }} font-semibold">{{ $ue['sell_through'] ?? '—' }}%</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Opportunities / Recommendations --}}
    @php $opps = $opportunities ?? []; $recs = $opps['recommendations'] ?? []; @endphp
    @if(!empty($recs))
        <div class="rounded-xl bg-gray-900 border border-gray-700/50 p-4 mb-6">
            <div class="text-sm font-semibold text-gray-300 mb-4">Recommendations</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($recs as $rec)
                    <div class="rounded-lg bg-gray-800/50 border border-gray-700/30 p-3">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/10 text-indigo-300">{{ $rec['category'] }}</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $rec['confidence'] === 'high' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' }}">{{ $rec['confidence'] }}</span>
                        </div>
                        <div class="text-sm font-semibold text-white">{{ $rec['title'] }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $rec['detail'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Chart.js Scripts --}}
    @if(!empty($months))
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const months = @json($months);
            const chartDefaults = {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748B', font: { size: 10 } } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748B', font: { size: 10 } }, beginAtZero: true }
                }
            };

            new Chart(document.getElementById('eventsChart'), {
                type: 'bar',
                data: { labels: months, datasets: [{ data: @json($evSeries), backgroundColor: 'rgba(99,102,241,0.6)', borderRadius: 4 }] },
                options: chartDefaults
            });

            new Chart(document.getElementById('ticketsChart'), {
                type: 'bar',
                data: { labels: months, datasets: [{ data: @json($txSeries), backgroundColor: 'rgba(16,185,129,0.6)', borderRadius: 4 }] },
                options: chartDefaults
            });

            new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: { labels: months, datasets: [{ data: @json($rvSeries), borderColor: '#fbbf24', backgroundColor: 'rgba(251,191,36,0.1)', fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#fbbf24' }] },
                options: chartDefaults
            });
        });
        </script>
    @endif
</x-filament-panels::page>
