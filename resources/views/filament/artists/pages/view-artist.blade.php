{{-- resources/views/filament/artists/pages/view-artist.blade.php --}}
@php
    // ==== INPUTS DIN CONTROLLER (fallback-uri hardcodate dacă lipsesc) ====
    $months   = $seriesMonths   ?? [];
    $events   = $seriesEvents   ?? [];
    $tickets  = $seriesTickets  ?? [];
    $revenue  = $seriesRevenue  ?? [];

    $types    = $record->artistTypes?->map(fn($t) => $t->getTranslation('name', app()->getLocale()))->all() ?? [];
    $genres   = $record->artistGenres?->map(fn($g) => $g->getTranslation('name', app()->getLocale()))->all() ?? [];

    $country  = $record->country ?? null;
    $city     = $record->city ?? null;

    $followers = [
        'YouTube Subscribers' => $record->followers_youtube ?? null,
        'YouTube Views' => $record->youtube_total_views ?? null,
        'Spotify Followers' => $record->spotify_monthly_listeners ?? null,
        'Spotify Popularity' => $record->spotify_popularity ?? null,
        'Facebook'  => $record->followers_facebook ?? null,
        'Instagram' => $record->followers_instagram ?? null,
        'TikTok'    => $record->followers_tiktok ?? null,
    ];

    $socialLinks = [
        'Website'   => $record->website ?? null,
        'Facebook'  => $record->facebook_url ?? null,
        'Instagram' => $record->instagram_url ?? null,
        'TikTok'    => $record->tiktok_url ?? null,
        'YouTube'   => $record->youtube_url ?? null,
        'Spotify'   => $record->spotify_url ?? null,
    ];

    $bioHtml = is_array($record->bio_html ?? null)
        ? ($record->bio_html['en'] ?? reset($record->bio_html))
        : ($record->bio_html ?? $record->bio ?? null);

    // Convert YouTube URLs to embed format
    $rawVideos = is_array($record->youtube_videos ?? null) ? $record->youtube_videos : [];
    $videos = [];
    foreach ($rawVideos as $v) {
        $url = is_array($v) ? ($v['url'] ?? '') : $v;
        if (empty($url)) continue;

        // Extract video ID from various YouTube URL formats
        $videoId = null;
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (strlen($url) === 11 && preg_match('/^[a-zA-Z0-9_-]+$/', $url)) {
            $videoId = $url; // Already just the ID
        }

        if ($videoId) {
            $videos[] = "https://www.youtube.com/embed/{$videoId}";
        }
    }

    // ==== CALCULE KPI ====
    $totalEvents  = is_array($events)  ? array_sum($events)  : 0;
    $totalTickets = is_array($tickets) ? array_sum($tickets) : 0;
    $totalRevenue = is_array($revenue) ? array_sum($revenue) : 0.0;

    $avgTicketsPerEvent = $totalEvents > 0 ? round($totalTickets / $totalEvents, 1) : 0;
    $avgTicketPrice     = $totalTickets > 0 ? round($totalRevenue / $totalTickets, 2) : 0.00;

    // ==== LISTE TOP 10 (from controller or empty) ====
    $topVenues   = $topVenues   ?? [];
    $topCities   = $topCities   ?? [];
    $topCounties = $topCounties ?? [];

    // ==== FILTER RANGE (UI only; linkuri GET) ====
    $currentRange = request()->input('range', '12m');

    // ==== SPOTIFY EMBED (use artist's ID if available) ====
    $spotifyArtistId = $record->spotify_id ?? null;

    // Lists from controller
    $artistEvents = $artistEvents ?? collect();
    $artistVenues = $artistVenues ?? collect();
    $artistTenants = $artistTenants ?? collect();
@endphp

@push('styles')
<style>
:root{ --bg:#0b1020; --bg-soft:#121a33; --card:#0f1530; --muted:#a7b0c3; --text:#e9eefb;
       --primary:#7aa2ff; --accent:#22d3ee; --success:#22c55e; --ring:rgba(122,162,255,.2); --warn:#fbbf24;}
.fi-page, body{background:var(--bg); color:var(--text);}
a{color:var(--primary); text-decoration:none} a:hover{text-decoration:underline}
img{display:block; max-width:100%}

.av-container{max-width:1200px; margin:0 auto; padding:24px;}
.av-header{position:sticky; top:64px; z-index:30; background:linear-gradient(180deg,rgba(11,16,32,.96),rgba(11,16,32,.72)); backdrop-filter:blur(6px); border-bottom:1px solid rgba(122,162,255,.15);}
.av-header-inner{max-width:1200px; margin:0 auto; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; gap:16px;}
.av-title{font-size:28px; line-height:1.1; font-weight:700; letter-spacing:.2px;}
.av-sub{margin-top:6px; color:#cdd7f6; font-size:13px;}
.av-filters{display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;margin-bottom:24px;}
.filter-chip{display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; border:1px solid var(--ring); background:linear-gradient(180deg,#172143,#131c3c); color:var(--text); font-weight:600; letter-spacing:.2px;}
.filter-chip.active{background:linear-gradient(180deg,#1f9adb,#1275e6); border-color:rgba(34,211,238,.35)}
.filter-chip:hover{text-decoration:none; filter:brightness(1.05)}
.fi-main form.filter-custom{display:inline-flex; gap:6px; align-items:center; padding:0!important;}

.av-main{padding:24px;}
.av-grid{display:grid; grid-template-columns:360px 1fr; gap:24px;}
@media (max-width:980px){.av-grid{grid-template-columns:1fr;}}

.av-card{background:linear-gradient(180deg,#10183a,#0d1431); border:1px solid rgba(122,162,255,.12); border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,.25);}
.av-card-header{padding:14px 16px; border-bottom:1px solid rgba(122,162,255,.12); font-weight:600; color:#cdd7f6;}
.av-card-body{padding:16px;}
.av-row{display:flex; flex-wrap:wrap; gap:8px; align-items:center}
.av-chip{display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:rgba(122,162,255,.1); border:1px solid rgba(122,162,255,.15); color:#dbe6ff; font-size:12px;}
.av-chip.purple{background:rgba(192,132,252,.12); border-color:rgba(192,132,252,.25);}
.av-chip.gray{background:rgba(148,163,184,.12); border-color:rgba(148,163,184,.25); color:#e2e8f0;}
.kpis{display:grid; grid-template-columns:repeat(5,1fr); gap:16px;}
@media (max-width:1200px){.kpis{grid-template-columns:repeat(2,1fr);}}
.kpi{padding:16px; border-radius:14px; background:linear-gradient(180deg,#0f1634,#0d1330); border:1px solid rgba(122,162,255,.12);}
.kpi .label{color:var(--muted); font-size:13px;}
.kpi .value{margin-top:6px; font-size:22px; font-weight:700}

.av-hero{overflow:hidden; border-radius:16px; border:1px solid rgba(122,162,255,.15);}
.av-hero img{width:100%; height:215px; object-fit:cover;}
.av-portrait{position:absolute; bottom:-32px; left:24px; width:96px; height:120px; border-radius:12px; border:3px solid var(--bg); box-shadow:0 10px 25px rgba(0,0,0,.5); object-fit:cover;}
.av-hero-wrap{position:relative;}

.list{list-style:none; padding:0; margin:0}
.list li{display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px dashed rgba(122,162,255,.12)}
.list li:last-child{border-bottom:0}

.chart-card .av-card-body{padding:6px 12px 14px;}
.chart-wrap{height:240px; width:100%;}
canvas{width:100% !important; height:240px !important;}

.video-grid{display:grid; grid-template-columns:1fr 1fr; gap:16px;}
@media (max-width:980px){.video-grid{grid-template-columns:1fr;}}
.video{position:relative; width:100%; aspect-ratio:16/9; border-radius:12px; overflow:hidden; border:1px solid rgba(122,162,255,.12); box-shadow:0 8px 20px rgba(0,0,0,.35)}
.video iframe{position:absolute; inset:0; width:100%; height:100%;}

.followers{display:grid; grid-template-columns:1fr 1fr; gap:10px;}
.f-card{border:1px solid rgba(122,162,255,.12); border-radius:12px; padding:12px; background:linear-gradient(180deg,#0f1736,#0e1532);}
.f-card .lbl{color:var(--muted); font-size:12px;}
.f-card .val{margin-top:4px; font-size:18px; font-weight:700}

.tbl{width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; border-radius:12px; border:1px solid rgba(122,162,255,.15)}
.tbl thead th{background:#0f1736; color:#cdd7f6; font-weight:600; text-align:left; padding:10px 12px; border-bottom:1px solid rgba(122,162,255,.15)}
.tbl tbody td{padding:10px 12px; border-bottom:1px dashed rgba(122,162,255,.12); color:#dbe6ff}
.tbl tbody tr:last-child td{border-bottom:0}
.badge{display:inline-flex; align-items:center; gap:6px; background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.25); padding:4px 8px; border-radius:999px; font-size:12px}

.btn{display:inline-flex; align-items:center; gap:10px; padding:9px 14px; border-radius:12px; border:1px solid var(--ring); background:linear-gradient(180deg,#0f1838,#0d1431); color:var(--text); font-weight:600; letter-spacing:.2px; box-shadow:0 6px 20px rgba(0,0,0,.3);}
.btn:hover{filter:brightness(1.06); text-decoration:none;}
.btn-ghost{background:transparent;}
.btn-elegant{background:linear-gradient(180deg,#151f45,#0f1736); border-color:rgba(122,162,255,.25);}
.btn-elegant svg{opacity:.9}

.contact-row{display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px dashed rgba(122,162,255,.12)}
.contact-row:last-child{border-bottom:0}
.contact-label{display:flex; align-items:center; gap:8px; color:#cdd7f6; min-width:120px}
.contact-value a{color:#dbe6ff}

.link-grid{display:grid; grid-template-columns:1fr 1fr; gap:10px;}
.link-item{display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:12px; background:linear-gradient(180deg,#0f1736,#0e1532); border:1px solid rgba(122,162,255,.12)}
.link-item a{color:#e9eefb; text-decoration:none}
.link-item a:hover{text-decoration:underline}

.spotify-embed{border-radius:12px; overflow:hidden; border:1px solid rgba(122,162,255,.15); box-shadow:0 8px 22px rgba(0,0,0,.35)}

.av-footer{position:sticky; bottom:0; z-index:20; backdrop-filter:blur(6px); background:linear-gradient(180deg, rgba(16,23,52,.2), rgba(16,23,52,.85)); border-top:1px solid rgba(122,162,255,.15); padding:10px 24px; display:flex; justify-content:flex-end;}
</style>
@endpush

<div id="av-root">
    {{-- Sticky header cu filtre de timp + buton Edit elegant --}}
    <div class="av-header">
        <div class="av-header-inner">
            <div>
                <div class="av-title">{{ $record->name }}</div>
                <div class="av-sub">
                    @foreach($types as $t)
                        <span class="av-chip">{{ $t }}</span>
                    @endforeach
                    @foreach($genres as $g)
                        <span class="av-chip purple">{{ $g }}</span>
                    @endforeach
                    @if($country)
                        <span class="av-chip gray">{{ $country }}@if($city) • {{ $city }}@endif</span>
                    @endif
                </div>
            </div>

            <a class="btn btn-elegant" href="{{ \App\Filament\Resources\Artists\ArtistResource::getUrl('edit', ['record' => $record->getKey()]) }}">
                {{-- feather-ish edit icon --}}
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20h4l10-10a2.828 2.828 0 1 0-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Edit
            </a>
        </div>
    </div>

    <div class="av-container av-main">
        {{-- About --}}
        @if($bioHtml)
            <div class="" style="margin-top:20px;margin-bottom:20px">
                <div class="av-card-body">
                    <div style="line-height:1.7; color:#dbe6ff">{!! $bioHtml !!}</div>
                </div>
            </div>
        @endif

        {{ number_format($kpis['tickets_sold']) }}
        {{ number_format($kpis['avg_per_event'], 2) }}
        {{ number_format($kpis['avg_ticket_price'], 2) }}
        {{ number_format($kpis['revenue_minor'] / 100, 2) }}
        
        {{-- KPIs (5) --}}
        <div class="kpis" style="margin-bottom:20px">
            <div class="kpi"><div class="label">Events (last period)</div><div class="value">{{ $totalEvents }}</div></div>
            <div class="kpi"><div class="label">Tickets sold</div><div class="value">{{ number_format($totalTickets) }}</div></div>
            <div class="kpi"><div class="label">Revenue (RON)</div><div class="value">{{ number_format($totalRevenue, 2) }}</div></div>
            <div class="kpi"><div class="label">Avg tickets / event</div><div class="value">{{ $avgTicketsPerEvent }}</div></div>
            <div class="kpi"><div class="label">Avg sold price (RON)</div><div class="value">{{ number_format($avgTicketPrice, 2) }}</div></div>
        </div>

        <div class="av-filters">
            <div class="date-filters">
            @php
                $ranges = ['30d'=>'30d','60d'=>'60d','90d'=>'90d','120d'=>'120d','6m'=>'6m','12m'=>'1y'];
            @endphp
            @foreach($ranges as $key=>$label)
                <a class="filter-chip {{ $currentRange === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['range'=>$key]) }}">{{ strtoupper($label) }}</a>
            @endforeach
            </div>

            <form method="GET" class="filter-custom" action="">
                <input type="date" name="from" style="background:#0b122a;border:1px solid rgba(122,162,255,.25);border-radius:8px;color:#e9eefb;padding:6px 8px">
                <input type="date" name="to"   style="background:#0b122a;border:1px solid rgba(122,162,255,.25);border-radius:8px;color:#e9eefb;padding:6px 8px">
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
        </div>

        <div class="av-grid">
            <div class="av-col-left">
                {{-- Hero + Portrait --}}
                <div class="av-card av-hero">
                    <div class="av-hero-wrap">
                        <img src="{{ $record->hero_image_url ?: ($record->portrait_url ?: 'https://picsum.photos/1200/400') }}" alt="{{ $record->name }}">
                        @if($record->portrait_url)
                            <img class="av-portrait" src="{{ $record->portrait_url }}" alt="{{ $record->name }}">
                        @endif
                    </div>
                </div>

                {{-- Contact (iconițe & layout) --}}
                @if($record->email || $record->phone || $country)
                    <div class="av-card" style="margin-top:24px">
                        <div class="av-card-body">
                            @if($record->email)
                                <div class="contact-row">
                                    <div class="contact-label">
                                        {{-- mail icon --}}
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4z" stroke="currentColor" stroke-width="1.6"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Email
                                    </div>
                                    <div class="contact-value"><a href="mailto:{{ $record->email }}">{{ $record->email }}</a></div>
                                </div>
                            @endif
                            @if($record->phone)
                                <div class="contact-row">
                                    <div class="contact-label">
                                        {{-- phone icon --}}
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 5a2 2 0 0 1 2-2h2l2 4-2 1a12 12 0 0 0 7 7l1-2 4 2v2a2 2 0 0 1-2 2h-1C9.82 19 5 14.18 5 8V7a2 2 0 0 1-1-2Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Phone
                                    </div>
                                    <div class="contact-value"><a href="tel:{{ preg_replace('/\s+/', '', $record->phone) }}">{{ $record->phone }}</a></div>
                                </div>
                            @endif
                            @if($country)
                                <div class="contact-row">
                                    <div class="contact-label">
                                        {{-- map pin --}}
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 21s-7-5.33-7-11a7 7 0 1 1 14 0c0 5.67-7 11-7 11Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
                                        Location
                                    </div>
                                    <div class="contact-value">{{ $city ? $city.' • ' : '' }}{{ $country }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Links (icon + link direct) --}}
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-body">
                        <div class="link-grid">
                            @foreach($socialLinks as $label => $url)
                                @if($url)
                                    <div class="link-item">
                                        @switch($label)
                                            @case('Website')
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 3c2.5 3 2.5 15 0 18M5 7.5h14M5 16.5h14" stroke="currentColor" stroke-width="1.2"/></svg>
                                                @break
                                            @case('Facebook')
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M14 9h3V6h-3a3 3 0 0 0-3 3v3H8v3h3v6h3v-6h3l1-3h-4V9a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.4"/></svg>
                                                @break
                                            @case('Instagram')
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="5" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.6"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
                                                @break
                                            @case('TikTok')
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M14 4v8.5a3.5 3.5 0 1 1-3-3.465" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M14 6a6 6 0 0 0 6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                                @break
                                            @case('YouTube')
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="10" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M11 10v4l4-2-4-2Z" fill="currentColor"/></svg>
                                                @break
                                            @case('Spotify')
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M7.5 14c3-1 6-1 9 0M8.5 11.5c3.2-.9 6.3-.9 9.5 0M9.5 9c2.7-.7 5.3-.7 8 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                                @break
                                        @endswitch
                                        <a href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Spotify Artist Embed --}}
                @if($spotifyArtistId)
                <div class="" style="margin-top:24px">
                    <div class="av-card-body">
                        <div class="spotify-embed">
                            <iframe style="border-radius:12px" src="https://open.spotify.com/embed/artist/{{ $spotifyArtistId }}?utm_source=generator"
                                width="100%" height="352" frameborder="0" allowfullscreen=""
                                allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Audience --}}
                <div class="" style="margin-top:24px">
                    <div class="av-card-body">
                        <div class="followers">
                            @foreach($followers as $label => $val)
                                <div class="f-card">
                                    <div class="lbl">{{ $label }}</div>
                                    <div class="val">{{ $val !== null ? number_format($val) : '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="av-col-right">
                {{-- KPIs Charts --}}
                <div class="av-card chart-card">
                    <div class="av-card-body">
                        <div class="chart-wrap"><canvas id="eventsChart"></canvas></div>
                        <div class="chart-wrap" style="margin-top:16px"><canvas id="ticketsChart"></canvas></div>
                        <div class="chart-wrap" style="margin-top:16px"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>

                {{-- YouTube Videos --}}
                @if(count($videos))
                    <div class="" style="margin-top:24px">
                        <div class="av-card-body">
                            <div class="video-grid">
                                @foreach($videos as $videoUrl)
                                    <div class="video">
                                        <iframe
                                            src="{{ $videoUrl }}"
                                            title="Artist video"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen
                                        ></iframe>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Events List --}}
                @if($artistEvents->count())
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-header">Events ({{ $artistEvents->count() }})</div>
                    <div class="av-card-body">
                        <table class="tbl">
                            <thead><tr><th>Date</th><th>Event</th><th>Venue</th><th>Tenant</th></tr></thead>
                            <tbody>
                                @foreach($artistEvents->take(20) as $event)
                                    <tr>
                                        <td>{{ $event->event_date ? \Carbon\Carbon::parse($event->event_date)->format('d M Y') : '—' }}</td>
                                        <td>{{ $event->getTranslation('title', app()->getLocale()) ?? $event->title ?? '—' }}</td>
                                        <td>{{ $event->venue?->name ?? '—' }}</td>
                                        <td>{{ $event->tenant?->public_name ?? $event->tenant?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Venues List --}}
                @if($artistVenues->count())
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-header">Venues ({{ $artistVenues->count() }})</div>
                    <div class="av-card-body">
                        <table class="tbl">
                            <thead><tr><th>Venue</th><th>City</th><th>Country</th></tr></thead>
                            <tbody>
                                @foreach($artistVenues as $venue)
                                    <tr>
                                        <td>{{ $venue->name ?? '—' }}</td>
                                        <td>{{ $venue->city ?? '—' }}</td>
                                        <td>{{ $venue->country ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Tenants List --}}
                @if($artistTenants->count())
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-header">Tenants ({{ $artistTenants->count() }})</div>
                    <div class="av-card-body">
                        <table class="tbl">
                            <thead><tr><th>Tenant</th><th>Country</th></tr></thead>
                            <tbody>
                                @foreach($artistTenants as $tenant)
                                    <tr>
                                        <td>{{ $tenant->public_name ?? $tenant->name ?? '—' }}</td>
                                        <td>{{ $tenant->country ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Top 10 Venue/City/County by sales --}}
                @if(count($topVenues))
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-header">Top Venues by Sales</div>
                    <div class="av-card-body">
                        <table class="tbl">
                            <thead><tr><th>#</th><th>Venue</th><th>Tickets</th></tr></thead>
                            <tbody>
                                @foreach($topVenues as $i=>$row)
                                    <tr>
                                        <td>{{ $i+1 }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td><span class="badge">{{ number_format($row->tickets_count) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                @if(count($topCities))
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-header">Top Cities by Sales</div>
                    <div class="av-card-body">
                        <table class="tbl">
                            <thead><tr><th>#</th><th>City</th><th>Tickets</th></tr></thead>
                            <tbody>
                                @foreach($topCities as $i=>$row)
                                    <tr>
                                        <td>{{ $i+1 }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td><span class="badge">{{ number_format($row->tickets_count) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                @if(count($topCounties))
                <div class="av-card" style="margin-top:24px">
                    <div class="av-card-header">Top Counties by Sales</div>
                    <div class="av-card-body">
                        <table class="tbl">
                            <thead><tr><th>#</th><th>County</th><th>Tickets</th></tr></thead>
                            <tbody>
                                @foreach($topCounties as $i=>$row)
                                    <tr>
                                        <td>{{ $i+1 }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td><span class="badge">{{ number_format($row->tickets_count) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>

    <div class="av-footer">
        <a class="btn btn-ghost" href="{{ \App\Filament\Resources\Artists\ArtistResource::getUrl('index') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M10 19l-7-7 7-7M3 12h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Back to list
        </a>
    </div>
</div>

@php
    // JS-safe arrays
    $monthsSafe  = (is_array($months) && count($months)) ? array_values($months) : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $eventsSafe  = is_array($events)  ? array_values($events)  : [];
    $ticketsSafe = is_array($tickets) ? array_values($tickets) : [];
    $revenueSafe = is_array($revenue) ? array_values($revenue) : [];
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(function () {
    const months  = @js($monthsSafe);
    const events  = @js($eventsSafe);
    const tickets = @js($ticketsSafe);
    const revenue = @js($revenueSafe);

    const safe = (arr, len=12) => Array.isArray(arr) && arr.length ? arr : new Array(len).fill(0);

    const mkLine = (id, label, data, color) => {
        const el = document.getElementById(id);
        if (!el || !window.Chart) return;
        new Chart(el, {
            type: 'line',
            data: { labels: months, datasets: [{ label, data: safe(data), borderColor: color, backgroundColor: color + '33', tension:.35, pointRadius:2, borderWidth:2, fill:true }]},
            options: {
                responsive:true, maintainAspectRatio:false, interaction:{mode:'index', intersect:false},
                plugins:{ legend:{display:true, labels:{color:'#cdd7f6'}}, tooltip:{enabled:true}},
                scales:{
                    x:{ticks:{color:'#a7b0c3'}, grid:{color:'rgba(122,162,255,.08)'}},
                    y:{beginAtZero:true, ticks:{color:'#a7b0c3'}, grid:{color:'rgba(122,162,255,.08)'}}
                }
            }
        });
    };

    mkLine('eventsChart',  'Events / month',  events,  '#22d3ee');
    mkLine('ticketsChart', 'Tickets / month', tickets, '#7aa2ff');
    mkLine('revenueChart','Revenue / month (RON)', revenue, '#22c55e');
})();
</script>
@endpush
