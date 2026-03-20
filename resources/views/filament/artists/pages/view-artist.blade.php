{{-- resources/views/filament/artists/pages/view-artist.blade.php --}}
@php
    $months   = $seriesMonths   ?? [];
    $events   = $seriesEvents   ?? [];
    $tickets  = $seriesTickets  ?? [];
    $revenue  = $seriesRevenue  ?? [];

    $types    = $record->artistTypes?->map(fn($t) => $t->getTranslation('name', app()->getLocale()))->all() ?? [];
    $genres   = $record->artistGenres?->map(fn($g) => $g->getTranslation('name', app()->getLocale()))->all() ?? [];
    $country  = $record->country ?? null;
    $city     = $record->city ?? null;

    $followers = [
        'YouTube Subs' => $record->followers_youtube ?? null,
        'YT Views' => $record->youtube_total_views ?? null,
        'Spotify' => $record->spotify_monthly_listeners ?? null,
        'Spotify Pop.' => $record->spotify_popularity ?? null,
        'Facebook'  => $record->followers_facebook ?? null,
        'Instagram' => $record->followers_instagram ?? null,
        'TikTok'    => $record->followers_tiktok ?? null,
    ];

    $socialLinks = [
        'Website' => $record->website ?? null, 'Facebook' => $record->facebook_url ?? null,
        'Instagram' => $record->instagram_url ?? null, 'TikTok' => $record->tiktok_url ?? null,
        'YouTube' => $record->youtube_url ?? null, 'Spotify' => $record->spotify_url ?? null,
    ];

    $bioHtml = is_array($record->bio_html ?? null)
        ? ($record->bio_html['en'] ?? reset($record->bio_html))
        : ($record->bio_html ?? $record->bio ?? null);

    $rawVideos = is_array($record->youtube_videos ?? null) ? $record->youtube_videos : [];
    $videos = [];
    foreach ($rawVideos as $v) {
        $url = is_array($v) ? ($v['url'] ?? '') : $v;
        if (empty($url)) continue;
        $videoId = null;
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (strlen($url) === 11 && preg_match('/^[a-zA-Z0-9_-]+$/', $url)) {
            $videoId = $url;
        }
        if ($videoId) $videos[] = "https://www.youtube.com/embed/{$videoId}";
    }

    $totalEvents  = is_array($events)  ? array_sum($events)  : 0;
    $totalTickets = is_array($tickets) ? array_sum($tickets) : 0;
    $totalRevenue = is_array($revenue) ? array_sum($revenue) : 0.0;
    $avgTicketsPerEvent = $totalEvents > 0 ? round($totalTickets / $totalEvents, 1) : 0;
    $avgTicketPrice     = $totalTickets > 0 ? round($totalRevenue / $totalTickets, 2) : 0.00;

    $topVenues   = $topVenues   ?? [];
    $topCities   = $topCities   ?? [];
    $topCounties = $topCounties ?? [];
    $spotifyArtistId = $record->spotify_id ?? null;
    $artistEvents = $artistEvents ?? collect();
    $artistVenues = $artistVenues ?? collect();
    $artistTenants = $artistTenants ?? collect();

    // 360 Analytics
    $personas = $audiencePersonas['personas'] ?? [];
    $aTotals = $audiencePersonas['totals'] ?? [];
    $geoData = $geoIntelligence ?? [];
    $perf = $performanceDeepDive ?? [];
    $perfEvents = $perf['events'] ?? [];
    $loyalty = $perf['customer_loyalty'] ?? [];
    $roleComp = $perf['role_comparison'] ?? [];
    $sales = $salesIntelligence ?? [];
    $channels = (array)($sales['channels'] ?? []);
    $timing = $sales['purchase_timing'] ?? [];
    $priceSens = $sales['price_sensitivity'] ?? [];
    $velocity = $sales['velocity_curves'] ?? [];
    $expansion = $expansionPlanner ?? [];
    $upcomingEvents = $upcomingAnalysis ?? [];
@endphp

@push('styles')
<style>
:root{--bg:#0b1020;--card:#10183a;--card2:#0d1431;--muted:#a7b0c3;--text:#e9eefb;--primary:#7aa2ff;--accent:#22d3ee;--success:#22c55e;--ring:rgba(122,162,255,.15);--warn:#fbbf24;--danger:#ef4444;}
.fi-page,body{background:var(--bg);color:var(--text);}
[x-cloak]{display:none!important;}
a{color:var(--primary);text-decoration:none}a:hover{text-decoration:underline}
img{display:block;max-width:100%}

.db{max-width:100%;margin:0 auto;padding:0;}
.db-header{position:sticky;top:0px;z-index:30;background:linear-gradient(180deg,rgba(11,16,32,.97),rgba(11,16,32,.85));backdrop-filter:blur(8px);border-bottom:1px solid var(--ring);padding:12px 0;}
.db-header-inner{max-width:1280px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.db-title{font-size:22px;font-weight:700;letter-spacing:.2px;}
.db-sub{display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;}

.chip{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid var(--ring);}
.chip-blue{background:rgba(122,162,255,.1);color:#dbe6ff;}
.chip-purple{background:rgba(192,132,252,.1);border-color:rgba(192,132,252,.2);color:#e9d5ff;}
.chip-gray{background:rgba(148,163,184,.1);border-color:rgba(148,163,184,.2);color:#e2e8f0;}
.chip-green{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.2);color:#86efac;}
.chip-yellow{background:rgba(251,191,36,.1);border-color:rgba(251,191,36,.2);color:#fbbf24;}
.chip-red{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.2);color:#ef4444;}

.card{background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--ring);border-radius:12px;}
.card-h{padding:12px 16px;border-bottom:1px solid var(--ring);font-weight:600;font-size:13px;color:#cdd7f6;}
.card-b{padding:14px 16px;}

.kpi-grid{display:grid;gap:10px;}
.kpi-grid-5{grid-template-columns:repeat(5,1fr);}
.kpi-grid-6{grid-template-columns:repeat(6,1fr);}
.kpi-grid-4{grid-template-columns:repeat(4,1fr);}
.kpi-grid-3{grid-template-columns:repeat(3,1fr);}
@media(max-width:1100px){.kpi-grid-5,.kpi-grid-6{grid-template-columns:repeat(3,1fr);}}
@media(max-width:700px){.kpi-grid-5,.kpi-grid-6,.kpi-grid-4,.kpi-grid-3{grid-template-columns:repeat(2,1fr);}}
.kpi{padding:14px;border-radius:12px;background:linear-gradient(180deg,#0f1634,#0d1330);border:1px solid var(--ring);}
.kpi .l{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
.kpi .v{margin-top:4px;font-size:20px;font-weight:700;}

.tbl{width:100%;border-collapse:separate;border-spacing:0;border-radius:10px;border:1px solid var(--ring);overflow:hidden;font-size:13px;}
.tbl thead th{background:#0f1736;color:#cdd7f6;font-weight:600;text-align:left;padding:8px 10px;border-bottom:1px solid var(--ring);}
.tbl tbody td{padding:8px 10px;border-bottom:1px dashed rgba(122,162,255,.08);color:#dbe6ff;}
.tbl tbody tr:last-child td{border-bottom:0;}

.badge{display:inline-flex;align-items:center;padding:3px 7px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;}

.tabs{display:flex;gap:2px;flex-wrap:wrap;margin-bottom:16px;border-bottom:1px solid var(--ring);padding-bottom:8px;}
.tab{padding:7px 14px;border-radius:8px 8px 0 0;font-size:12px;font-weight:600;color:var(--muted);background:transparent;border:none;cursor:pointer;transition:all .15s;letter-spacing:.3px;}
.tab:hover{color:var(--text);background:rgba(122,162,255,.06);}
.tab.active{color:var(--accent);background:rgba(34,211,238,.08);border-bottom:2px solid var(--accent);}

.btn{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:10px;border:1px solid var(--ring);background:linear-gradient(180deg,#151f45,#0f1736);color:var(--text);font-weight:600;font-size:12px;cursor:pointer;text-decoration:none;}
.btn:hover{filter:brightness(1.06);text-decoration:none;}

.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
@media(max-width:800px){.g2,.g3{grid-template-columns:1fr;}}

.follower-row{display:flex;gap:6px;flex-wrap:wrap;}
.f-pill{padding:6px 12px;border-radius:10px;background:linear-gradient(180deg,#0f1736,#0e1532);border:1px solid var(--ring);font-size:12px;}
.f-pill .n{color:var(--muted);}
.f-pill .v{font-weight:700;margin-left:4px;}

.link-row{display:flex;gap:8px;flex-wrap:wrap;}
.link-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:10px;background:linear-gradient(180deg,#0f1736,#0e1532);border:1px solid var(--ring);font-size:12px;}
.link-pill a{color:var(--text);}

canvas{width:100%!important;}
.chart-h{height:200px;}

.progress{height:5px;background:rgba(122,162,255,.1);border-radius:3px;overflow:hidden;flex:1;min-width:40px;}
.progress-fill{height:100%;border-radius:3px;}
.db>[x-show]{padding-bottom:60px;}
</style>
@endpush

<div class="db" x-data="{ tab: 'overview' }">

    {{-- ═══════ STICKY HEADER ═══════ --}}
    <div class="db-header">
        <div class="db-header-inner">
            <div>
                <div class="db-title">{{ $record->name }}</div>
                <div class="db-sub">
                    @foreach($types as $t) <span class="chip chip-blue">{{ $t }}</span> @endforeach
                    @foreach($genres as $g) <span class="chip chip-purple">{{ $g }}</span> @endforeach
                    @if($country) <span class="chip chip-gray">{{ $city ? $city.' · ' : '' }}{{ $country }}</span> @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <a class="btn" href="{{ request()->fullUrlWithQuery(['refresh_analytics' => 1]) }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M1 4v6h6M23 20v-6h-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M3.51 15a9 9 0 0 0 14.85 3.36L23 14M1 10l4.64 4.36A9 9 0 0 0 20.49 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    Refresh
                </a>
                <a class="btn" href="{{ \App\Filament\Resources\Artists\ArtistResource::getUrl('edit', ['record' => $record->getKey()]) }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 20h4l10-10a2.828 2.828 0 1 0-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    Edit
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════ TABS ═══════ --}}
    <div class="tabs" style="margin-top:16px;">
        @foreach([
            'overview' => 'Overview', 'performance' => 'Performance', 'audience' => 'Audience',
            'sales' => 'Sales', 'geographic' => 'Geographic', 'upcoming' => 'Upcoming',
            'expansion' => 'Expansion', 'media' => 'Media & Social', 'history' => 'Events History',
        ] as $tabKey => $tabLabel)
            <button @click="tab = '{{ $tabKey }}'" :class="tab === '{{ $tabKey }}' ? 'active' : ''" class="tab">{{ $tabLabel }}</button>
        @endforeach
    </div>

    {{-- ═══════ TAB: OVERVIEW ═══════ --}}
    <div x-show="tab === 'overview'">
        {{-- Hero KPIs --}}
        <div class="kpi-grid kpi-grid-5" style="margin-bottom:14px;">
            <div class="kpi"><div class="l">Events (12m)</div><div class="v">{{ $totalEvents }}</div></div>
            <div class="kpi"><div class="l">Tickets Sold</div><div class="v">{{ number_format($totalTickets) }}</div></div>
            <div class="kpi"><div class="l">Revenue</div><div class="v" style="color:var(--success);">{{ number_format($totalRevenue, 0) }} RON</div></div>
            <div class="kpi"><div class="l">Avg / Event</div><div class="v">{{ $avgTicketsPerEvent }}</div></div>
            <div class="kpi"><div class="l">Avg Price</div><div class="v">{{ number_format($avgTicketPrice, 0) }} RON</div></div>
        </div>

        <div class="kpi-grid kpi-grid-6" style="margin-bottom:14px;">
            <div class="kpi"><div class="l">Unique Buyers</div><div class="v">{{ number_format($aTotals['total_customers'] ?? 0) }}</div></div>
            <div class="kpi"><div class="l">Sell-Through</div><div class="v">{{ $perf['avg_sell_through'] ?? 0 }}%</div></div>
            <div class="kpi"><div class="l">Check-in</div><div class="v">{{ $perf['avg_checkin_rate'] ?? 0 }}%</div></div>
            <div class="kpi"><div class="l">Repeat Rate</div><div class="v">{{ $loyalty['repeat_rate'] ?? 0 }}%</div></div>
            <div class="kpi"><div class="l">Superfans</div><div class="v" style="color:var(--warn);">{{ $loyalty['superfan'] ?? 0 }}</div></div>
            <div class="kpi"><div class="l">Avg Lead</div><div class="v">{{ $sales['avg_lead_days'] ?? 0 }}d</div></div>
        </div>

        {{-- Charts row --}}
        <div class="g3" style="margin-bottom:14px;">
            <div class="card"><div class="card-h">Events / month</div><div class="card-b"><div class="chart-h"><canvas id="eventsChart"></canvas></div></div></div>
            <div class="card"><div class="card-h">Tickets / month</div><div class="card-b"><div class="chart-h"><canvas id="ticketsChart"></canvas></div></div></div>
            <div class="card"><div class="card-h">Revenue / month</div><div class="card-b"><div class="chart-h"><canvas id="revenueChart"></canvas></div></div></div>
        </div>

        {{-- Quick insights row --}}
        <div class="g2">
            <div class="card">
                <div class="card-h">Top Customer Persona</div>
                <div class="card-b">
                    @if(!empty($personas))
                        @php $p = $personas[0]; @endphp
                        <div style="font-size:14px;font-weight:700;margin-bottom:6px;">{{ $p['age_group'] }} · {{ ucfirst($p['gender']) }} <span class="badge" style="margin-left:4px;">{{ $p['percentage'] }}%</span></div>
                        <div style="font-size:12px;color:var(--muted);">Avg: <strong style="color:var(--success);">{{ number_format($p['avg_spend'], 0) }} RON</strong> · {{ $p['avg_orders'] }} orders</div>
                        @if(!empty($p['top_cities'])) <div style="margin-top:6px;">@foreach($p['top_cities'] as $c => $n) <span class="chip chip-blue">{{ $c }} ({{ $n }})</span> @endforeach</div> @endif
                    @else <div style="color:var(--muted);font-size:13px;">No demographic data</div> @endif
                </div>
            </div>
            <div class="card">
                <div class="card-h">Top Expansion Opportunity</div>
                <div class="card-b">
                    @if(!empty($expansion))
                        @php $exp = $expansion[0]; @endphp
                        <div style="font-size:14px;font-weight:700;margin-bottom:6px;">{{ $exp['city'] }}, {{ $exp['country'] }}</div>
                        <div style="font-size:12px;color:var(--muted);">{{ $exp['fan_count'] }} fans · Est. {{ $exp['estimated_demand'] }} demand</div>
                        @if(!empty($exp['venues'])) <div style="font-size:12px;color:var(--muted);margin-top:2px;">{{ $exp['venues'][0]['name'] ?? '' }} ({{ number_format($exp['venues'][0]['capacity'] ?? 0) }})</div> @endif
                        @php $cc = match($exp['confidence']) { 'high' => 'green', 'medium' => 'yellow', default => 'red' }; @endphp
                        <span class="chip chip-{{ $cc }}" style="margin-top:6px;">{{ ucfirst($exp['confidence']) }}</span>
                    @else <div style="color:var(--muted);font-size:13px;">No expansion data</div> @endif
                </div>
            </div>
        </div>

        {{-- Role comparison --}}
        @if(!empty($roleComp))
        <div class="kpi-grid kpi-grid-{{ count($roleComp) }}" style="margin-top:14px;">
            @foreach($roleComp as $role => $data)
            <div class="kpi"><div class="l">{{ $role }}</div><div style="margin-top:4px;font-size:13px;color:#dbe6ff;">{{ $data['events'] }} ev · {{ $data['avg_sold'] }} avg · <span style="color:var(--success);">{{ $data['avg_sell_through'] }}%</span></div></div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ═══════ TAB: PERFORMANCE ═══════ --}}
    <div x-show="tab === 'performance'" x-cloak>
        @if(empty($perfEvents))
            <div class="card"><div class="card-b" style="color:var(--muted);text-align:center;padding:32px;">No performance data.</div></div>
        @else
            <div class="kpi-grid kpi-grid-4" style="margin-bottom:14px;">
                <div class="kpi"><div class="l">Avg Sell-Through</div><div class="v">{{ $perf['avg_sell_through'] ?? 0 }}%</div></div>
                <div class="kpi"><div class="l">Avg Check-in</div><div class="v">{{ $perf['avg_checkin_rate'] ?? 0 }}%</div></div>
                <div class="kpi"><div class="l">Repeat Rate</div><div class="v">{{ $loyalty['repeat_rate'] ?? 0 }}%</div></div>
                <div class="kpi"><div class="l">Superfans (3x+)</div><div class="v" style="color:var(--warn);">{{ $loyalty['superfan'] ?? 0 }}</div></div>
            </div>
            <div style="display:flex;gap:14px;margin-bottom:14px;align-items:center;">
                <div class="card" style="width:170px;"><div class="card-b" style="padding:8px;"><div style="height:150px;"><canvas id="loyaltyChart"></canvas></div></div></div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <div><span style="color:#94a3b8;font-size:26px;font-weight:700;">{{ $loyalty['one_time'] ?? 0 }}</span><br><span style="color:var(--muted);font-size:11px;">One-time</span></div>
                    <div><span style="color:var(--primary);font-size:26px;font-weight:700;">{{ $loyalty['repeat'] ?? 0 }}</span><br><span style="color:var(--muted);font-size:11px;">Repeat</span></div>
                    <div><span style="color:var(--warn);font-size:26px;font-weight:700;">{{ $loyalty['superfan'] ?? 0 }}</span><br><span style="color:var(--muted);font-size:11px;">Superfan</span></div>
                </div>
            </div>
            <div class="card"><div class="card-b" style="overflow-x:auto;">
                <table class="tbl">
                    <thead><tr><th>Date</th><th>Event</th><th>Venue</th><th>Sold/Cap</th><th>Sell-Through</th><th>Check-in</th><th>Role</th></tr></thead>
                    <tbody>
                        @foreach(array_slice($perfEvents, 0, 30) as $pe)
                        <tr>
                            <td style="white-space:nowrap;">{{ $pe['date'] ? \Carbon\Carbon::parse($pe['date'])->format('d M Y') : '—' }}</td>
                            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pe['title'] ?? '—' }}</td>
                            <td>{{ $pe['venue'] ?? '—' }}</td>
                            <td style="white-space:nowrap;">{{ number_format($pe['sold']) }}/{{ $pe['capacity'] ?: '?' }}</td>
                            <td>@if($pe['sell_through'] !== null)<div style="display:flex;align-items:center;gap:6px;"><div class="progress"><div class="progress-fill" style="width:{{ min($pe['sell_through'],100) }}%;background:{{ $pe['sell_through']>=80?'var(--success)':($pe['sell_through']>=50?'var(--warn)':'var(--danger)') }};"></div></div><span style="font-size:11px;">{{ $pe['sell_through'] }}%</span></div>@else — @endif</td>
                            <td>{{ $pe['checkin_rate'] !== null ? $pe['checkin_rate'].'%' : '—' }}</td>
                            <td>@if($pe['is_headliner'])<span class="chip chip-yellow">H</span>@elseif($pe['is_co_headliner'])<span class="chip chip-blue">Co</span>@else<span class="chip chip-gray">S</span>@endif</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif
    </div>

    {{-- ═══════ TAB: AUDIENCE ═══════ --}}
    <div x-show="tab === 'audience'" x-cloak>
        @if(empty($personas))
            <div class="card"><div class="card-b" style="color:var(--muted);text-align:center;padding:32px;">No customer demographic data available.</div></div>
        @else
            <div class="g3" style="margin-bottom:14px;">
                @foreach($personas as $persona)
                <div class="card">
                    <div class="card-b">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                            <span style="font-size:13px;font-weight:700;">{{ $persona['label'] }}</span>
                            <span class="badge">{{ $persona['percentage'] }}%</span>
                        </div>
                        <div style="font-size:12px;color:var(--muted);line-height:1.8;">
                            Age: <strong style="color:var(--text);">{{ $persona['age_group'] }}</strong> · Gender: <strong style="color:var(--text);">{{ ucfirst($persona['gender']) }}</strong><br>
                            Spend: <strong style="color:var(--success);">{{ number_format($persona['avg_spend'], 0) }} RON</strong> · Orders: <strong>{{ $persona['avg_orders'] }}</strong>
                        </div>
                        @if(!empty($persona['top_cities'])) <div style="margin-top:6px;">@foreach($persona['top_cities'] as $c => $n) <span class="chip chip-blue" style="margin-top:3px;">{{ $c }} ({{ $n }})</span> @endforeach</div> @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="g2">
                <div class="card"><div class="card-h">Age Distribution</div><div class="card-b"><div style="height:230px;"><canvas id="ageDistChart"></canvas></div></div></div>
                <div class="card"><div class="card-h">Gender Distribution</div><div class="card-b"><div style="height:230px;"><canvas id="genderChart"></canvas></div></div></div>
            </div>
        @endif
    </div>

    {{-- ═══════ TAB: SALES ═══════ --}}
    <div x-show="tab === 'sales'" x-cloak>
        @if(empty($channels) && empty($timing))
            <div class="card"><div class="card-b" style="color:var(--muted);text-align:center;padding:32px;">No sales data.</div></div>
        @else
            <div class="g2" style="margin-bottom:14px;">
                <div class="card"><div class="card-h">Sales Channels</div><div class="card-b"><div style="height:220px;"><canvas id="channelChart"></canvas></div></div></div>
                <div class="card"><div class="card-h">Purchase Timing</div><div class="card-b"><div style="height:220px;"><canvas id="timingChart"></canvas></div></div></div>
            </div>
            <div class="g2">
                @if(!empty($priceSens))<div class="card"><div class="card-h">Price Sensitivity</div><div class="card-b"><div style="height:230px;"><canvas id="priceChart"></canvas></div></div></div>@endif
                @if(!empty($velocity))
                <div class="card">
                    <div class="card-h">Sales Pace — How fast tickets sold (last {{ count($velocity) }} events)</div>
                    <div class="card-b">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:10px;">Shows % of tickets sold at key milestones before each event</div>
                        <table class="tbl">
                            <thead><tr><th>Event</th><th>Total</th><th>90d before</th><th>60d</th><th>30d</th><th>7d</th><th>1d</th></tr></thead>
                            <tbody>
                                @foreach($velocity as $vc)
                                @php
                                    $pts = collect($vc['points']);
                                    $pctAt = function($days) use ($pts) {
                                        $match = $pts->where('days', '>=', $days)->sortBy('days')->first();
                                        return $match ? $match['pct'] : ($pts->isNotEmpty() ? $pts->last()['pct'] : 0);
                                    };
                                @endphp
                                <tr>
                                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $vc['event_name'] }}</td>
                                    <td><span class="badge">{{ $vc['total_tickets'] }}</span></td>
                                    @foreach([90, 60, 30, 7, 1] as $d)
                                    @php $pct = $pctAt($d); @endphp
                                    <td>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <div class="progress" style="width:50px;"><div class="progress-fill" style="width:{{ min($pct,100) }}%;background:{{ $pct>=80?'var(--success)':($pct>=50?'var(--warn)':'var(--primary)') }};"></div></div>
                                            <span style="font-size:11px;">{{ round($pct) }}%</span>
                                        </div>
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ═══════ TAB: GEOGRAPHIC ═══════ --}}
    <div x-show="tab === 'geographic'" x-cloak>
        @if(empty($geoData))
            <div class="card"><div class="card-b" style="color:var(--muted);text-align:center;padding:32px;">No geographic data.</div></div>
        @else
            <div class="card"><div class="card-b" style="overflow-x:auto;">
                <table class="tbl">
                    <thead><tr><th>City</th><th>Country</th><th>Fans</th><th>Favorites</th><th>Potential</th><th>Revenue</th><th>Best Venue</th><th>Capacity</th></tr></thead>
                    <tbody>
                        @foreach($geoData as $geo)
                        <tr>
                            <td><strong>{{ $geo['city'] }}</strong></td>
                            <td>{{ $geo['country'] }}</td>
                            <td><span class="badge">{{ $geo['fans_count'] }}</span></td>
                            <td>{{ $geo['favorites_count'] }}</td>
                            <td><span class="chip chip-green">{{ $geo['potential_buyers'] }}</span></td>
                            <td>{{ number_format($geo['total_revenue'], 0) }} RON</td>
                            <td>{{ $geo['recommended_venue'] ?? '—' }}</td>
                            <td>{{ $geo['recommended_capacity'] ? number_format($geo['recommended_capacity']) : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif

        {{-- Top venues/cities/counties from ticket sales --}}
        @if(count($topVenues) || count($topCities) || count($topCounties))
        <div class="g3" style="margin-top:14px;">
            @if(count($topVenues))<div class="card"><div class="card-h">Top Venues by Sales</div><div class="card-b"><table class="tbl"><thead><tr><th>#</th><th>Venue</th><th>Tickets</th></tr></thead><tbody>@foreach($topVenues as $i=>$r)<tr><td>{{ $i+1 }}</td><td>{{ $r->name }}</td><td><span class="badge">{{ number_format($r->tickets_count) }}</span></td></tr>@endforeach</tbody></table></div></div>@endif
            @if(count($topCities))<div class="card"><div class="card-h">Top Cities</div><div class="card-b"><table class="tbl"><thead><tr><th>#</th><th>City</th><th>Tickets</th></tr></thead><tbody>@foreach($topCities as $i=>$r)<tr><td>{{ $i+1 }}</td><td>{{ $r->name }}</td><td><span class="badge">{{ number_format($r->tickets_count) }}</span></td></tr>@endforeach</tbody></table></div></div>@endif
            @if(count($topCounties))<div class="card"><div class="card-h">Top Counties</div><div class="card-b"><table class="tbl"><thead><tr><th>#</th><th>County</th><th>Tickets</th></tr></thead><tbody>@foreach($topCounties as $i=>$r)<tr><td>{{ $i+1 }}</td><td>{{ $r->name }}</td><td><span class="badge">{{ number_format($r->tickets_count) }}</span></td></tr>@endforeach</tbody></table></div></div>@endif
        </div>
        @endif

        {{-- Venue Forecast Tool --}}
        <div class="card" style="margin-top:14px;">
            <div class="card-h">Venue Forecast — Analyze artist × venue potential</div>
            <div class="card-b">
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" wire:model.live.debounce.300ms="venueSearch" placeholder="Search venue by name..." style="flex:1;padding:8px 12px;border-radius:8px;background:#0b122a;border:1px solid var(--ring);color:var(--text);font-size:13px;">
                </div>

                @if(!empty($venueResults))
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach($venueResults as $vr)
                        <button wire:click="analyzeVenue({{ $vr['id'] }})" class="chip {{ $selectedVenueId == $vr['id'] ? 'chip-green' : 'chip-blue' }}" style="cursor:pointer;border:1px solid var(--ring);background:{{ $selectedVenueId == $vr['id'] ? 'rgba(34,197,94,.15)' : 'rgba(122,162,255,.1)' }};">{{ $vr['label'] }}</button>
                    @endforeach
                </div>
                @endif

                @if($venueAnalysis)
                <div style="margin-top:16px;">
                    <div class="kpi-grid kpi-grid-4" style="margin-bottom:14px;">
                        <div class="kpi"><div class="l">{{ $venueAnalysis['venue_name'] }}</div><div class="v" style="font-size:14px;">{{ $venueAnalysis['city'] }}</div></div>
                        <div class="kpi"><div class="l">Venue Capacity</div><div class="v">{{ number_format($venueAnalysis['capacity']) }}</div></div>
                        <div class="kpi"><div class="l">Venue Avg Fill</div><div class="v">{{ $venueAnalysis['venue_avg_fill'] }}%</div></div>
                        <div class="kpi"><div class="l">Total Events at Venue</div><div class="v">{{ $venueAnalysis['venue_total_events'] }}</div></div>
                    </div>

                    @if($venueAnalysis['has_history'])
                        {{-- Artist HAS performed here --}}
                        <div class="card" style="margin-bottom:14px;border-color:rgba(34,197,94,.2);">
                            <div class="card-h" style="color:var(--success);">Artist History at this Venue ({{ count($venueAnalysis['history']) }} events)</div>
                            <div class="card-b">
                                <div class="g2" style="margin-bottom:12px;">
                                    <div class="kpi"><div class="l">Avg Sell-Through</div><div class="v" style="color:var(--success);">{{ $venueAnalysis['history_avg_st'] }}%</div></div>
                                    <div class="kpi"><div class="l">Avg Revenue</div><div class="v" style="color:var(--success);">{{ number_format($venueAnalysis['history_avg_revenue']) }} RON</div></div>
                                </div>
                                <table class="tbl">
                                    <thead><tr><th>Date</th><th>Event</th><th>Sold/Cap</th><th>Sell-Through</th><th>Revenue</th></tr></thead>
                                    <tbody>
                                        @foreach($venueAnalysis['history'] as $h)
                                        <tr>
                                            <td style="white-space:nowrap;">{{ $h['date'] ? \Carbon\Carbon::parse($h['date'])->format('d M Y') : '—' }}</td>
                                            <td>{{ $h['title'] }}</td>
                                            <td>{{ number_format($h['sold']) }}/{{ $h['capacity'] ?: '?' }}</td>
                                            <td>@if($h['sell_through'] !== null)<div style="display:flex;align-items:center;gap:5px;"><div class="progress"><div class="progress-fill" style="width:{{ min($h['sell_through'],100) }}%;background:{{ $h['sell_through']>=80?'var(--success)':($h['sell_through']>=50?'var(--warn)':'var(--danger)') }};"></div></div><span style="font-size:11px;">{{ $h['sell_through'] }}%</span></div>@else — @endif</td>
                                            <td>{{ number_format($h['revenue']) }} RON</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        {{-- Artist has NOT performed here — Forecast --}}
                        @php $fc = $venueAnalysis['forecast']; @endphp
                        <div class="card" style="border-color:rgba(34,211,238,.2);">
                            <div class="card-h" style="color:var(--accent);">Forecast — Artist has not performed here</div>
                            <div class="card-b">
                                <div class="kpi-grid kpi-grid-4" style="margin-bottom:12px;">
                                    <div class="kpi"><div class="l">Fans in {{ $venueAnalysis['city'] }}</div><div class="v">{{ $fc['fans_in_city'] }}</div></div>
                                    <div class="kpi"><div class="l">Est. Demand</div><div class="v" style="color:var(--accent);">{{ $fc['estimated_demand'] }}</div></div>
                                    <div class="kpi"><div class="l">Similar Artists</div><div class="v">{{ $fc['similar_events'] }} events</div></div>
                                    <div class="kpi"><div class="l">Capacity Utilization</div><div class="v">{{ $fc['capacity_utilization'] }}%</div></div>
                                </div>
                                @if($fc['similar_events'] > 0)
                                <div style="font-size:12px;color:var(--muted);">Similar artists averaged <strong style="color:var(--text);">{{ $fc['similar_avg_sold'] }}</strong> tickets sold ({{ $fc['similar_avg_st'] }}% sell-through) at this venue.</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════ TAB: UPCOMING ═══════ --}}
    <div x-show="tab === 'upcoming'" x-cloak>
        @if(empty($upcomingEvents))
            <div class="card"><div class="card-b" style="color:var(--muted);text-align:center;padding:32px;">No upcoming events.</div></div>
        @else
            <div class="card"><div class="card-b" style="overflow-x:auto;">
                <table class="tbl">
                    <thead><tr><th>Date</th><th>Event</th><th>Venue</th><th>City</th><th>Sold/Cap</th><th>Sell-Through</th><th>Revenue</th><th>Days</th><th>Hist. Avg</th><th>Forecast</th></tr></thead>
                    <tbody>
                        @foreach($upcomingEvents as $ue)
                        <tr>
                            <td style="white-space:nowrap;">{{ $ue['date'] ? \Carbon\Carbon::parse($ue['date'])->format('d M Y') : '—' }}</td>
                            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $ue['title'] }} @if($ue['is_headliner'])<span class="chip chip-yellow" style="font-size:9px;padding:1px 5px;">H</span>@endif</td>
                            <td>{{ $ue['venue'] ?? '—' }}</td>
                            <td>{{ $ue['city'] ?? '—' }}</td>
                            <td style="white-space:nowrap;">{{ number_format($ue['sold']) }}/{{ $ue['capacity'] ?: '?' }}</td>
                            <td>@if($ue['sell_through'] !== null)<div style="display:flex;align-items:center;gap:5px;"><div class="progress"><div class="progress-fill" style="width:{{ min($ue['sell_through'],100) }}%;background:{{ $ue['sell_through']>=80?'var(--success)':($ue['sell_through']>=50?'var(--warn)':'var(--danger)') }};"></div></div><span style="font-size:11px;">{{ $ue['sell_through'] }}%</span></div>@else — @endif</td>
                            <td style="color:var(--success);">{{ number_format($ue['revenue_sold'], 0) }}</td>
                            <td>@if($ue['days_until'] !== null)<span class="chip {{ $ue['days_until'] <= 7 ? 'chip-red' : 'chip-gray' }}">{{ round($ue['days_until']) }}d</span>@else — @endif</td>
                            <td style="font-size:11px;color:var(--muted);">{{ $ue['hist_avg_sold'] }} · {{ $ue['hist_avg_sell_through'] }}%</td>
                            <td>@if($ue['forecast_sold'] !== null)<strong style="color:var(--accent);">~{{ number_format($ue['forecast_sold']) }}</strong>@if($ue['capacity'] > 0)<span style="font-size:10px;color:var(--muted);"> ({{ round($ue['forecast_sold']/$ue['capacity']*100) }}%)</span>@endif @else — @endif</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif

        {{-- Event Analyzer --}}
        @if(!empty($upcomingEvents))
        <div class="card" style="margin-top:14px;">
            <div class="card-h">Event Analyzer — Select an event for deep analysis</div>
            <div class="card-b">
                <select wire:change="analyzeEvent($event.target.value)" style="width:100%;padding:8px 12px;border-radius:8px;background:#0b122a;border:1px solid var(--ring);color:var(--text);font-size:13px;">
                    <option value="">Choose an upcoming event...</option>
                    @foreach($upcomingEvents as $ue)
                        <option value="{{ $ue['id'] }}" {{ $selectedEventId == $ue['id'] ? 'selected' : '' }}>{{ $ue['title'] }} — {{ $ue['date'] ? \Carbon\Carbon::parse($ue['date'])->format('d M Y') : '' }} ({{ $ue['venue'] ?? '' }})</option>
                    @endforeach
                </select>

                @if($eventAnalysis)
                <div style="margin-top:16px;">
                    <div class="kpi-grid kpi-grid-5" style="margin-bottom:14px;">
                        <div class="kpi"><div class="l">Current Sold</div><div class="v">{{ number_format($eventAnalysis['sold']) }}</div></div>
                        <div class="kpi"><div class="l">Capacity</div><div class="v">{{ $eventAnalysis['capacity'] ?: '?' }}</div></div>
                        <div class="kpi"><div class="l">Sell-Through</div><div class="v">{{ $eventAnalysis['sell_through'] ?? 0 }}%</div></div>
                        <div class="kpi"><div class="l">Revenue</div><div class="v" style="color:var(--success);">{{ number_format($eventAnalysis['revenue']) }} RON</div></div>
                        <div class="kpi"><div class="l">Days Left</div><div class="v">{{ $eventAnalysis['days_until'] ?? '?' }}d</div></div>
                    </div>

                    {{-- Prediction --}}
                    @if(!empty($eventAnalysis['prediction']['avg_sell_through']))
                    <div class="card" style="margin-bottom:14px;border-color:rgba(34,211,238,.2);">
                        <div class="card-h" style="color:var(--accent);">Prediction (based on {{ count($eventAnalysis['comparables']) }} comparable events)</div>
                        <div class="card-b">
                            <div class="kpi-grid kpi-grid-4">
                                <div class="kpi"><div class="l">Min Scenario</div><div class="v" style="color:var(--danger);">{{ $eventAnalysis['prediction']['min_sell_through'] }}%</div></div>
                                <div class="kpi"><div class="l">Avg Scenario</div><div class="v" style="color:var(--warn);">{{ $eventAnalysis['prediction']['avg_sell_through'] }}%</div></div>
                                <div class="kpi"><div class="l">Max Scenario</div><div class="v" style="color:var(--success);">{{ $eventAnalysis['prediction']['max_sell_through'] }}%</div></div>
                                <div class="kpi"><div class="l">Pace Forecast</div><div class="v" style="color:var(--accent);">~{{ number_format($eventAnalysis['prediction']['pace_forecast'] ?? 0) }}</div></div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Comparable events --}}
                    @if(!empty($eventAnalysis['comparables']))
                    <div class="card">
                        <div class="card-h">Comparable Past Events</div>
                        <div class="card-b" style="overflow-x:auto;">
                            <table class="tbl">
                                <thead><tr><th>Date</th><th>Event</th><th>Venue</th><th>Sold/Cap</th><th>Sell-Through</th><th>Revenue</th></tr></thead>
                                <tbody>
                                    @foreach($eventAnalysis['comparables'] as $comp)
                                    <tr>
                                        <td style="white-space:nowrap;">{{ $comp['date'] ? \Carbon\Carbon::parse($comp['date'])->format('d M Y') : '—' }}</td>
                                        <td>{{ $comp['title'] }}</td>
                                        <td>{{ $comp['venue'] ?? '—' }} <span style="color:var(--muted);font-size:10px;">{{ $comp['city'] ?? '' }}</span></td>
                                        <td>{{ number_format($comp['sold']) }}/{{ $comp['capacity'] ?: '?' }}</td>
                                        <td>@if($comp['sell_through'] !== null)<div style="display:flex;align-items:center;gap:5px;"><div class="progress"><div class="progress-fill" style="width:{{ min($comp['sell_through'],100) }}%;background:{{ $comp['sell_through']>=80?'var(--success)':($comp['sell_through']>=50?'var(--warn)':'var(--danger)') }};"></div></div><span style="font-size:11px;">{{ $comp['sell_through'] }}%</span></div>@else — @endif</td>
                                        <td>{{ number_format($comp['revenue']) }} RON</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════ TAB: EXPANSION ═══════ --}}
    <div x-show="tab === 'expansion'" x-cloak>
        @if(empty($expansion))
            <div class="card"><div class="card-b" style="color:var(--muted);text-align:center;padding:32px;">No expansion opportunities.</div></div>
        @else
            <div class="card"><div class="card-b" style="overflow-x:auto;">
                <table class="tbl">
                    <thead><tr><th>City</th><th>Country</th><th>Fans</th><th>Est. Demand</th><th>Best Venue (cap)</th><th>Similar Artists</th><th>Confidence</th></tr></thead>
                    <tbody>
                        @foreach($expansion as $exp)
                        <tr>
                            <td><strong>{{ $exp['city'] }}</strong></td>
                            <td>{{ $exp['country'] }}</td>
                            <td><span class="badge">{{ $exp['fan_count'] }}</span></td>
                            <td>{{ $exp['estimated_demand'] }}</td>
                            <td>@if(!empty($exp['venues'])){{ $exp['venues'][0]['name'] ?? '—' }} <span style="color:var(--muted);font-size:11px;">({{ number_format($exp['venues'][0]['capacity'] ?? 0) }})</span>@else — @endif</td>
                            <td>@if($exp['similar_events'] > 0){{ $exp['similar_events'] }}ev · {{ $exp['similar_avg_attendance'] }}avg · {{ $exp['similar_sell_through'] }}%@else <span style="color:var(--muted);">No data</span>@endif</td>
                            <td>@php $cc = match($exp['confidence']) { 'high' => 'green', 'medium' => 'yellow', default => 'red' }; @endphp<span class="chip chip-{{ $cc }}">{{ ucfirst($exp['confidence']) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif
    </div>

    {{-- ═══════ TAB: MEDIA & SOCIAL ═══════ --}}
    <div x-show="tab === 'media'" x-cloak>
        {{-- Followers --}}
        <div class="card" style="margin-bottom:14px;">
            <div class="card-h">Social Stats</div>
            <div class="card-b">
                <div class="follower-row">
                    @foreach($followers as $label => $val)
                        <div class="f-pill"><span class="n">{{ $label }}:</span><span class="v">{{ $val !== null ? number_format($val) : '—' }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Social links --}}
        <div class="card" style="margin-bottom:14px;">
            <div class="card-h">Links</div>
            <div class="card-b">
                <div class="link-row">
                    @foreach($socialLinks as $label => $url)
                        @if($url) <div class="link-pill"><a href="{{ $url }}" target="_blank">{{ $label }}</a></div> @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Contact --}}
        @if($record->email || $record->phone || $country)
        <div class="card" style="margin-bottom:14px;">
            <div class="card-h">Contact</div>
            <div class="card-b" style="font-size:13px;">
                @if($record->email) <div style="margin-bottom:4px;"><span style="color:var(--muted);">Email:</span> <a href="mailto:{{ $record->email }}">{{ $record->email }}</a></div> @endif
                @if($record->phone) <div style="margin-bottom:4px;"><span style="color:var(--muted);">Phone:</span> <a href="tel:{{ preg_replace('/\s+/', '', $record->phone) }}">{{ $record->phone }}</a></div> @endif
                @if($country) <div><span style="color:var(--muted);">Location:</span> {{ $city ? $city.' · ' : '' }}{{ $country }}</div> @endif
            </div>
        </div>
        @endif

        {{-- Bio --}}
        @if($bioHtml)
        <div class="card" style="margin-bottom:14px;">
            <div class="card-h">Bio</div>
            <div class="card-b"><div style="line-height:1.7;color:#dbe6ff;font-size:13px;">{!! $bioHtml !!}</div></div>
        </div>
        @endif

        {{-- Spotify embed --}}
        @if($spotifyArtistId)
        <div class="card" style="margin-bottom:14px;">
            <div class="card-b" style="padding:0;">
                <iframe style="border-radius:12px;" src="https://open.spotify.com/embed/artist/{{ $spotifyArtistId }}?utm_source=generator" width="100%" height="352" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
            </div>
        </div>
        @endif

        {{-- Videos --}}
        @if(count($videos))
        <div class="card">
            <div class="card-h">YouTube Videos</div>
            <div class="card-b">
                <div class="g2">
                    @foreach($videos as $videoUrl)
                        <div style="position:relative;width:100%;aspect-ratio:16/9;border-radius:10px;overflow:hidden;border:1px solid var(--ring);">
                            <iframe src="{{ $videoUrl }}" title="Video" frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;"></iframe>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Artist image --}}
        @if($record->main_image_full_url ?? $record->portrait_full_url ?? null)
        <div class="card" style="margin-top:14px;">
            <div class="card-b" style="padding:0;overflow:hidden;border-radius:12px;">
                <img src="{{ $record->main_image_full_url ?: $record->portrait_full_url }}" alt="{{ $record->name }}" style="width:100%;max-height:400px;object-fit:cover;">
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════ TAB: EVENTS HISTORY ═══════ --}}
    <div x-show="tab === 'history'" x-cloak>
        @if($artistEvents->count())
        <div class="card" style="margin-bottom:14px;">
            <div class="card-h">All Events ({{ $artistEvents->count() }})</div>
            <div class="card-b" style="overflow-x:auto;">
                <table class="tbl">
                    <thead><tr><th>Date</th><th>Event</th><th>Venue</th><th>City</th><th>Tenant</th></tr></thead>
                    <tbody>
                        @foreach($artistEvents as $event)
                        <tr>
                            <td style="white-space:nowrap;">{{ $event->event_date ? \Carbon\Carbon::parse($event->event_date)->format('d M Y') : '—' }}</td>
                            <td>{{ $event->getTranslation('title', app()->getLocale()) ?? '—' }}</td>
                            <td>{{ $event->venue ? ($event->venue->getTranslation('name', app()->getLocale()) ?? (is_array($event->venue->name) ? ($event->venue->name['en'] ?? array_values($event->venue->name)[0] ?? '—') : ($event->venue->name ?? '—'))) : '—' }}</td>
                            <td>{{ $event->venue?->city ?? '—' }}</td>
                            <td>{{ $event->tenant?->public_name ?? $event->tenant?->name ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Venues + Tenants --}}
        <div class="g2">
            @if($artistVenues->count())
            <div class="card">
                <div class="card-h">Venues ({{ $artistVenues->count() }})</div>
                <div class="card-b"><table class="tbl"><thead><tr><th>Venue</th><th>City</th><th>Country</th></tr></thead><tbody>
                    @foreach($artistVenues as $v)
                    <tr><td>{{ $v->getTranslation('name', app()->getLocale()) ?? (is_array($v->name) ? ($v->name['en'] ?? array_values($v->name)[0] ?? '—') : ($v->name ?? '—')) }}</td><td>{{ $v->city ?? '—' }}</td><td>{{ $v->country ?? '—' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </div>
            @endif
            @if($artistTenants->count())
            <div class="card">
                <div class="card-h">Tenants ({{ $artistTenants->count() }})</div>
                <div class="card-b"><table class="tbl"><thead><tr><th>Tenant</th><th>Country</th></tr></thead><tbody>
                    @foreach($artistTenants as $t)
                    <tr><td>{{ $t->public_name ?? $t->name ?? '—' }}</td><td>{{ $t->country ?? '—' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </div>
            @endif
        </div>
    </div>

</div>

@php
    $monthsSafe  = (is_array($months) && count($months)) ? array_values($months) : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $eventsSafe  = is_array($events)  ? array_values($events)  : [];
    $ticketsSafe = is_array($tickets) ? array_values($tickets) : [];
    $revenueSafe = is_array($revenue) ? array_values($revenue) : [];
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(function () {
    const M = @js($monthsSafe), E = @js($eventsSafe), T = @js($ticketsSafe), R = @js($revenueSafe);
    const safe = (a,n=12) => Array.isArray(a)&&a.length?a:new Array(n).fill(0);
    const opts = {responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#a7b0c3',font:{size:10}},grid:{color:'rgba(122,162,255,.06)'}},y:{beginAtZero:true,ticks:{color:'#a7b0c3',font:{size:10}},grid:{color:'rgba(122,162,255,.06)'}}}};
    const mk = (id,d,c) => {const el=document.getElementById(id);if(!el||!window.Chart)return;new Chart(el,{type:'line',data:{labels:M,datasets:[{data:safe(d),borderColor:c,backgroundColor:c+'22',tension:.35,pointRadius:2,borderWidth:2,fill:true}]},options:opts});};
    mk('eventsChart',E,'#22d3ee');mk('ticketsChart',T,'#7aa2ff');mk('revenueChart',R,'#22c55e');

    // Age doughnut
    const ageDist = @js($aTotals['age_distribution'] ?? []);
    if(Object.keys(ageDist).length&&document.getElementById('ageDistChart')){new Chart(document.getElementById('ageDistChart'),{type:'doughnut',data:{labels:Object.keys(ageDist),datasets:[{data:Object.values(ageDist),backgroundColor:['#22d3ee','#7aa2ff','#c084fc','#fbbf24','#22c55e','#ef4444'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{color:'#cdd7f6',font:{size:11}}}}}});}

    // Gender bar
    const gd = @js($aTotals['gender_overall'] ?? []);
    if(Object.keys(gd).length&&document.getElementById('genderChart')){new Chart(document.getElementById('genderChart'),{type:'bar',data:{labels:Object.keys(gd).map(g=>g.charAt(0).toUpperCase()+g.slice(1)),datasets:[{data:Object.values(gd),backgroundColor:['#7aa2ff','#c084fc','#22d3ee','#fbbf24'],borderWidth:0,borderRadius:6}]},options:{...opts,indexAxis:'y',plugins:{legend:{display:false}}}});}

    // Loyalty doughnut
    const ly = @js($loyalty ?? []);
    if((ly.one_time||ly.repeat||ly.superfan)&&document.getElementById('loyaltyChart')){new Chart(document.getElementById('loyaltyChart'),{type:'doughnut',data:{labels:['One-time','Repeat','Superfan'],datasets:[{data:[ly.one_time||0,ly.repeat||0,ly.superfan||0],backgroundColor:['#94a3b8','#7aa2ff','#fbbf24'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}});}

    // Channel doughnut
    const ch = @js($channels ?? []);
    const chL=[],chD=[],chC={'web':'#7aa2ff','pos':'#fbbf24','app':'#22d3ee','pos_app':'#c084fc','api':'#94a3b8','marketplace':'#22c55e'};
    for(const[s,i]of Object.entries(ch)){chL.push(s.toUpperCase());chD.push(i.orders_count||0);}
    if(chD.length&&document.getElementById('channelChart')){new Chart(document.getElementById('channelChart'),{type:'doughnut',data:{labels:chL,datasets:[{data:chD,backgroundColor:Object.keys(ch).map(s=>chC[s]||'#94a3b8'),borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{color:'#cdd7f6',font:{size:11}}}}}});}

    // Timing bar
    const tm = @js($timing ?? []);
    if(Object.values(tm).some(v=>v>0)&&document.getElementById('timingChart')){new Chart(document.getElementById('timingChart'),{type:'bar',data:{labels:['Last min','Last week','Last month','Early bird','Super early'],datasets:[{data:[tm.last_minute||0,tm.last_week||0,tm.last_month||0,tm.early_bird||0,tm.super_early||0],backgroundColor:['#ef4444','#fbbf24','#22c55e','#7aa2ff','#c084fc'],borderWidth:0,borderRadius:6}]},options:{...opts,indexAxis:'y',plugins:{legend:{display:false}}}});}

    // Price sensitivity
    const ps = @js($priceSens ?? []);
    if(ps.length&&document.getElementById('priceChart')){new Chart(document.getElementById('priceChart'),{type:'bar',data:{labels:ps.map(p=>p.range+' RON'),datasets:[{label:'Tickets',data:ps.map(p=>p.tickets),backgroundColor:'#7aa2ff88',borderColor:'#7aa2ff',borderWidth:1,borderRadius:4,yAxisID:'y'},{label:'Sell-Through %',data:ps.map(p=>p.sell_through),type:'line',borderColor:'#22c55e',backgroundColor:'#22c55e33',tension:.3,pointRadius:4,borderWidth:2,yAxisID:'y1'}]},options:{...opts,scales:{...opts.scales,y1:{position:'right',beginAtZero:true,max:100,ticks:{color:'#22c55e',callback:v=>v+'%'},grid:{display:false}}}}});}

})();
</script>
@endpush
