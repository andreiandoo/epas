@php
    $months = $seriesMonths ?? [];
    $evSeries = $seriesEvents ?? [];
    $txSeries = $seriesTickets ?? [];
    $rvSeries = $seriesRevenue ?? [];
    $ocSeries = $seriesOccupancy ?? [];
    $kpis = $kpis ?? [];
    $fmt = fn(?int $n) => (!$n) ? '—' : ($n >= 1000000 ? round($n/1000000,1).'M' : ($n >= 1000 ? round($n/1000,1).'K' : number_format($n)));

    $venueName = $venue->name;
    if (is_array($venueName)) $venueName = $venueName['en'] ?? $venueName['ro'] ?? reset($venueName) ?: '—';
    $cap = $venue->capacity ?: $venue->capacity_total ?: (($venue->capacity_standing ?? 0) + ($venue->capacity_seated ?? 0));
@endphp

<x-filament-panels::page>
<style>
:root{--bg:#0b1020;--card:#10183a;--card2:#0d1431;--muted:#a7b0c3;--text:#e9eefb;--primary:#7aa2ff;--accent:#22d3ee;--success:#22c55e;--ring:rgba(122,162,255,.15);--warn:#fbbf24;--danger:#ef4444;}
.fi-page,body{background:var(--bg);color:var(--text);}
.fi-page > .fi-header,.fi-page-header-ctn,header.fi-header{display:none!important;}
[x-cloak]{display:none!important;}
a{color:var(--primary);text-decoration:none}a:hover{text-decoration:underline}
.db{max-width:100%;width:100%;margin:0 auto;padding:0;}
.card{background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--ring);border-radius:12px;}
.card-h{padding:12px 16px;border-bottom:1px solid var(--ring);font-weight:600;font-size:13px;color:#cdd7f6;}
.card-b{padding:14px 16px;}
.kpi-grid{display:grid;gap:10px;grid-template-columns:repeat(6,1fr);}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:700px){.kpi-grid{grid-template-columns:repeat(2,1fr);}}
.kpi{padding:14px;border-radius:12px;background:linear-gradient(180deg,#0f1634,#0d1330);border:1px solid var(--ring);}
.kpi .l{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
.kpi .v{margin-top:4px;font-size:20px;font-weight:700;}
.tbl{width:100%;border-collapse:separate;border-spacing:0;border:1px solid var(--ring);overflow:hidden;font-size:13px;}
.tbl thead th{background:#0f1736;color:#cdd7f6;font-weight:600;text-align:left;padding:8px 10px;border-bottom:1px solid var(--ring);}
.tbl tbody td{padding:8px 10px;border-bottom:1px dashed rgba(122,162,255,.08);color:#dbe6ff;}
.tbl tbody tr:last-child td{border-bottom:0;}
.tabs{display:flex;gap:0;flex-wrap:wrap;margin-bottom:16px;border:1px solid var(--ring);border-radius:12px;overflow:hidden;justify-content:stretch;}
.tab{flex:1;padding:10px 16px;font-size:13px;font-weight:600;color:var(--muted);background:transparent;border:none;border-right:1px solid var(--ring);cursor:pointer;transition:all .15s;letter-spacing:.3px;display:inline-flex;align-items:center;justify-content:center;gap:6px;white-space:nowrap;}
.tab:last-child{border-right:none;}
.tab:hover{color:var(--text);background:rgba(122,162,255,.06);}
.tab.active{color:var(--accent);background:rgba(34,211,238,.08);}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
@media(max-width:800px){.g2,.g3{grid-template-columns:1fr;}}
.progress{height:5px;background:rgba(122,162,255,.1);border-radius:3px;overflow:hidden;flex:1;min-width:40px;}
.progress-fill{height:100%;border-radius:3px;}
.chip{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid var(--ring);}
canvas{width:100%!important;}
</style>

<div class="db" wire:ignore x-data="{
    tab: 'overview',
    simGenre: '', simDay: 'Saturday', simPrice: 100, simResult: null, simLoading: false,
    suggestions: null, sugLoading: false,
    calendarResult: null, calLoading: false,
    cmpA: null, cmpB: null, cmpResult: null, cmpLoading: false,
    runSimulation() {
        if (!this.simGenre) return;
        this.simLoading = true;
        $wire.call('simulateEventApi', this.simGenre, this.simDay, parseFloat(this.simPrice)).then(r => { this.simResult = r; this.simLoading = false; });
    },
    loadSuggestions() {
        this.sugLoading = true;
        $wire.call('getEventSuggestionsApi').then(r => { this.suggestions = r; this.sugLoading = false; });
    },
    loadCalendar(eventId) {
        this.calLoading = true;
        $wire.call('getCreativeCalendarApi', eventId).then(r => { this.calendarResult = r; this.calLoading = false; });
    },
    runComparison() {
        if (!this.cmpA || !this.cmpB) return;
        this.cmpLoading = true;
        $wire.call('compareEventsApi', parseInt(this.cmpA), parseInt(this.cmpB)).then(r => { this.cmpResult = r; this.cmpLoading = false; });
    }
}">

    {{-- VENUE HEADER --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card-b" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="width:64px;height:64px;border-radius:12px;background:linear-gradient(135deg,#1e293b,#334155);display:flex;align-items:center;justify-content:center;font-size:28px;border:1px solid var(--ring);">🏛️</div>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <h2 style="font-size:22px;font-weight:700;margin:0;">{{ $venueName }}</h2>
                    @if(count($allVenues) > 1)
                        <select onchange="if(this.value) Livewire.find('{{ $_instance->getId() }}').call('switchVenueApi', parseInt(this.value)).then(() => location.reload())"
                            style="background:var(--card);border:1px solid var(--ring);border-radius:8px;color:var(--text);padding:4px 8px;font-size:12px;cursor:pointer;">
                            @foreach($allVenues as $av)
                                <option value="{{ $av['id'] }}" {{ $av['id'] === $selectedVenueId ? 'selected' : '' }}>{{ $av['name'] }} ({{ $av['city'] }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div style="color:var(--muted);font-size:13px;margin-top:4px;">
                    {{ $venue->city ?? '' }}{{ $venue->country ? ', ' . $venue->country : '' }}
                    @if($cap) <span style="margin-left:12px;">Capacity: <strong style="color:var(--text);">{{ number_format($cap) }}</strong></span> @endif
                </div>
            </div>
            @if($venue->website_url || $venue->facebook_url || $venue->instagram_url)
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    @foreach(collect(['Website' => $venue->website_url, 'Facebook' => $venue->facebook_url, 'Instagram' => $venue->instagram_url, 'TikTok' => $venue->tiktok_url])->filter() as $label => $url)
                        <a href="{{ $url }}" target="_blank" style="font-size:11px;padding:4px 10px;border-radius:8px;background:rgba(122,162,255,.08);border:1px solid var(--ring);">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- KPI CARDS --}}
    <div class="kpi-grid" style="margin-bottom:16px;">
        @foreach([
            ['l' => 'Events', 'v' => $kpis['total_events'] ?? 0, 'c' => 'var(--text)'],
            ['l' => 'Tickets Sold', 'v' => number_format($kpis['total_tickets'] ?? 0), 'c' => 'var(--success)'],
            ['l' => 'Revenue', 'v' => number_format($kpis['total_revenue'] ?? 0, 0) . ' RON', 'c' => 'var(--warn)'],
            ['l' => 'Avg Occupancy', 'v' => ($kpis['avg_occupancy'] ?? 0) . '%', 'c' => 'var(--accent)'],
            ['l' => 'Avg Rev/Event', 'v' => number_format($kpis['avg_revenue_per_event'] ?? 0, 0) . ' RON', 'c' => 'var(--primary)'],
            ['l' => 'Avg Ticket Price', 'v' => number_format($kpis['avg_ticket_price'] ?? 0, 0) . ' RON', 'c' => '#c084fc'],
        ] as $k)
            <div class="kpi"><div class="l">{{ $k['l'] }}</div><div class="v" style="color:{{ $k['c'] }}">{{ $k['v'] }}</div></div>
        @endforeach
    </div>

    {{-- VENUE HEALTH SCORE + MONTHLY MOMENTUM --}}
    @php $healthScore = $venueHealthScore ?? []; $momentum = $monthlyMomentum ?? []; @endphp
    @if(!empty($healthScore['components']) || !empty($momentum['metrics']))
    <div style="display:grid;grid-template-columns:1fr 5fr;gap:14px;margin-bottom:16px;">
        @if(!empty($healthScore['components']))
        <div class="card" style="min-width:200px;"><div class="card-b" style="text-align:center;">
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Venue Health Score</div>
            <div style="position:relative;width:110px;height:110px;margin:0 auto;">
                <svg viewBox="0 0 36 36" style="width:110px;height:110px;transform:rotate(-90deg);">
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(122,162,255,.1)" stroke-width="3"/>
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="{{ $healthScore['color'] }}" stroke-width="3" stroke-dasharray="{{ $healthScore['score'] }}, 100" stroke-linecap="round"/>
                </svg>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <span style="font-size:28px;font-weight:800;color:{{ $healthScore['color'] }};">{{ $healthScore['score'] }}</span>
                    <span style="font-size:10px;color:var(--muted);">/ 100</span>
                </div>
            </div>
            <div style="font-size:13px;font-weight:700;color:{{ $healthScore['color'] }};margin-top:6px;">{{ $healthScore['label'] }}</div>
            <div style="margin-top:10px;text-align:left;">
                @foreach($healthScore['components'] as $comp)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:11px;">
                        <span style="color:var(--muted);">{{ $comp['name'] }}</span>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:50px;height:4px;background:rgba(122,162,255,.1);border-radius:2px;overflow:hidden;"><div style="height:100%;width:{{ round($comp['score'] / $comp['max'] * 100) }}%;background:{{ $healthScore['color'] }};border-radius:2px;"></div></div>
                            <span style="color:var(--text);font-weight:600;width:30px;text-align:right;">{{ $comp['score'] }}/{{ $comp['max'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div></div>
        @endif

        @if(!empty($momentum['metrics']))
        <div class="card"><div class="card-h">Monthly Momentum — {{ $momentum['current_label'] ?? '' }} vs {{ $momentum['previous_label'] ?? '' }}</div><div class="card-b">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
                @foreach($momentum['metrics'] as $mm)
                    @php
                        $arrow = match($mm['trend']['direction']) { 'up' => '↑', 'down' => '↓', default => '→' };
                        $tColor = match($mm['trend']['direction']) { 'up' => 'var(--success)', 'down' => 'var(--danger)', default => 'var(--muted)' };
                        $fmt = ($mm['format'] ?? '') === 'currency' ? number_format($mm['current']) . ' RON' : (($mm['format'] ?? '') === 'pct' ? $mm['current'] . '%' : $mm['current']);
                        $fmtPrev = ($mm['format'] ?? '') === 'currency' ? number_format($mm['previous']) . ' RON' : (($mm['format'] ?? '') === 'pct' ? $mm['previous'] . '%' : $mm['previous']);
                    @endphp
                    <div class="kpi">
                        <div class="l">{{ $mm['name'] }}</div>
                        <div class="v" style="display:flex;align-items:center;gap:8px;">
                            <span>{{ $fmt }}</span>
                            <span style="font-size:14px;color:{{ $tColor }};">{{ $arrow }} {{ $mm['trend']['pct'] >= 0 ? '+' : '' }}{{ $mm['trend']['pct'] }}%</span>
                        </div>
                        <div style="font-size:10px;color:var(--muted);margin-top:2px;">prev: {{ $fmtPrev }}</div>
                    </div>
                @endforeach
            </div>
        </div></div>
        @endif
    </div>
    @endif

    {{-- TABS --}}
    @php
        $tabList = ['overview' => 'Overview', 'financial' => 'Financial', 'audience' => 'Audience', 'artists' => 'Artists', 'scheduling' => 'Scheduling', 'opportunities' => 'Opportunities', 'promotion' => 'Promotion', 'upcoming' => 'Upcoming', 'actions' => 'Actions'];
    @endphp
    <div class="tabs">
        @foreach($tabList as $key => $label)
            <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'active' : ''" class="tab">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ═══ TAB: OVERVIEW ═══ --}}
    <div x-show="tab === 'overview'">

        {{-- Charts --}}
        @if(!empty($months))
        <div class="g2" style="margin-bottom:14px;">
            <div class="card"><div class="card-h">Events & Tickets per Month</div><div class="card-b"><canvas id="venueEvTxChart" height="200"></canvas></div></div>
            <div class="card"><div class="card-h">Revenue & Occupancy per Month</div><div class="card-b"><canvas id="venueRevOccChart" height="200"></canvas></div></div>
        </div>
        @endif

        {{-- Event Performance Table --}}
        @php $evPerf = $eventPerformance ?? []; @endphp
        @if(!empty($evPerf))
        <div class="card" style="margin-bottom:14px;">
            <div class="card-h">Event Performance ({{ count($evPerf) }} events)</div>
            <div style="overflow-x:auto;">
                <table class="tbl">
                    <thead><tr>
                        <th>Date</th><th>Event</th><th>Artists</th><th style="text-align:right">Sold</th><th style="text-align:right">Capacity</th><th style="text-align:right">Sell-Through</th><th style="text-align:right">Revenue</th><th style="text-align:right">Check-in</th>
                    </tr></thead>
                    <tbody>
                    @foreach(array_slice($evPerf, 0, 25) as $ev)
                        @php $stColor = ($ev['sell_through'] ?? 0) >= 75 ? 'var(--success)' : (($ev['sell_through'] ?? 0) >= 50 ? 'var(--warn)' : 'var(--danger)'); @endphp
                        <tr style="{{ ($ev['is_past'] ?? false) ? 'opacity:0.6' : '' }}">
                            <td style="white-space:nowrap">{{ $ev['date'] ? \Carbon\Carbon::parse($ev['date'])->format('d M Y') : '—' }}</td>
                            <td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ \Illuminate\Support\Str::limit($ev['title'], 40) }}</td>
                            <td style="color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ \Illuminate\Support\Str::limit($ev['artists'], 35) }}</td>
                            <td style="text-align:right;font-family:monospace">{{ number_format($ev['sold']) }}</td>
                            <td style="text-align:right;font-family:monospace;color:var(--muted)">{{ $ev['capacity'] ?: '—' }}</td>
                            <td style="text-align:right;font-weight:700;color:{{ $stColor }}">{{ $ev['sell_through'] !== null ? $ev['sell_through'] . '%' : '—' }}</td>
                            <td style="text-align:right;font-family:monospace;color:var(--warn)">{{ number_format($ev['revenue']) }}</td>
                            <td style="text-align:right;color:var(--muted)">{{ $ev['checkin_rate'] !== null ? $ev['checkin_rate'] . '%' : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Revenue by Day Type + YoY --}}
        @php $rb = $revenueBreakdown ?? []; $dayType = $rb['revenue_by_day_type'] ?? []; $yoy = $rb['yoy'] ?? []; @endphp
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($dayType))
            <div class="card"><div class="card-h">Weekend vs Weekday</div><div class="card-b">
                @foreach($dayType as $dt)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed rgba(122,162,255,.08);">
                        <span style="font-weight:600;">{{ $dt['day_type'] }}</span>
                        <span style="color:var(--muted);">{{ $dt['events'] }} events · {{ $dt['avg_st'] }}% ST · {{ number_format($dt['avg_revenue']) }} RON avg</span>
                    </div>
                @endforeach
            </div></div>
            @endif
            @if(!empty($yoy) && ($yoy['last_12'] ?? 0) > 0)
            <div class="card"><div class="card-h">Year over Year</div><div class="card-b">
                <div style="display:flex;gap:20px;align-items:center;">
                    <div><div style="color:var(--muted);font-size:11px;">Last 12 months</div><div style="font-size:22px;font-weight:700;color:var(--warn);">{{ number_format($yoy['last_12']) }} RON</div></div>
                    <div><div style="color:var(--muted);font-size:11px;">Previous 12 months</div><div style="font-size:22px;font-weight:700;color:var(--muted);">{{ number_format($yoy['prev_12']) }} RON</div></div>
                    @if($yoy['change_pct'] !== null)
                        <div style="font-size:18px;font-weight:700;color:{{ $yoy['change_pct'] >= 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $yoy['change_pct'] >= 0 ? '+' : '' }}{{ $yoy['change_pct'] }}%</div>
                    @endif
                </div>
            </div></div>
            @endif
        </div>

        {{-- Competitor Benchmark + Revenue per Seat --}}
        @php $bench = $competitorBenchmark ?? []; $rps = $revenuePerSeat ?? []; @endphp
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($bench) && $bench['city_avg'])
            <div class="card"><div class="card-h">Competitor Benchmark — {{ $venue->city ?? '' }}</div><div class="card-b">
                <div style="display:flex;gap:20px;margin-bottom:12px;">
                    <div style="text-align:center;"><div style="color:var(--muted);font-size:11px;">Your Avg ST</div><div style="font-size:24px;font-weight:700;color:var(--accent);">{{ $bench['my']['avg_st'] }}%</div></div>
                    <div style="text-align:center;"><div style="color:var(--muted);font-size:11px;">City Avg ST</div><div style="font-size:24px;font-weight:700;color:var(--muted);">{{ $bench['city_avg']['avg_st'] }}%</div></div>
                    @if($bench['vs_city'] !== null)
                    <div style="text-align:center;"><div style="color:var(--muted);font-size:11px;">Difference</div><div style="font-size:24px;font-weight:700;color:{{ $bench['vs_city'] >= 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $bench['vs_city'] >= 0 ? '+' : '' }}{{ $bench['vs_city'] }}%</div></div>
                    @endif
                </div>
                @if(!empty($bench['competitors']))
                    <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;">Other venues in city</div>
                    @foreach(array_slice($bench['competitors'], 0, 5) as $comp)
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed rgba(122,162,255,.08);font-size:12px;">
                            <span>{{ $comp['name'] }} <span style="color:var(--muted);">({{ number_format($comp['capacity']) }} cap)</span></span>
                            <span>{{ $comp['events'] }} ev · <span style="color:{{ $comp['avg_st'] > $bench['my']['avg_st'] ? 'var(--danger)' : 'var(--success)' }};font-weight:600;">{{ $comp['avg_st'] }}% ST</span> · {{ $comp['avg_price'] }} RON</span>
                        </div>
                    @endforeach
                @endif
            </div></div>
            @endif

            @if(!empty($rps) && ($rps['avg_rev_per_seat'] ?? 0) > 0)
            <div class="card"><div class="card-h">Revenue per Seat</div><div class="card-b">
                <div style="display:flex;gap:20px;margin-bottom:12px;">
                    <div><div style="color:var(--muted);font-size:11px;">Avg Rev / Seat / Event</div><div style="font-size:24px;font-weight:700;color:var(--warn);">{{ number_format($rps['avg_rev_per_seat'], 0) }} RON</div></div>
                    <div><div style="color:var(--muted);font-size:11px;">Venue Capacity</div><div style="font-size:24px;font-weight:700;">{{ number_format($rps['capacity']) }}</div></div>
                </div>
                @if($rps['best_event'])
                    <div style="padding:8px 12px;border-radius:8px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.15);font-size:12px;">
                        Best: <strong>{{ $rps['best_event']['title'] }}</strong> — {{ number_format($rps['best_event']['rev_per_seat'], 0) }} RON/seat ({{ number_format($rps['best_event']['revenue']) }} RON total)
                    </div>
                @endif
            </div></div>
            @endif
        </div>

        {{-- Event Comparison Tool --}}
        @if(!empty($evPerf) && count($evPerf) >= 2)
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Event Comparison — Side by Side</div><div class="card-b">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;">
                <div><label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Event A</label>
                    <select x-model="cmpA" style="background:var(--card);border:1px solid var(--ring);border-radius:8px;color:var(--text);padding:8px 10px;font-size:12px;max-width:280px;">
                        <option value="">Select event...</option>
                        @foreach(array_slice($evPerf, 0, 30) as $ev)<option value="{{ $ev['id'] }}">{{ \Illuminate\Support\Str::limit($ev['title'], 35) }} ({{ $ev['date'] ? \Carbon\Carbon::parse($ev['date'])->format('d M y') : '' }})</option>@endforeach
                    </select></div>
                <div><label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Event B</label>
                    <select x-model="cmpB" style="background:var(--card);border:1px solid var(--ring);border-radius:8px;color:var(--text);padding:8px 10px;font-size:12px;max-width:280px;">
                        <option value="">Select event...</option>
                        @foreach(array_slice($evPerf, 0, 30) as $ev)<option value="{{ $ev['id'] }}">{{ \Illuminate\Support\Str::limit($ev['title'], 35) }} ({{ $ev['date'] ? \Carbon\Carbon::parse($ev['date'])->format('d M y') : '' }})</option>@endforeach
                    </select></div>
                <button @click="runComparison()" style="padding:8px 18px;border-radius:8px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.2);color:var(--accent);font-weight:600;font-size:12px;cursor:pointer;" :disabled="cmpLoading || !cmpA || !cmpB">Compare</button>
            </div>
            <template x-if="cmpResult && !cmpResult.error">
                <div>
                    <table class="tbl"><thead><tr><th>Metric</th><th style="text-align:right" x-text="cmpResult.event_a.title"></th><th style="text-align:right" x-text="cmpResult.event_b.title"></th></tr></thead>
                    <tbody>
                        <tr><td style="font-weight:600;">Date</td><td style="text-align:right" x-text="cmpResult.event_a.date"></td><td style="text-align:right" x-text="cmpResult.event_b.date"></td></tr>
                        <tr><td style="font-weight:600;">Artists</td><td style="text-align:right;color:var(--muted);font-size:12px;" x-text="cmpResult.event_a.artists"></td><td style="text-align:right;color:var(--muted);font-size:12px;" x-text="cmpResult.event_b.artists"></td></tr>
                        <tr><td style="font-weight:600;">Sold / Cap</td><td style="text-align:right;font-family:monospace;" x-text="cmpResult.event_a.sold + ' / ' + cmpResult.event_a.capacity"></td><td style="text-align:right;font-family:monospace;" x-text="cmpResult.event_b.sold + ' / ' + cmpResult.event_b.capacity"></td></tr>
                        <tr><td style="font-weight:600;">Sell-Through</td><td style="text-align:right;font-weight:700;" :style="'color:' + ((cmpResult.event_a.sell_through||0) >= (cmpResult.event_b.sell_through||0) ? 'var(--success)' : 'var(--danger)')" x-text="(cmpResult.event_a.sell_through ?? '—') + '%'"></td><td style="text-align:right;font-weight:700;" :style="'color:' + ((cmpResult.event_b.sell_through||0) >= (cmpResult.event_a.sell_through||0) ? 'var(--success)' : 'var(--danger)')" x-text="(cmpResult.event_b.sell_through ?? '—') + '%'"></td></tr>
                        <tr><td style="font-weight:600;">Revenue</td><td style="text-align:right;color:var(--warn);font-family:monospace;" x-text="Number(cmpResult.event_a.revenue).toLocaleString() + ' RON'"></td><td style="text-align:right;color:var(--warn);font-family:monospace;" x-text="Number(cmpResult.event_b.revenue).toLocaleString() + ' RON'"></td></tr>
                        <tr><td style="font-weight:600;">Avg Ticket Price</td><td style="text-align:right;font-family:monospace;" x-text="cmpResult.event_a.avg_price + ' RON'"></td><td style="text-align:right;font-family:monospace;" x-text="cmpResult.event_b.avg_price + ' RON'"></td></tr>
                        <tr><td style="font-weight:600;">Avg Lead Time</td><td style="text-align:right;" x-text="cmpResult.event_a.avg_lead_days + 'd'"></td><td style="text-align:right;" x-text="cmpResult.event_b.avg_lead_days + 'd'"></td></tr>
                        <tr><td style="font-weight:600;">Check-in Rate</td><td style="text-align:right;" x-text="(cmpResult.event_a.checkin_rate ?? '—') + '%'"></td><td style="text-align:right;" x-text="(cmpResult.event_b.checkin_rate ?? '—') + '%'"></td></tr>
                    </tbody></table>
                </div>
            </template>
        </div></div>
        @endif
    </div>

    {{-- ═══ TAB: FINANCIAL ═══ --}}
    <div x-show="tab === 'financial'" x-cloak>
        @php $rb = $revenueBreakdown ?? []; @endphp

        {{-- Top Artists by Revenue --}}
        @if(!empty($rb['top_artists_by_revenue']))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Top Artists by Revenue</div>
            <table class="tbl"><thead><tr><th>Artist</th><th style="text-align:right">Events</th><th style="text-align:right">Revenue</th><th style="text-align:right">Avg ST</th></tr></thead>
            <tbody>
            @foreach($rb['top_artists_by_revenue'] as $a)
                <tr><td style="font-weight:600">{{ $a['name'] }}</td><td style="text-align:right">{{ $a['events'] }}</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($a['total_revenue']) }}</td><td style="text-align:right">{{ $a['avg_st'] }}%</td></tr>
            @endforeach
            </tbody></table>
        </div>
        @endif

        {{-- Revenue by Genre + Channel --}}
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($rb['revenue_by_genre']))
            <div class="card"><div class="card-h">Revenue by Genre</div><div class="card-b">
                @foreach($rb['revenue_by_genre'] as $g)
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed rgba(122,162,255,.08);">
                        <span>{{ $g['genre'] }} <span style="color:var(--muted);font-size:11px;">({{ $g['events'] }} events)</span></span>
                        <span style="color:var(--warn);font-family:monospace;font-weight:600;">{{ number_format($g['revenue']) }} RON</span>
                    </div>
                @endforeach
            </div></div>
            @endif
            @if(!empty($rb['revenue_by_channel']))
            <div class="card"><div class="card-h">Revenue by Channel</div><div class="card-b">
                @foreach($rb['revenue_by_channel'] as $ch)
                    @php $c = is_object($ch) ? $ch : (object)$ch; @endphp
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed rgba(122,162,255,.08);">
                        <span>{{ $c->source ?? 'unknown' }} <span style="color:var(--muted);font-size:11px;">({{ $c->orders ?? 0 }} orders)</span></span>
                        <span style="color:var(--warn);font-family:monospace;font-weight:600;">{{ number_format($c->revenue ?? 0) }} RON</span>
                    </div>
                @endforeach
            </div></div>
            @endif
        </div>

        {{-- Pricing Intelligence --}}
        @php $pi = $pricingIntelligence ?? []; @endphp
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($pi['price_buckets']))
            <div class="card"><div class="card-h">Price Sensitivity @if($pi['sweet_spot']) <span style="color:var(--success);font-size:11px;margin-left:8px;">Sweet spot: {{ $pi['sweet_spot'] }} RON</span> @endif</div><div class="card-b">
                @foreach($pi['price_buckets'] as $pb)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">
                        <span>{{ $pb['range'] }} RON</span>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress" style="width:100px;"><div class="progress-fill" style="width:{{ min($pb['sell_through'], 100) }}%;background:var(--success);"></div></div>
                            <span style="font-family:monospace;color:var(--muted);width:45px;text-align:right;">{{ $pb['sell_through'] }}%</span>
                        </div>
                    </div>
                @endforeach
            </div></div>
            @endif
            <div>
                @if(!empty($pi['underpriced']))
                <div class="card" style="margin-bottom:10px;"><div class="card-h" style="color:var(--warn);">Under-Priced Events (>90% ST)</div><div class="card-b">
                    @foreach($pi['underpriced'] as $up)
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;">
                            <span>{{ \Illuminate\Support\Str::limit($up['title'], 30) }}</span>
                            <span style="color:var(--success);font-weight:600;">{{ $up['sell_through'] }}% · {{ $up['avg_price'] }} RON</span>
                        </div>
                    @endforeach
                </div></div>
                @endif
                @if(!empty($pi['overpriced']))
                <div class="card"><div class="card-h" style="color:var(--danger);">Over-Priced Events (<30% ST)</div><div class="card-b">
                    @foreach($pi['overpriced'] as $op)
                        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;">
                            <span>{{ \Illuminate\Support\Str::limit($op['title'], 30) }}</span>
                            <span style="color:var(--danger);font-weight:600;">{{ $op['sell_through'] }}% · {{ $op['avg_price'] }} RON</span>
                        </div>
                    @endforeach
                </div></div>
                @endif
            </div>
        </div>

        {{-- Revenue Forecast --}}
        @php $rf = $revenueForecast ?? []; @endphp
        @if(!empty($rf['forecast']))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Revenue Forecast (6 months) @if($rf['yoy_change_pct'] !== null) <span style="color:{{ $rf['yoy_change_pct'] >= 0 ? 'var(--success)' : 'var(--danger)' }};font-size:11px;margin-left:8px;">YoY: {{ $rf['yoy_change_pct'] >= 0 ? '+' : '' }}{{ $rf['yoy_change_pct'] }}%</span> @endif</div>
            <table class="tbl"><thead><tr><th>Month</th><th style="text-align:right">Pessimistic</th><th style="text-align:right">Realistic</th><th style="text-align:right">Optimistic</th></tr></thead>
            <tbody>
            @foreach($rf['forecast'] as $f)
                <tr><td>{{ $f['month'] }}</td><td style="text-align:right;color:var(--muted);font-family:monospace">{{ number_format($f['pessimistic']) }}</td><td style="text-align:right;color:var(--warn);font-weight:600;font-family:monospace">{{ number_format($f['realistic']) }}</td><td style="text-align:right;color:var(--success);font-family:monospace">{{ number_format($f['optimistic']) }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
        @endif

        {{-- Refund Analysis --}}
        @php $refunds = $refundAnalysis ?? []; @endphp
        @if(($refunds['total_refunds'] ?? 0) > 0)
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Refund & Cancellation Analysis</div><div class="card-b">
            <div style="display:flex;gap:20px;margin-bottom:14px;">
                <div><div style="color:var(--muted);font-size:11px;">Total Orders</div><div style="font-size:20px;font-weight:700;">{{ number_format($refunds['total_orders']) }}</div></div>
                <div><div style="color:var(--muted);font-size:11px;">Refunds</div><div style="font-size:20px;font-weight:700;color:var(--danger);">{{ number_format($refunds['total_refunds']) }}</div></div>
                <div><div style="color:var(--muted);font-size:11px;">Refund Rate</div><div style="font-size:20px;font-weight:700;color:{{ $refunds['refund_rate'] > 5 ? 'var(--danger)' : ($refunds['refund_rate'] > 2 ? 'var(--warn)' : 'var(--success)') }};">{{ $refunds['refund_rate'] }}%</div></div>
                <div><div style="color:var(--muted);font-size:11px;">Revenue Lost</div><div style="font-size:20px;font-weight:700;color:var(--danger);">{{ number_format($refunds['refund_revenue_lost']) }} RON</div></div>
            </div>
            @if(!empty($refunds['by_event']))
                <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;">Top Refunded Events</div>
                <table class="tbl"><thead><tr><th>Event</th><th style="text-align:right">Orders</th><th style="text-align:right">Refunds</th><th style="text-align:right">Rate</th><th style="text-align:right">Lost Revenue</th></tr></thead>
                <tbody>
                @foreach(array_slice($refunds['by_event'], 0, 8) as $re)
                    <tr><td style="font-weight:600">{{ \Illuminate\Support\Str::limit($re['title'], 35) }} <span style="color:var(--muted);font-size:11px;">{{ $re['date'] ? \Carbon\Carbon::parse($re['date'])->format('d M y') : '' }}</span></td><td style="text-align:right">{{ $re['total_orders'] }}</td><td style="text-align:right;color:var(--danger)">{{ $re['refunds'] }}</td><td style="text-align:right;font-weight:700;color:{{ $re['refund_rate'] > 10 ? 'var(--danger)' : 'var(--muted)' }}">{{ $re['refund_rate'] }}%</td><td style="text-align:right;font-family:monospace;color:var(--danger)">{{ number_format($re['lost_revenue']) }}</td></tr>
                @endforeach
                </tbody></table>
            @endif
            @if(!empty($refunds['monthly']))
                <div style="font-size:12px;font-weight:600;color:var(--muted);margin:12px 0 6px;">Monthly Refund Trend</div>
                <div style="display:flex;align-items:flex-end;gap:3px;height:60px;">
                    @php $maxRefRate = collect($refunds['monthly'])->max('rate') ?: 1; @endphp
                    @foreach($refunds['monthly'] as $mr)
                        @php $barH = $mr['rate'] > 0 ? max(4, round($mr['rate'] / $maxRefRate * 50)) : 0; @endphp
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="{{ $mr['month'] }}: {{ $mr['refunds'] }}/{{ $mr['total'] }} ({{ $mr['rate'] }}%)">
                            <div style="width:100%;height:{{ $barH }}px;background:{{ $mr['rate'] > 5 ? 'var(--danger)' : ($mr['rate'] > 2 ? 'var(--warn)' : 'rgba(122,162,255,.3)') }};border-radius:3px 3px 0 0;"></div>
                            <span style="font-size:8px;color:var(--muted);">{{ substr($mr['month'], 5) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div></div>
        @endif
    </div>

    {{-- ═══ TAB: AUDIENCE ═══ --}}
    <div x-show="tab === 'audience'" x-cloak>
        @php $personas = $audiencePersonas['personas'] ?? []; $aTotals = $audiencePersonas['totals'] ?? []; $loyalty = $customerLoyalty ?? []; $geoOrig = $geographicOrigin ?? []; @endphp

        {{-- Personas --}}
        @if(!empty($personas))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Audience Personas ({{ $aTotals['total_customers'] ?? 0 }} buyers)</div><div class="card-b">
            <div class="g3">
            @foreach($personas as $p)
                <div style="padding:14px;border-radius:10px;background:rgba(122,162,255,.04);border:1px solid var(--ring);">
                    <div style="font-size:11px;font-weight:600;color:var(--accent);text-transform:uppercase;margin-bottom:6px;">{{ $p['label'] }}</div>
                    <div style="font-size:18px;font-weight:700;">{{ $p['age_group'] }} / {{ ucfirst($p['gender']) }}</div>
                    <div style="color:var(--muted);font-size:12px;margin-top:4px;">{{ $p['count'] }} buyers ({{ $p['percentage'] }}%)</div>
                    <div style="color:var(--muted);font-size:12px;">Avg spend: {{ number_format($p['avg_spend'], 0) }} RON</div>
                    @if(!empty($p['top_cities']))
                        <div style="color:var(--muted);font-size:11px;margin-top:4px;">Cities: {{ implode(', ', array_keys($p['top_cities'])) }}</div>
                    @endif
                </div>
            @endforeach
            </div>
        </div></div>
        @endif

        {{-- Age/Gender Distribution --}}
        @php $ageDist = $aTotals['age_distribution'] ?? []; $genderDist = $aTotals['gender_overall'] ?? []; @endphp
        @if(!empty($ageDist) || !empty($genderDist))
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($ageDist))
            <div class="card"><div class="card-h">Age Distribution</div><div class="card-b">
                @php $maxAge = max(1, max($ageDist)); @endphp
                @foreach($ageDist as $ageGroup => $count)
                    <div style="display:flex;align-items:center;gap:10px;padding:5px 0;">
                        <span style="width:50px;font-size:12px;font-weight:600;">{{ $ageGroup }}</span>
                        <div class="progress" style="flex:1;height:18px;border-radius:6px;">
                            <div class="progress-fill" style="width:{{ round($count / $maxAge * 100) }}%;background:linear-gradient(90deg,rgba(99,102,241,0.6),rgba(34,211,238,0.6));border-radius:6px;display:flex;align-items:center;padding-left:6px;">
                                <span style="font-size:10px;font-weight:600;color:white;">{{ number_format($count) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div></div>
            @endif
            @if(!empty($genderDist))
            <div class="card"><div class="card-h">Gender Distribution</div><div class="card-b">
                @php $totalGender = max(1, array_sum($genderDist)); @endphp
                <div style="display:flex;gap:16px;align-items:center;justify-content:center;padding:10px 0;">
                    @foreach($genderDist as $gender => $count)
                        @php $pct = round($count / $totalGender * 100, 1); $color = match(strtolower($gender)) { 'male' => 'var(--primary)', 'female' => '#c084fc', default => 'var(--muted)' }; @endphp
                        <div style="text-align:center;">
                            <div style="font-size:36px;font-weight:700;color:{{ $color }};">{{ $pct }}%</div>
                            <div style="font-size:12px;color:var(--muted);margin-top:4px;">{{ ucfirst($gender) }}</div>
                            <div style="font-size:11px;color:var(--muted);">{{ number_format($count) }} buyers</div>
                        </div>
                    @endforeach
                </div>
                {{-- Visual bar --}}
                <div style="display:flex;height:12px;border-radius:6px;overflow:hidden;margin-top:8px;">
                    @foreach($genderDist as $gender => $count)
                        @php $pct = round($count / $totalGender * 100, 1); $color = match(strtolower($gender)) { 'male' => 'var(--primary)', 'female' => '#c084fc', default => 'var(--muted)' }; @endphp
                        <div style="width:{{ $pct }}%;background:{{ $color }};"></div>
                    @endforeach
                </div>
            </div></div>
            @endif
        </div>
        @endif

        {{-- Loyalty + Geographic --}}
        <div class="g2" style="margin-bottom:14px;">
            @if(($loyalty['total'] ?? 0) > 0)
            <div class="card"><div class="card-h">Customer Loyalty</div><div class="card-b">
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;text-align:center;">
                    @foreach([['v' => $loyalty['total'], 'l' => 'Total', 'c' => 'var(--text)'], ['v' => $loyalty['one_time'], 'l' => 'One-time', 'c' => 'var(--muted)'], ['v' => $loyalty['repeat'], 'l' => 'Repeat', 'c' => 'var(--primary)'], ['v' => $loyalty['regulars'], 'l' => 'Regulars', 'c' => 'var(--accent)'], ['v' => $loyalty['superfan'], 'l' => 'Superfan', 'c' => 'var(--warn)']] as $lk)
                        <div><div style="font-size:20px;font-weight:700;color:{{ $lk['c'] }}">{{ $lk['v'] }}</div><div style="font-size:10px;color:var(--muted);">{{ $lk['l'] }}</div></div>
                    @endforeach
                </div>
                <div style="text-align:center;margin-top:10px;font-size:13px;"><span style="color:var(--success);font-weight:700;">{{ $loyalty['repeat_rate'] }}%</span> <span style="color:var(--muted);">repeat rate</span></div>
            </div></div>
            @endif

            @if(!empty($geoOrig['cities']))
            <div class="card"><div class="card-h">Where Customers Come From <span style="color:var(--accent);font-size:11px;margin-left:8px;">{{ $geoOrig['out_of_town_ratio'] }}% out-of-town</span></div>
                <div style="max-height:300px;overflow-y:auto;">
                <table class="tbl"><thead><tr><th>City</th><th style="text-align:right">Customers</th><th style="text-align:right">Revenue</th><th style="text-align:right">Avg Spend</th></tr></thead>
                <tbody>
                @foreach(array_slice($geoOrig['cities'], 0, 15) as $c)
                    <tr><td style="font-weight:600">{{ $c['city'] }}</td><td style="text-align:right">{{ $c['customer_count'] }}</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($c['total_revenue']) }}</td><td style="text-align:right;color:var(--muted);font-family:monospace">{{ number_format($c['avg_spend'], 0) }}</td></tr>
                @endforeach
                </tbody></table>
                </div>
            </div>
            @endif
        </div>

        {{-- Superfan Details --}}
        @if(!empty($loyalty['superfan_details']))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Top Superfans</div>
            <table class="tbl"><thead><tr><th>Name</th><th>Email</th><th>City</th><th style="text-align:right">Events</th><th style="text-align:right">Total Spent</th></tr></thead>
            <tbody>
            @foreach(array_slice($loyalty['superfan_details'], 0, 15) as $sf)
                <tr><td style="font-weight:600">{{ $sf['name'] }}</td><td style="color:var(--muted)">{{ $sf['email'] }}</td><td>{{ $sf['city'] }}</td><td style="text-align:right">{{ $sf['events'] }}</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($sf['total_spent'], 0) }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
        @endif

        {{-- Genre Loyalty --}}
        @php $gLoyalty = $genreLoyalty ?? []; @endphp
        @if(!empty($gLoyalty))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Recurring Customers by Genre — Which genres build loyalty?</div>
            <table class="tbl"><thead><tr><th>Genre</th><th style="text-align:right">Total Buyers</th><th style="text-align:right">Repeat Buyers</th><th style="text-align:right">Repeat Rate</th><th style="text-align:right">Avg Events/Buyer</th></tr></thead>
            <tbody>
            @foreach($gLoyalty as $gl)
                <tr><td style="font-weight:600">{{ $gl['genre'] }}</td><td style="text-align:right">{{ number_format($gl['total_buyers']) }}</td><td style="text-align:right;color:var(--accent)">{{ number_format($gl['repeat_buyers']) }}</td><td style="text-align:right;font-weight:700;color:{{ $gl['repeat_rate'] >= 20 ? 'var(--success)' : ($gl['repeat_rate'] >= 10 ? 'var(--warn)' : 'var(--muted)') }}">{{ $gl['repeat_rate'] }}%</td><td style="text-align:right;font-family:monospace">{{ $gl['avg_events_per_buyer'] }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
        @endif
    </div>

    {{-- ═══ TAB: ARTISTS ═══ --}}
    <div x-show="tab === 'artists'" x-cloak>
        @php $artPerf = $artistPerformance ?? []; $genPerf = $genrePerformance ?? []; $npArtists = $neverPlayed ?? []; @endphp

        {{-- Artist Performance --}}
        @if(!empty($artPerf))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Artist Performance at Venue ({{ count($artPerf) }} artists)</div>
            <div style="overflow-x:auto;">
            <table class="tbl"><thead><tr><th>Artist</th><th style="text-align:right">Events</th><th style="text-align:right">Tickets</th><th style="text-align:right">Avg ST</th><th style="text-align:right">Best ST</th><th style="text-align:right">Avg Revenue</th></tr></thead>
            <tbody>
            @foreach(array_slice($artPerf, 0, 20) as $ap)
                <tr><td style="font-weight:600">{{ $ap['artist_name'] }}</td><td style="text-align:right">{{ $ap['events_count'] }}</td><td style="text-align:right;font-family:monospace">{{ number_format($ap['total_tickets']) }}</td><td style="text-align:right;color:{{ $ap['avg_sell_through'] >= 75 ? 'var(--success)' : ($ap['avg_sell_through'] >= 50 ? 'var(--warn)' : 'var(--danger)') }};font-weight:700">{{ $ap['avg_sell_through'] }}%</td><td style="text-align:right;color:var(--muted)">{{ $ap['best_sell_through'] }}%</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($ap['avg_revenue']) }}</td></tr>
            @endforeach
            </tbody></table>
            </div>
        </div>
        @endif

        {{-- Genre Performance --}}
        @if(!empty($genPerf))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Genre Performance</div>
            <table class="tbl"><thead><tr><th>Genre</th><th style="text-align:right">Events</th><th style="text-align:right">Avg ST</th><th style="text-align:right">Avg Revenue</th><th style="text-align:right">Avg Price</th><th style="text-align:right">Tickets</th></tr></thead>
            <tbody>
            @foreach($genPerf as $gp)
                <tr><td style="font-weight:600">{{ $gp['genre'] }}</td><td style="text-align:right">{{ $gp['events_count'] }}</td><td style="text-align:right;color:{{ $gp['avg_sell_through'] >= 70 ? 'var(--success)' : 'var(--muted)' }};font-weight:700">{{ $gp['avg_sell_through'] }}%</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($gp['avg_revenue']) }}</td><td style="text-align:right;color:var(--muted);font-family:monospace">{{ $gp['avg_ticket_price'] }}</td><td style="text-align:right;font-family:monospace">{{ number_format($gp['total_tickets']) }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
        @endif

        {{-- Never Played Artists --}}
        @if(!empty($npArtists))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Artists to Consider Booking</div>
            <table class="tbl"><thead><tr><th>Artist</th><th style="text-align:right">Events in City</th><th style="text-align:right">Avg ST</th><th style="text-align:right">Est. Draw</th></tr></thead>
            <tbody>
            @foreach(array_slice($npArtists, 0, 15) as $np)
                <tr><td style="font-weight:600">{{ $np['artist_name'] }}</td><td style="text-align:right">{{ $np['city_events'] }}</td><td style="text-align:right;color:var(--success);font-weight:700">{{ $np['avg_sell_through'] }}%</td><td style="text-align:right;font-family:monospace">{{ number_format($np['estimated_draw']) }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
        @endif
    </div>

    {{-- ═══ TAB: SCHEDULING ═══ --}}
    <div x-show="tab === 'scheduling'" x-cloak>
        @php $heatmap = $schedulingHeatmap ?? []; $dow = $dayOfWeek ?? []; $season = $seasonality ?? []; $idle = $idleDays ?? []; $si = $salesIntelligence ?? []; $optFreq = $si['optimal_frequency'] ?? []; @endphp

        {{-- Heatmap --}}
        @if(!empty($heatmap['matrix']))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Performance Heatmap (Day × Month)</div><div class="card-b">
            <div style="overflow-x:auto;">
            <table style="width:100%;font-size:12px;border-collapse:collapse;">
                <thead><tr><th style="padding:6px;color:var(--muted);"></th>
                    @foreach($heatmap['months'] as $m)<th style="padding:6px;color:var(--muted);text-align:center;font-weight:600;">{{ $m }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach($heatmap['days'] as $di => $dayName)
                    <tr><td style="padding:6px;font-weight:600;color:var(--muted);">{{ $dayName }}</td>
                    @for($mi = 0; $mi < 12; $mi++)
                        @php $cell = $heatmap['matrix'][$di][$mi] ?? null; $st = $cell ? $cell['st'] : null; $bg = $st === null ? 'transparent' : ($st >= 75 ? 'rgba(34,197,94,0.3)' : ($st >= 50 ? 'rgba(251,191,36,0.2)' : ($st > 0 ? 'rgba(239,68,68,0.15)' : 'transparent'))); @endphp
                        <td style="padding:6px;text-align:center;background:{{ $bg }};border-radius:4px;color:{{ $st !== null ? 'var(--text)' : 'var(--muted)' }};font-weight:{{ $st !== null ? '600' : '400' }}">{{ $st !== null ? $st . '%' : '·' }}</td>
                    @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div></div>
        @endif

        {{-- Day of Week + Seasonality --}}
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($dow))
            <div class="card"><div class="card-h">Day of Week Analysis</div>
                <table class="tbl"><thead><tr><th>Day</th><th style="text-align:right">Events</th><th style="text-align:right">Avg ST</th><th style="text-align:right">Avg Revenue</th></tr></thead>
                <tbody>
                @foreach($dow as $d)
                    <tr><td style="font-weight:600">{{ $d['day'] }}</td><td style="text-align:right">{{ $d['events'] }}</td><td style="text-align:right;color:{{ $d['avg_sell_through'] >= 70 ? 'var(--success)' : 'var(--muted)' }};font-weight:700">{{ $d['avg_sell_through'] }}%</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($d['avg_revenue']) }}</td></tr>
                @endforeach
                </tbody></table>
            </div>
            @endif

            @if(!empty($season))
            <div class="card"><div class="card-h">Seasonality (by Month)</div>
                <table class="tbl"><thead><tr><th>Month</th><th style="text-align:right">Events</th><th style="text-align:right">Avg ST</th><th style="text-align:right">Avg Revenue</th><th style="text-align:right">Idle Days</th></tr></thead>
                <tbody>
                @foreach($season as $s)
                    <tr><td style="font-weight:600">{{ $s['month'] }}</td><td style="text-align:right">{{ $s['events'] }}</td><td style="text-align:right;color:{{ $s['avg_sell_through'] >= 70 ? 'var(--success)' : 'var(--muted)' }};font-weight:700">{{ $s['avg_sell_through'] }}%</td><td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($s['avg_revenue']) }}</td><td style="text-align:right;color:{{ $s['idle_days'] > 20 ? 'var(--danger)' : 'var(--muted)' }}">{{ $s['idle_days'] }}</td></tr>
                @endforeach
                </tbody></table>
            </div>
            @endif
        </div>

        {{-- Idle Days + Frequency --}}
        <div class="g2" style="margin-bottom:14px;">
            @if(($idle['total_idle_weekend_days'] ?? 0) > 0)
            <div class="card"><div class="card-h">Idle Weekend Days (Last 12 Months)</div><div class="card-b">
                <div style="display:flex;gap:20px;align-items:center;margin-bottom:12px;">
                    <div><div style="color:var(--muted);font-size:11px;">Idle Fri/Sat/Sun</div><div style="font-size:28px;font-weight:700;color:var(--danger);">{{ $idle['total_idle_weekend_days'] }}</div></div>
                    <div><div style="color:var(--muted);font-size:11px;">Avg Rev/Event</div><div style="font-size:18px;font-weight:700;color:var(--warn);">{{ number_format($idle['avg_revenue_per_event']) }} RON</div></div>
                    <div><div style="color:var(--muted);font-size:11px;">Est. Lost Revenue</div><div style="font-size:18px;font-weight:700;color:var(--danger);">{{ number_format($idle['estimated_lost_revenue']) }} RON</div></div>
                </div>
            </div></div>
            @endif

            @if(!empty($optFreq))
            <div class="card"><div class="card-h">Optimal Event Frequency</div>
                <table class="tbl"><thead><tr><th>Frequency</th><th style="text-align:right">Weeks</th><th style="text-align:right">Avg ST</th></tr></thead>
                <tbody>
                @foreach($optFreq as $of)
                    <tr><td style="font-weight:600">{{ $of['frequency'] }}</td><td style="text-align:right">{{ $of['weeks'] }}</td><td style="text-align:right;font-weight:700;color:{{ $of['avg_sell_through'] >= 70 ? 'var(--success)' : 'var(--muted)' }}">{{ $of['avg_sell_through'] }}%</td></tr>
                @endforeach
                </tbody></table>
            </div>
            @endif
        </div>

        {{-- Sales Velocity + Purchase Timing --}}
        @php $timing = $si['purchase_timing'] ?? []; $velCurves = $si['velocity_curves'] ?? []; @endphp
        <div class="g2" style="margin-bottom:14px;">
            @if(!empty($timing))
            <div class="card"><div class="card-h">Purchase Timing <span style="color:var(--muted);font-size:11px;margin-left:8px;">(avg {{ $si['avg_lead_days'] ?? 0 }}d before event)</span></div><div class="card-b">
                @php $timingLabels = ['super_early' => '90+ days', 'early_bird' => '31-90 days', 'last_month' => '8-30 days', 'last_week' => '2-7 days', 'last_minute' => 'Last minute']; @endphp
                @foreach($timingLabels as $key => $label)
                    @if(($timing[$key] ?? 0) > 0)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">
                        <span>{{ $label }}</span>
                        <div style="display:flex;align-items:center;gap:8px;"><div class="progress" style="width:100px;"><div class="progress-fill" style="width:{{ min($timing[$key], 100) }}%;background:var(--primary);"></div></div><span style="font-family:monospace;color:var(--muted);width:45px;text-align:right;">{{ $timing[$key] }}%</span></div>
                    </div>
                    @endif
                @endforeach
            </div></div>
            @endif

            @if(!empty($velCurves))
            <div class="card"><div class="card-h">Sales Velocity (Last 5 Events)</div><div class="card-b" style="font-size:12px;">
                @foreach($velCurves as $vc)
                    <div style="margin-bottom:14px;padding-bottom:14px;{{ !$loop->last ? 'border-bottom:1px dashed var(--ring);' : '' }}">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <strong>{{ \Illuminate\Support\Str::limit($vc['event_name'], 35) }}</strong>
                            <span style="color:var(--muted);font-size:11px;">{{ $vc['total_tickets'] }} bilete</span>
                        </div>
                        <div style="display:flex;align-items:flex-end;gap:3px;height:40px;">
                        @foreach($vc['points'] as $pt)
                            @php $h = max(4, (int) round($pt['pct'] * 0.4)); @endphp
                            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;">
                                <span style="font-size:9px;color:var(--muted);">{{ $pt['pct'] }}%</span>
                                <div style="width:100%;height:{{ $h }}px;background:var(--primary);border-radius:3px;opacity:{{ 0.3 + ($pt['pct'] / 100 * 0.7) }};"></div>
                                <span style="font-size:9px;color:var(--muted);">{{ $pt['days'] }}z</span>
                            </div>
                        @endforeach
                        </div>
                    </div>
                @endforeach
            </div></div>
            @endif
        </div>

        {{-- Check-in Time Analysis --}}
        @php $ciAnalysis = $checkinAnalysis ?? []; @endphp
        @if(!empty($ciAnalysis) && ($ciAnalysis['total_checkins'] ?? 0) > 0)
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Check-in / Arrival Time Analysis ({{ number_format($ciAnalysis['total_checkins']) }} check-ins)</div><div class="card-b">
            <div style="display:flex;gap:20px;margin-bottom:14px;">
                <div><div style="color:var(--muted);font-size:11px;">Peak Hour</div><div style="font-size:22px;font-weight:700;color:var(--accent);">{{ $ciAnalysis['peak_hour'] }}</div></div>
                <div><div style="color:var(--muted);font-size:11px;">50% Arrived By</div><div style="font-size:22px;font-weight:700;">{{ $ciAnalysis['p50_arrival'] }}</div></div>
                <div><div style="color:var(--muted);font-size:11px;">80% Arrived By</div><div style="font-size:22px;font-weight:700;">{{ $ciAnalysis['p80_arrival'] }}</div></div>
            </div>
            <div style="display:flex;align-items:flex-end;gap:2px;height:80px;">
                @foreach($ciAnalysis['hourly'] as $h)
                    @php $maxPct = collect($ciAnalysis['hourly'])->max('pct') ?: 1; $barH = $h['pct'] > 0 ? max(4, round($h['pct'] / $maxPct * 70)) : 0; @endphp
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;" title="{{ $h['hour'] }}: {{ $h['count'] }} check-ins ({{ $h['pct'] }}%)">
                        <div style="width:100%;height:{{ $barH }}px;background:{{ $h['pct'] > 0 ? 'linear-gradient(180deg, var(--accent), rgba(34,211,238,0.3))' : 'transparent' }};border-radius:3px 3px 0 0;min-width:4px;"></div>
                        @if($h['pct'] > 5)<span style="font-size:8px;color:var(--muted);">{{ substr($h['hour'], 0, 2) }}</span>@endif
                    </div>
                @endforeach
            </div>
            <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--muted);margin-top:2px;">
                <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
            </div>
            <div style="margin-top:10px;padding:8px 12px;border-radius:8px;background:rgba(34,211,238,.04);border:1px solid rgba(34,211,238,.1);font-size:12px;color:var(--muted);">
                Tip: Open F&B service by <strong style="color:var(--text);">{{ $ciAnalysis['p50_arrival'] }}</strong>. Staff peak needed at <strong style="color:var(--accent);">{{ $ciAnalysis['peak_hour'] }}</strong>. Security can scale down after <strong style="color:var(--text);">{{ $ciAnalysis['p80_arrival'] }}</strong>.
            </div>
        </div></div>
        @endif
    </div>

    {{-- ═══ TAB: OPPORTUNITIES ═══ --}}
    <div x-show="tab === 'opportunities'" x-cloak>
        @php $opps = $opportunities ?? []; $recs = $opps['recommendations'] ?? []; @endphp
        @if(!empty($recs))
        {{-- Human-readable insights summary --}}
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Ce am descoperit</div><div class="card-b">
            <div style="font-size:14px;line-height:1.8;color:var(--text);">
                @foreach($recs as $rec)
                    <p style="margin-bottom:8px;">
                        <span style="font-weight:700;color:var(--accent);">{{ $rec['category'] }}:</span>
                        {{ $rec['title'] }}.
                        <span style="color:var(--muted);">{{ $rec['detail'] }}</span>
                    </p>
                @endforeach
            </div>
        </div></div>

        <div class="card" style="margin-bottom:14px;"><div class="card-h">Recomandari detaliate ({{ count($recs) }})</div><div class="card-b">
            <div class="g2">
            @foreach($recs as $rec)
                <div style="padding:14px;border-radius:10px;background:rgba(122,162,255,.04);border:1px solid var(--ring);">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <span class="chip" style="background:rgba(34,211,238,.08);border-color:rgba(34,211,238,.2);color:var(--accent);">{{ $rec['category'] }}</span>
                        <span class="chip" style="{{ $rec['confidence'] === 'high' ? 'background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.2);color:var(--success)' : 'background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.2);color:var(--warn)' }}">{{ $rec['confidence'] }}</span>
                    </div>
                    <div style="font-weight:700;font-size:14px;margin-bottom:4px;">{{ $rec['title'] }}</div>
                    <div style="color:var(--muted);font-size:12px;">{{ $rec['detail'] }}</div>
                </div>
            @endforeach
            </div>
        </div></div>
        @else
        <div class="card" style="margin-bottom:14px;"><div class="card-b" style="text-align:center;color:var(--muted);padding:40px;">Not enough data to generate recommendations. More events needed.</div></div>
        @endif

        {{-- Event Simulator --}}
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Event Simulator — Predict Performance</div><div class="card-b">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;">
                <div><label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Genre</label>
                    <input x-model="simGenre" placeholder="e.g. Hip-Hop, Rock, Pop..." style="background:var(--card);border:1px solid var(--ring);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px;width:180px;"></div>
                <div><label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Day of Week</label>
                    <select x-model="simDay" style="background:var(--card);border:1px solid var(--ring);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px;">
                        <option>Monday</option><option>Tuesday</option><option>Wednesday</option><option>Thursday</option><option selected>Friday</option><option>Saturday</option><option>Sunday</option>
                    </select></div>
                <div><label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px;">Ticket Price (RON)</label>
                    <input x-model="simPrice" type="number" min="10" max="5000" style="background:var(--card);border:1px solid var(--ring);border-radius:8px;color:var(--text);padding:8px 12px;font-size:13px;width:120px;"></div>
                <button @click="runSimulation()" style="padding:8px 20px;border-radius:8px;background:linear-gradient(180deg,rgba(34,211,238,.2),rgba(34,211,238,.1));border:1px solid rgba(34,211,238,.3);color:var(--accent);font-weight:600;font-size:13px;cursor:pointer;" :disabled="simLoading">
                    <span x-show="!simLoading">Simulate</span><span x-show="simLoading">...</span>
                </button>
            </div>

            <template x-if="simResult && !simResult.error">
                <div>
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px;">
                        <div class="kpi"><div class="l">Predicted ST</div><div class="v" :style="'color:' + (simResult.predicted_sell_through >= 75 ? 'var(--success)' : simResult.predicted_sell_through >= 50 ? 'var(--warn)' : 'var(--danger)')" x-text="simResult.predicted_sell_through + '%'"></div></div>
                        <div class="kpi"><div class="l">Est. Tickets</div><div class="v" style="color:var(--accent)" x-text="simResult.predicted_tickets"></div></div>
                        <div class="kpi"><div class="l">Est. Revenue</div><div class="v" style="color:var(--warn)" x-text="Number(simResult.predicted_revenue).toLocaleString() + ' RON'"></div></div>
                        <div class="kpi"><div class="l">Demand</div><div class="v" :style="'color:' + (simResult.demand_score >= 75 ? 'var(--success)' : simResult.demand_score >= 50 ? 'var(--primary)' : 'var(--muted)')" x-text="simResult.demand_label + ' (' + simResult.demand_score + ')'"></div></div>
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
                        Genre baseline: <span x-text="simResult.genre_baseline_st + '%'" style="color:var(--text);"></span> ·
                        Day modifier: <span x-text="simResult.dow_modifier + 'x'" style="color:var(--text);"></span> ·
                        Price modifier: <span x-text="simResult.price_modifier + 'x'" style="color:var(--text);"></span>
                    </div>
                    <template x-if="simResult.comparables && simResult.comparables.length">
                        <div><div style="font-size:12px;font-weight:600;color:var(--accent);margin-bottom:6px;">Comparable Past Events</div>
                            <template x-for="c in simResult.comparables" :key="c.title + c.date">
                                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed rgba(122,162,255,.08);font-size:12px;">
                                    <span x-text="c.title"></span>
                                    <span style="color:var(--muted);"><span x-text="c.sell_through + '%'"></span> ST · <span x-text="c.avg_price"></span> RON</span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div></div>

        {{-- Event Suggestions --}}
        <div class="card" style="margin-bottom:14px;"><div class="card-h" style="display:flex;justify-content:space-between;align-items:center;">
            <span>Event Suggestions — What to Book Next</span>
            <button @click="loadSuggestions()" style="padding:5px 14px;border-radius:8px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.2);color:var(--accent);font-weight:600;font-size:12px;cursor:pointer;" :disabled="sugLoading">
                <span x-show="!sugLoading">Generate Suggestions</span><span x-show="sugLoading">Analyzing...</span>
            </button>
        </div><div class="card-b">
            <template x-if="!suggestions"><div style="text-align:center;color:var(--muted);padding:20px;font-size:13px;">Click "Generate Suggestions" to get data-driven booking recommendations.</div></template>
            <template x-if="suggestions && suggestions.length">
                <div>
                    <template x-for="s in suggestions" :key="s.rank">
                        <div style="padding:16px;border-radius:10px;background:rgba(122,162,255,.04);border:1px solid var(--ring);margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                                <span class="chip" style="background:rgba(34,211,238,.08);border-color:rgba(34,211,238,.2);color:var(--accent);" x-text="'#' + s.rank"></span>
                                <span style="font-weight:700;font-size:16px;" x-text="s.genre"></span>
                                <span class="chip" :style="s.confidence === 'high' ? 'background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.2);color:var(--success)' : 'background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.2);color:var(--warn)'" x-text="s.confidence"></span>
                            </div>
                            <div class="g3" style="font-size:12px;">
                                <div>
                                    <div style="color:var(--accent);font-weight:600;margin-bottom:4px;">When</div>
                                    <div style="font-weight:700;" x-text="s.when"></div>
                                    <div style="color:var(--muted);margin-top:2px;" x-text="s.why_when"></div>
                                </div>
                                <div>
                                    <div style="color:var(--accent);font-weight:600;margin-bottom:4px;">Pricing</div>
                                    <div>Regular: <strong x-text="s.pricing.recommended"></strong></div>
                                    <div style="color:var(--muted);" x-text="'Early bird: ' + s.pricing.early_bird"></div>
                                    <div style="color:var(--muted);" x-text="'VIP: ' + s.pricing.vip"></div>
                                </div>
                                <div>
                                    <div style="color:var(--accent);font-weight:600;margin-bottom:4px;">Expected</div>
                                    <div>Revenue: <strong style="color:var(--warn);" x-text="s.estimated_revenue"></strong></div>
                                    <div style="color:var(--muted);" x-text="'Target: ' + s.target_capacity"></div>
                                    <div style="color:var(--muted);" x-text="'Audience: ' + s.target_audience"></div>
                                </div>
                            </div>
                            <template x-if="s.suggested_artists && s.suggested_artists.length">
                                <div style="margin-top:8px;padding-top:8px;border-top:1px dashed rgba(122,162,255,.08);">
                                    <div style="color:var(--accent);font-weight:600;font-size:12px;margin-bottom:4px;">Suggested Artists</div>
                                    <template x-for="a in s.suggested_artists" :key="a.name">
                                        <span style="display:inline-block;padding:3px 10px;border-radius:8px;background:rgba(122,162,255,.06);border:1px solid var(--ring);font-size:12px;margin:2px 4px 2px 0;" x-text="a.name + ' (' + a.avg_sell_through + '% ST)'"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div></div>
    </div>

    {{-- ═══ TAB: PROMOTION ═══ --}}
    <div x-show="tab === 'promotion'" x-cloak>
        @php $promo = $promotionPlanner ?? []; $aw = $promo['announcement_window'] ?? []; $ab = $promo['ad_budget'] ?? []; $ps = $promo['platform_strategy'] ?? []; @endphp

        {{-- Announcement Window --}}
        @if(!empty($aw))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Optimal Announcement Window</div><div class="card-b">
            <div style="display:flex;gap:24px;align-items:center;margin-bottom:14px;">
                <div><div style="color:var(--muted);font-size:11px;">Announce At</div><div style="font-size:28px;font-weight:700;color:var(--accent);">{{ $aw['optimal_announce_days'] ?? 0 }}d</div><div style="color:var(--muted);font-size:11px;">before event</div></div>
                <div><div style="color:var(--muted);font-size:11px;">P90 Purchase</div><div style="font-size:22px;font-weight:700;">{{ $aw['p90_days'] ?? 0 }}d</div></div>
                <div><div style="color:var(--muted);font-size:11px;">Median Purchase</div><div style="font-size:22px;font-weight:700;">{{ $aw['median_days'] ?? 0 }}d</div></div>
            </div>
            @if(!empty($aw['labels']))
            <canvas id="announceChart" height="120"></canvas>
            @endif
        </div></div>
        @endif

        {{-- Ad Budget --}}
        @if(!empty($ab))
        <div class="g2" style="margin-bottom:14px;">
            <div class="card"><div class="card-h">Recommended Ad Budget</div><div class="card-b">
                <div style="display:flex;gap:20px;align-items:center;margin-bottom:14px;">
                    <div><div style="color:var(--muted);font-size:11px;">Est. Revenue/Event</div><div style="font-size:22px;font-weight:700;color:var(--warn);">{{ number_format($ab['estimated_revenue'] ?? 0) }} RON</div></div>
                    <div><div style="color:var(--muted);font-size:11px;">Recommended Budget (12%)</div><div style="font-size:22px;font-weight:700;color:var(--accent);">{{ number_format($ab['recommended_budget'] ?? 0) }} RON</div></div>
                </div>
                @if(!empty($ab['budget_phases']))
                    @foreach($ab['budget_phases'] as $bp)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px dashed rgba(122,162,255,.08);">
                            <div><span style="font-weight:600;">{{ $bp['phase'] }}</span> <span style="color:var(--muted);font-size:11px;">({{ $bp['days_range'] }})</span></div>
                            <div><span style="color:var(--accent);font-weight:600;">{{ $bp['pct'] }}%</span> <span style="color:var(--muted);font-family:monospace;margin-left:8px;">{{ number_format($bp['amount']) }} RON</span></div>
                        </div>
                    @endforeach
                @endif
            </div></div>

            {{-- Budget by Platform visual --}}
            <div class="card"><div class="card-h">Budget Split by Platform</div><div class="card-b">
                @foreach($ps as $p)
                    @php $color = match($p['platform']) { 'Facebook & Instagram' => '#1877f2', 'Google Ads' => '#ea4335', 'TikTok' => '#00f2ea', default => '#a78bfa' }; @endphp
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed rgba(122,162,255,.08);">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:8px;height:8px;border-radius:50%;background:{{ $color }};"></div>
                            <span style="font-weight:600;">{{ $p['platform'] }}</span>
                            @if(!$p['recommended']) <span style="font-size:10px;color:var(--danger);border:1px solid rgba(239,68,68,.3);padding:1px 6px;border-radius:8px;">Low priority</span> @endif
                        </div>
                        <span style="color:var(--accent);font-weight:700;">{{ $p['budget_pct'] }}%</span>
                    </div>
                @endforeach
            </div></div>
        </div>
        @endif

        {{-- Platform Details --}}
        @if(!empty($ps))
        <div style="margin-bottom:14px;">
            @foreach($ps as $p)
            <div class="card" style="margin-bottom:10px;"><div class="card-h">{{ $p['platform'] }} @if(!$p['recommended']) <span style="color:var(--danger);font-size:11px;margin-left:8px;">Low priority for your audience</span> @endif</div><div class="card-b">
                <div class="g3" style="margin-bottom:10px;">
                    <div><div style="color:var(--muted);font-size:11px;margin-bottom:4px;">Audience Targeting</div>
                        @foreach($p['audience'] as $ak => $av)
                            <div style="font-size:12px;margin-bottom:2px;"><strong style="color:var(--accent);">{{ ucfirst($ak) }}:</strong> {{ is_array($av) ? implode(', ', $av) : $av }}</div>
                        @endforeach
                    </div>
                    <div><div style="color:var(--muted);font-size:11px;margin-bottom:4px;">Formats</div>
                        @foreach($p['formats'] as $f)
                            <div style="font-size:12px;margin-bottom:2px;">• {{ $f }}</div>
                        @endforeach
                    </div>
                    <div>
                        <div style="color:var(--muted);font-size:11px;margin-bottom:4px;">Active Phases</div>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            @foreach($p['phases'] as $ph)
                                <span class="chip" style="background:rgba(34,211,238,.08);border-color:rgba(34,211,238,.2);color:var(--accent);">{{ $ph }}</span>
                            @endforeach
                        </div>
                        <div style="color:var(--warn);font-size:11px;margin-top:8px;">{{ $p['tips'] }}</div>
                    </div>
                </div>
            </div></div>
            @endforeach
        </div>
        @endif

        {{-- Creative Calendar Generator --}}
        @php $upcomingForCal = $upcomingEvents ?? []; @endphp
        @if(!empty($upcomingForCal))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Creative Calendar — Generate Campaign Timeline</div><div class="card-b">
            <div style="margin-bottom:12px;font-size:12px;color:var(--muted);">Select an upcoming event to generate a day-by-day promotion campaign:</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                @foreach(array_slice($upcomingForCal, 0, 8) as $ucal)
                    <button @click="loadCalendar({{ $ucal['id'] }})" style="padding:6px 14px;border-radius:8px;background:rgba(122,162,255,.06);border:1px solid var(--ring);color:var(--text);font-size:12px;cursor:pointer;font-weight:600;" :disabled="calLoading">
                        {{ \Illuminate\Support\Str::limit($ucal['title'], 25) }} <span style="color:var(--muted);">· {{ $ucal['date'] ? \Carbon\Carbon::parse($ucal['date'])->format('d M') : '' }}</span>
                    </button>
                @endforeach
            </div>

            <template x-if="calendarResult && !calendarResult.error">
                <div>
                    <div style="padding:14px;border-radius:10px;background:rgba(34,211,238,.04);border:1px solid rgba(34,211,238,.15);margin-bottom:14px;">
                        <div style="font-size:16px;font-weight:700;" x-text="calendarResult.event_title"></div>
                        <div style="color:var(--muted);font-size:12px;margin-top:4px;">
                            <span x-text="calendarResult.event_date"></span> · <span x-text="calendarResult.artists"></span> ·
                            Cap: <span x-text="calendarResult.capacity"></span> · Sold: <span x-text="calendarResult.sold"></span> ·
                            Est. Revenue: <strong style="color:var(--warn);" x-text="Number(calendarResult.estimated_revenue).toLocaleString() + ' RON'"></strong> ·
                            Ad Budget: <strong style="color:var(--accent);" x-text="Number(calendarResult.total_ad_budget).toLocaleString() + ' RON'"></strong>
                        </div>
                    </div>

                    <template x-for="phase in calendarResult.phases" :key="phase.phase">
                        <div style="position:relative;padding-left:30px;margin-bottom:20px;">
                            {{-- Timeline dot + line --}}
                            <div style="position:absolute;left:8px;top:0;bottom:-20px;width:2px;background:var(--ring);"></div>
                            <div style="position:absolute;left:3px;top:4px;width:12px;height:12px;border-radius:50%;background:var(--accent);border:2px solid var(--bg);z-index:1;"></div>

                            <div style="font-weight:700;font-size:14px;color:var(--accent);" x-text="phase.phase"></div>
                            <div style="font-size:12px;color:var(--muted);margin-bottom:6px;">
                                <span x-text="phase.date"></span> · <span x-text="phase.days_before + 'd before'"></span> · Budget: <strong x-text="Number(phase.budget).toLocaleString() + ' RON'"></strong>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div>
                                    <div style="font-size:11px;color:var(--accent);font-weight:600;margin-bottom:4px;">Actions</div>
                                    <template x-for="action in phase.actions" :key="action">
                                        <div style="font-size:12px;color:var(--text);margin-bottom:3px;padding-left:10px;position:relative;">
                                            <span style="position:absolute;left:0;color:var(--muted);">•</span>
                                            <span x-text="action"></span>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <div style="font-size:11px;color:var(--warn);font-weight:600;margin-bottom:4px;">Ad Spend</div>
                                    <template x-for="ad in phase.ads" :key="ad">
                                        <div style="font-size:12px;color:var(--muted);margin-bottom:3px;padding-left:10px;position:relative;">
                                            <span style="position:absolute;left:0;color:var(--warn);">›</span>
                                            <span x-text="ad"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="calLoading"><div style="text-align:center;color:var(--muted);padding:20px;">Generating campaign timeline...</div></template>
        </div></div>
        @endif
    </div>

    {{-- ═══ TAB: UPCOMING ═══ --}}
    <div x-show="tab === 'upcoming'" x-cloak>
        @php $upcoming = $upcomingEvents ?? []; @endphp
        @if(!empty($upcoming))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Upcoming Events ({{ count($upcoming) }})</div>
            <div style="overflow-x:auto;">
            <table class="tbl"><thead><tr><th>Event</th><th>Artists</th><th style="text-align:right">Days Left</th><th style="text-align:right">Sold / Cap</th><th style="text-align:right">Sell-Through</th><th style="text-align:right">Revenue</th><th style="text-align:center">Demand</th></tr></thead>
            <tbody>
            @foreach($upcoming as $ue)
                @php $dColor = match($ue['demand_label']) { 'Hot' => 'var(--success)', 'Strong' => 'var(--primary)', 'Moderate' => 'var(--warn)', default => 'var(--muted)' }; @endphp
                <tr>
                    <td><div style="font-weight:600">{{ \Illuminate\Support\Str::limit($ue['title'], 35) }}</div><div style="color:var(--muted);font-size:11px;">{{ $ue['date'] ? \Carbon\Carbon::parse($ue['date'])->format('d M Y') : '—' }}</div></td>
                    <td style="color:var(--muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ \Illuminate\Support\Str::limit($ue['artists'], 30) }}</td>
                    <td style="text-align:right;font-weight:{{ ($ue['days_until'] ?? 99) <= 7 ? '700' : '400' }};color:{{ ($ue['days_until'] ?? 99) <= 7 ? 'var(--danger)' : 'var(--text)' }}">{{ $ue['days_until'] ?? '—' }}d</td>
                    <td style="text-align:right;font-family:monospace">{{ $ue['sold'] }} / {{ $ue['capacity'] ?: '—' }}</td>
                    <td style="text-align:right;font-weight:700;color:{{ ($ue['sell_through'] ?? 0) >= 75 ? 'var(--success)' : (($ue['sell_through'] ?? 0) >= 50 ? 'var(--warn)' : 'var(--muted)') }}">{{ $ue['sell_through'] !== null ? $ue['sell_through'] . '%' : '—' }}</td>
                    <td style="text-align:right;color:var(--warn);font-family:monospace">{{ number_format($ue['revenue']) }}</td>
                    <td style="text-align:center"><span class="chip" style="color:{{ $dColor }};border-color:{{ $dColor }}33;background:{{ $dColor }}10;">{{ $ue['demand_label'] }} ({{ $ue['demand_score'] }})</span></td>
                </tr>
            @endforeach
            </tbody></table>
            </div>
        </div>
        @else
        <div class="card"><div class="card-b" style="text-align:center;color:var(--muted);padding:40px;">No upcoming events at this venue.</div></div>
        @endif

        {{-- Churn Risk Alerts --}}
        @php $churn = $churnAlerts ?? []; @endphp
        @if(!empty($churn))
        <div class="card" style="margin-bottom:14px;"><div class="card-h" style="color:var(--danger);">Churn Risk Alerts ({{ count($churn) }} events need attention)</div><div class="card-b">
            @foreach($churn as $ca)
                @php $riskColor = match($ca['risk']) { 'critical' => 'var(--danger)', 'high' => 'var(--warn)', default => 'var(--muted)' }; @endphp
                <div style="padding:14px;border-radius:10px;border:1px solid {{ $riskColor }}33;background:{{ $riskColor }}08;margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <div>
                            <span class="chip" style="color:{{ $riskColor }};border-color:{{ $riskColor }}33;background:{{ $riskColor }}15;text-transform:uppercase;">{{ $ca['risk'] }}</span>
                            <strong style="margin-left:8px;">{{ \Illuminate\Support\Str::limit($ca['title'], 40) }}</strong>
                            <span style="color:var(--muted);font-size:12px;margin-left:8px;">{{ $ca['days_until'] }}d left · {{ $ca['sold'] }}/{{ $ca['capacity'] }} ({{ $ca['sell_through'] }}%)</span>
                        </div>
                        <span style="font-size:12px;color:{{ $riskColor }};font-weight:700;">{{ $ca['gap'] }} tickets to fill</span>
                    </div>
                    <div style="font-size:12px;color:var(--muted);">
                        @foreach($ca['suggestions'] as $sug)
                            <div style="padding:3px 0;padding-left:14px;position:relative;"><span style="position:absolute;left:0;color:{{ $riskColor }};">→</span> {{ $sug }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div></div>
        @endif
    </div>

    {{-- ═══ TAB: ACTIONS ═══ --}}
    <div x-show="tab === 'actions'" x-cloak>
        @php $actions = $actionPriority ?? []; @endphp
        @if(!empty($actions))
        <div class="card" style="margin-bottom:14px;"><div class="card-h">Action Priority Dashboard — What to Do Next ({{ count($actions) }} items)</div><div class="card-b">
            @foreach($actions as $ai => $act)
                @php $urgColor = match($act['urgency']) { 'critical' => 'var(--danger)', 'high' => '#f97316', 'medium' => 'var(--warn)', default => 'var(--muted)' }; @endphp
                <div style="display:flex;gap:14px;padding:14px;border-radius:10px;background:{{ $urgColor }}08;border:1px solid {{ $urgColor }}22;margin-bottom:10px;">
                    <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:{{ $urgColor }}15;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:{{ $urgColor }};">{{ $ai + 1 }}</div>
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span class="chip" style="color:{{ $urgColor }};border-color:{{ $urgColor }}33;background:{{ $urgColor }}10;text-transform:uppercase;font-size:10px;">{{ $act['urgency'] }}</span>
                            <span class="chip" style="background:rgba(122,162,255,.06);border-color:var(--ring);color:var(--accent);font-size:10px;">{{ $act['category'] }}</span>
                        </div>
                        <div style="font-weight:700;font-size:14px;margin-bottom:4px;">{{ $act['title'] }}</div>
                        <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">
                            <span style="color:var(--accent);font-weight:600;">Action:</span> {{ $act['action'] }}
                        </div>
                        <div style="font-size:12px;padding:4px 10px;border-radius:6px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.1);display:inline-block;">
                            <span style="color:var(--success);font-weight:600;">Impact:</span> <span style="color:var(--text);">{{ $act['impact'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div></div>
        @else
        <div class="card"><div class="card-b" style="text-align:center;color:var(--success);padding:40px;font-size:14px;font-weight:600;">No urgent actions needed. Your venue is performing well!</div></div>
        @endif
    </div>

</div>{{-- end .db --}}

{{-- ═══ CHART.JS ═══ --}}
@if(!empty($months))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const months = @json($months);
    const opts = (extra) => Object.assign({responsive:true,plugins:{legend:{display:true,labels:{color:'#64748B',font:{size:10}}}},scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B',font:{size:10}}},y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B',font:{size:10}},beginAtZero:true}}}, extra);

    new Chart(document.getElementById('venueEvTxChart'), {type:'bar', data:{labels:months, datasets:[
        {label:'Events',data:@json($evSeries),backgroundColor:'rgba(99,102,241,0.6)',borderRadius:4,yAxisID:'y'},
        {label:'Tickets',data:@json($txSeries),backgroundColor:'rgba(16,185,129,0.4)',borderRadius:4,yAxisID:'y1'}
    ]}, options:Object.assign(opts({}),{scales:{y:{position:'left',grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B'}},y1:{position:'right',grid:{display:false},ticks:{color:'#10b981'}},x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B',font:{size:10}}}}})});

    new Chart(document.getElementById('venueRevOccChart'), {type:'line', data:{labels:months, datasets:[
        {label:'Revenue (RON)',data:@json($rvSeries),borderColor:'#fbbf24',backgroundColor:'rgba(251,191,36,0.1)',fill:true,tension:0.3,pointRadius:3,yAxisID:'y'},
        {label:'Occupancy %',data:@json($ocSeries),borderColor:'#22d3ee',backgroundColor:'rgba(34,211,238,0.1)',fill:true,tension:0.3,pointRadius:3,yAxisID:'y1'}
    ]}, options:Object.assign(opts({}),{scales:{y:{position:'left',grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#fbbf24'}},y1:{position:'right',grid:{display:false},ticks:{color:'#22d3ee',callback:v=>v+'%'},max:100},x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B',font:{size:10}}}}})});

    @if(!empty($promo['announcement_window']['labels'] ?? []))
    const awLabels = @json($promo['announcement_window']['labels']);
    const awValues = @json($promo['announcement_window']['values']);
    const awIdx = {{ $promo['announcement_window']['announce_week_index'] ?? 0 }};
    const awColors = awValues.map((v,i) => i === (awLabels.length - 1 - awIdx) ? 'rgba(34,211,238,0.8)' : 'rgba(122,162,255,0.4)');
    const awEl = document.getElementById('announceChart');
    if (awEl) { new Chart(awEl, {type:'bar', data:{labels:awLabels, datasets:[{data:awValues,backgroundColor:awColors,borderRadius:4}]}, options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B',font:{size:9}}},y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748B'}}}}}); }
    @endif
});
</script>
@endif
</x-filament-panels::page>
