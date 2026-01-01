<div
    class="min-h-screen bg-gray-950 text-white overflow-hidden relative"
    wire:poll.3s="refreshData"
    x-data="{
        particles: [],
        init() {
            this.initParticles();
            this.animateParticles();
        },
        initParticles() {
            for (let i = 0; i < 50; i++) {
                this.particles.push({
                    x: Math.random() * 100,
                    y: Math.random() * 100,
                    size: Math.random() * 3 + 1,
                    speedX: (Math.random() - 0.5) * 0.1,
                    speedY: (Math.random() - 0.5) * 0.1,
                    opacity: Math.random() * 0.5 + 0.2
                });
            }
        },
        animateParticles() {
            setInterval(() => {
                this.particles.forEach(p => {
                    p.x += p.speedX;
                    p.y += p.speedY;
                    if (p.x < 0) p.x = 100;
                    if (p.x > 100) p.x = 0;
                    if (p.y < 0) p.y = 100;
                    if (p.y > 100) p.y = 0;
                });
            }, 50);
        }
    }"
>
    {{-- Background Effects --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <template x-for="(particle, index) in particles" :key="index">
            <div class="absolute rounded-full bg-cyan-400" :style="`left: ${particle.x}%; top: ${particle.y}%; width: ${particle.size}px; height: ${particle.size}px; opacity: ${particle.opacity};`"></div>
        </template>
    </div>
    <div class="fixed inset-0 pointer-events-none opacity-10">
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(6, 182, 212, 0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.3) 1px, transparent 1px); background-size: 50px 50px;"></div>
    </div>
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute w-full h-1 bg-gradient-to-r from-transparent via-cyan-400 to-transparent opacity-30 animate-scan"></div>
    </div>

    {{-- Header --}}
    <header class="relative z-10 border-b border-cyan-900/50 bg-gray-950/80 backdrop-blur-xl">
        <div class="max-w-[1920px] mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center animate-pulse-glow">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold bg-gradient-to-r from-cyan-400 via-blue-400 to-purple-500 bg-clip-text text-transparent">T.I.X.E.L.L.O</h1>
                            <p class="text-[10px] text-cyan-400/60 font-mono tracking-wider">INTELLIGENCE MONITOR v2.1</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-900/50 border border-cyan-900/30">
                        <div class="relative">
                            <div class="w-2 h-2 rounded-full {{ $isStreaming ? 'bg-emerald-400' : 'bg-amber-400' }}"></div>
                            <div class="absolute inset-0 w-2 h-2 rounded-full {{ $isStreaming ? 'bg-emerald-400' : 'bg-amber-400' }} animate-ping"></div>
                        </div>
                        <span class="text-[10px] font-mono {{ $isStreaming ? 'text-emerald-400' : 'text-amber-400' }}">{{ $isStreaming ? 'LIVE' : 'PAUSED' }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select wire:model.live="selectedTenant" class="appearance-none bg-gray-900/50 border border-cyan-900/50 rounded-lg px-3 py-1.5 pr-8 text-cyan-100 text-xs font-mono focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 focus:outline-none">
                        <option value="all">ALL TENANTS</option>
                        @foreach($tenants as $id => $name)
                            <option value="{{ $id }}">{{ strtoupper($name) }}</option>
                        @endforeach
                    </select>
                    <button wire:click="toggleStreaming" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs {{ $isStreaming ? 'bg-cyan-600 text-white' : 'bg-gray-800 text-gray-300' }}">
                        @if($isStreaming)
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>
                        @else
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        @endif
                    </button>
                    <a href="{{ route('admin.intelligence-monitor.fullscreen') }}" target="_blank" class="p-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    </a>
                    <div class="text-right font-mono">
                        <div class="text-sm text-cyan-400" x-data x-text="new Date().toLocaleTimeString()" x-init="setInterval(() => $el.textContent = new Date().toLocaleTimeString(), 1000)"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="relative z-10 max-w-[1920px] mx-auto px-4 py-4 space-y-4">
        {{-- Row 1: AI Insights + Stats --}}
        <div class="grid grid-cols-12 gap-4">
            {{-- AI Insights Panel --}}
            <div class="col-span-4">
                <div class="relative h-full">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-purple-600/50 to-pink-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-purple-900/50 h-full overflow-hidden">
                        <div class="px-4 py-2 border-b border-purple-900/30 flex items-center gap-2">
                            <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            <h2 class="text-xs font-bold text-purple-400 font-mono tracking-wider">AI INSIGHTS</h2>
                        </div>
                        <div class="p-3 space-y-2 max-h-[200px] overflow-y-auto custom-scrollbar">
                            @forelse($aiInsights as $insight)
                                <div class="flex items-start gap-3 p-2 rounded-lg {{ $insight['type'] === 'positive' ? 'bg-emerald-500/10 border border-emerald-500/20' : ($insight['type'] === 'warning' ? 'bg-amber-500/10 border border-amber-500/20' : ($insight['type'] === 'negative' ? 'bg-rose-500/10 border border-rose-500/20' : 'bg-gray-800/50 border border-gray-700/50')) }}">
                                    <div class="flex-shrink-0 mt-0.5">
                                        @if($insight['type'] === 'positive')
                                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                        @elseif($insight['type'] === 'warning')
                                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        @elseif($insight['type'] === 'negative')
                                            <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                                        @else
                                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium {{ $insight['type'] === 'positive' ? 'text-emerald-300' : ($insight['type'] === 'warning' ? 'text-amber-300' : ($insight['type'] === 'negative' ? 'text-rose-300' : 'text-gray-300')) }}">{{ $insight['message'] }}</p>
                                        <p class="text-[10px] text-gray-500 mt-0.5">{{ $insight['detail'] }}</p>
                                    </div>
                                    @if($insight['priority'] === 'high')
                                        <span class="px-1.5 py-0.5 text-[9px] font-mono bg-red-500/20 text-red-400 rounded">HIGH</span>
                                    @endif
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                    <p class="text-xs font-mono">ANALYZING DATA...</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stats Grid --}}
            <div class="col-span-8 grid grid-cols-4 gap-3">
                @php
                    $stats = [
                        ['label' => 'EVENTS TODAY', 'value' => $systemStats['events_today'] ?? 0, 'color' => 'cyan', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                        ['label' => 'VISITORS', 'value' => $systemStats['visitors_today'] ?? 0, 'color' => 'emerald', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                        ['label' => 'CONVERSIONS', 'value' => $systemStats['conversions_today'] ?? 0, 'color' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => 'REVENUE', 'value' => '€' . number_format($systemStats['revenue_today'] ?? 0, 0), 'color' => 'amber', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => 'EVENTS/MIN', 'value' => $systemStats['events_per_minute'] ?? 0, 'color' => 'violet', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                        ['label' => 'THIS HOUR', 'value' => $systemStats['events_this_hour'] ?? 0, 'color' => 'blue', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => 'ALERTS', 'value' => $systemStats['active_alerts'] ?? 0, 'color' => 'orange', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
                        ['label' => 'AT-RISK', 'value' => $systemStats['at_risk_customers'] ?? 0, 'color' => 'rose', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
                    ];
                @endphp
                @foreach($stats as $stat)
                    <div class="relative group">
                        <div class="absolute -inset-0.5 bg-gradient-to-r from-{{ $stat['color'] }}-600 to-{{ $stat['color'] }}-400 rounded-lg blur opacity-20 group-hover:opacity-40 transition"></div>
                        <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-3 border border-{{ $stat['color'] }}-900/50">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-mono text-{{ $stat['color'] }}-400/70">{{ $stat['label'] }}</span>
                                <svg class="w-4 h-4 text-{{ $stat['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/></svg>
                            </div>
                            <div class="text-xl font-bold text-white font-mono">{{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Row 2: Historical Trends + Revenue Forecast --}}
        <div class="grid grid-cols-12 gap-4">
            {{-- Historical Trends --}}
            <div class="col-span-6">
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-600/50 to-cyan-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-blue-900/50 overflow-hidden">
                        <div class="px-4 py-2 border-b border-blue-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <h2 class="text-xs font-bold text-blue-400 font-mono tracking-wider">24H ACTIVITY TREND</h2>
                            </div>
                            <div class="flex items-center gap-4 text-[10px] font-mono">
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                                    <span class="text-gray-400">Today: {{ number_format($historicalTrends['summary']['events_today'] ?? 0) }}</span>
                                    @if(($historicalTrends['summary']['events_change'] ?? 0) != 0)
                                        <span class="{{ ($historicalTrends['summary']['events_change'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ ($historicalTrends['summary']['events_change'] ?? 0) >= 0 ? '+' : '' }}{{ $historicalTrends['summary']['events_change'] ?? 0 }}%
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="h-32 flex items-end gap-1">
                                @php
                                    $maxEvents = collect($historicalTrends['hourly'] ?? [])->max('events') ?: 1;
                                @endphp
                                @foreach(($historicalTrends['hourly'] ?? []) as $index => $hour)
                                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                        <div class="w-full rounded-t transition-all duration-300 {{ $hour['is_today'] ? 'bg-gradient-to-t from-cyan-600 to-cyan-400' : 'bg-gradient-to-t from-gray-700 to-gray-600' }} hover:opacity-80" style="height: {{ max(4, ($hour['events'] / $maxEvents) * 100) }}px"></div>
                                        @if($index % 4 === 0)
                                            <span class="text-[8px] text-gray-500 font-mono">{{ $hour['label'] }}</span>
                                        @endif
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-gray-800 px-2 py-1 rounded text-[10px] font-mono opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 border border-gray-700">
                                            <div class="text-cyan-400">{{ number_format($hour['events']) }} events</div>
                                            <div class="text-emerald-400">€{{ number_format($hour['revenue'], 0) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Revenue Forecast --}}
            <div class="col-span-6">
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-600/50 to-green-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-emerald-900/50 overflow-hidden">
                        <div class="px-4 py-2 border-b border-emerald-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h2 class="text-xs font-bold text-emerald-400 font-mono tracking-wider">REVENUE FORECAST</h2>
                            </div>
                            <div class="flex items-center gap-3 text-[10px] font-mono">
                                <span class="text-gray-400">Today projected:</span>
                                <span class="text-emerald-400 font-bold">€{{ number_format($revenueForecast['today']['projected'] ?? 0, 0) }}</span>
                                @if(($revenueForecast['summary']['trend_percent'] ?? 0) != 0)
                                    <span class="{{ ($revenueForecast['summary']['trend_percent'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ ($revenueForecast['summary']['trend_percent'] ?? 0) >= 0 ? '↑' : '↓' }}{{ abs($revenueForecast['summary']['trend_percent'] ?? 0) }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="h-32 flex items-end gap-2">
                                @php
                                    $allRevenue = array_merge($revenueForecast['historical'] ?? [], $revenueForecast['forecast'] ?? []);
                                    $maxRevenue = collect($allRevenue)->max('revenue') ?: 1;
                                @endphp
                                @foreach(($revenueForecast['historical'] ?? []) as $day)
                                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                        <div class="w-full rounded-t transition-all duration-300 {{ $day['is_today'] ? 'bg-gradient-to-t from-emerald-600 to-emerald-400' : 'bg-gradient-to-t from-gray-700 to-gray-600' }}" style="height: {{ max(4, ($day['revenue'] / $maxRevenue) * 100) }}px"></div>
                                        <span class="text-[9px] text-gray-500 font-mono">{{ $day['day'] }}</span>
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-gray-800 px-2 py-1 rounded text-[10px] font-mono opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 border border-gray-700">
                                            <div class="text-emerald-400">€{{ number_format($day['revenue'], 0) }}</div>
                                            <div class="text-gray-400">{{ $day['date'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="w-px h-full bg-gray-700 mx-1"></div>
                                @foreach(($revenueForecast['forecast'] ?? []) as $day)
                                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                        <div class="w-full rounded-t bg-gradient-to-t from-emerald-900/50 to-emerald-700/50 border border-dashed border-emerald-500/30" style="height: {{ max(4, ($day['revenue'] / $maxRevenue) * 100) }}px"></div>
                                        <span class="text-[9px] text-emerald-500/50 font-mono">{{ $day['day'] }}</span>
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-gray-800 px-2 py-1 rounded text-[10px] font-mono opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 border border-gray-700">
                                            <div class="text-emerald-400">€{{ number_format($day['revenue'], 0) }}</div>
                                            <div class="text-amber-400">{{ $day['confidence'] }}% confidence</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 3: Geographic Map + Live Stream + Alerts --}}
        <div class="grid grid-cols-12 gap-4">
            {{-- Geographic Distribution --}}
            <div class="col-span-3">
                <div class="relative h-full">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-indigo-600/50 to-violet-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-indigo-900/50 h-full overflow-hidden">
                        <div class="px-4 py-2 border-b border-indigo-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h2 class="text-xs font-bold text-indigo-400 font-mono tracking-wider">GEO DISTRIBUTION</h2>
                            </div>
                            <span class="text-[10px] text-gray-500 font-mono">{{ $geoData['total_countries'] ?? 0 }} countries</span>
                        </div>
                        <div class="p-3 space-y-2 max-h-[280px] overflow-y-auto custom-scrollbar">
                            @forelse(($geoData['countries'] ?? []) as $country)
                                <div class="group">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-lg">{{ $this->getCountryFlag($country['code']) }}</span>
                                        <span class="text-xs text-gray-300 flex-1 truncate">{{ $country['name'] }}</span>
                                        <span class="text-[10px] font-mono text-indigo-400">{{ $country['percentage'] }}%</span>
                                    </div>
                                    <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-indigo-600 to-violet-500 rounded-full transition-all duration-500" style="width: {{ $country['percentage'] }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[9px] text-gray-500 mt-0.5">
                                        <span>{{ number_format($country['events']) }} events</span>
                                        <span class="text-emerald-500">€{{ number_format($country['revenue'], 0) }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p class="text-xs font-mono">NO GEO DATA</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Live Event Stream --}}
            <div class="col-span-5">
                <div class="relative h-full">
                    <div class="absolute -inset-0.5 bg-gradient-to-b from-cyan-600/50 to-blue-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-cyan-900/50 h-full overflow-hidden">
                        <div class="px-4 py-2 border-b border-cyan-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></div>
                                <h2 class="text-xs font-bold text-cyan-400 font-mono tracking-wider">LIVE EVENT STREAM</h2>
                            </div>
                            <span class="text-[10px] text-gray-500 font-mono">{{ count($recentEvents) }} events</span>
                        </div>
                        <div class="h-[280px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($recentEvents as $index => $event)
                                <div class="flex items-center gap-2 p-1.5 rounded-lg bg-gray-800/50 hover:bg-gray-800 transition-all text-xs">
                                    <div class="w-6 h-6 rounded bg-{{ $event['color'] }}-500/20 flex items-center justify-center flex-shrink-0">
                                        @switch($event['type'])
                                            @case('purchase')
                                                <svg class="w-3 h-3 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @break
                                            @case('add_to_cart')
                                                <svg class="w-3 h-3 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                @break
                                            @default
                                                <svg class="w-3 h-3 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        @endswitch
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="font-mono text-{{ $event['color'] }}-400">{{ strtoupper($event['type']) }}</span>
                                        @if($event['value'])<span class="text-emerald-400 ml-1">€{{ number_format($event['value'], 0) }}</span>@endif
                                        @if($event['content'])<span class="text-gray-500 ml-1 truncate">{{ Str::limit($event['content'], 20) }}</span>@endif
                                    </div>
                                    <span class="text-[9px] text-gray-600 flex-shrink-0">{{ $event['time_ago'] }}</span>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-full text-gray-500">
                                    <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    <span class="text-xs font-mono">AWAITING DATA</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alerts + AI Actions + Journey --}}
            <div class="col-span-4 space-y-4">
                {{-- Alerts --}}
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-orange-600/50 to-red-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-orange-900/50 overflow-hidden">
                        <div class="px-3 py-2 border-b border-orange-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></div>
                                <h2 class="text-[10px] font-bold text-orange-400 font-mono tracking-wider">ALERTS</h2>
                            </div>
                            <span class="text-[9px] text-gray-500 font-mono">{{ count($recentAlerts) }}</span>
                        </div>
                        <div class="h-[120px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($recentAlerts as $alert)
                                <div class="p-1.5 rounded bg-gray-800/50 border-l-2 border-{{ $alert['color'] }}-500">
                                    <p class="text-[10px] text-{{ $alert['color'] }}-400">{{ Str::limit($alert['message'], 40) }}</p>
                                    <p class="text-[9px] text-gray-600">{{ $alert['time_ago'] }}</p>
                                </div>
                            @empty
                                <div class="text-center py-4 text-gray-500">
                                    <p class="text-[10px] font-mono">ALL CLEAR</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Journey Transitions --}}
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-600/50 to-indigo-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-blue-900/50 overflow-hidden">
                        <div class="px-3 py-2 border-b border-blue-900/30">
                            <h2 class="text-[10px] font-bold text-blue-400 font-mono tracking-wider">JOURNEY TRANSITIONS</h2>
                        </div>
                        <div class="h-[120px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($journeyTransitions as $transition)
                                <div class="flex items-center gap-1 p-1 rounded bg-gray-800/50 text-[10px]">
                                    <span class="px-1 py-0.5 rounded bg-gray-700 text-gray-300 uppercase">{{ Str::limit($transition['from'], 6) }}</span>
                                    <svg class="w-3 h-3 text-{{ $transition['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $transition['direction'] === 'up' ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' }}" />
                                    </svg>
                                    <span class="px-1 py-0.5 rounded bg-{{ $transition['color'] }}-500/20 text-{{ $transition['color'] }}-400 uppercase">{{ Str::limit($transition['to'], 6) }}</span>
                                    <span class="text-gray-600 ml-auto">{{ $transition['time_ago'] }}</span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-gray-500">
                                    <p class="text-[10px] font-mono">TRACKING...</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Voice Indicator --}}
    <div class="fixed bottom-4 left-1/2 -translate-x-1/2 z-20">
        <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-gray-900/90 backdrop-blur border border-cyan-900/50">
            <div class="flex items-center gap-0.5">
                @for($i = 0; $i < 5; $i++)
                    <div class="w-0.5 bg-cyan-400 rounded-full animate-voice-bar" style="animation-delay: {{ $i * 100 }}ms; height: {{ rand(6, 16) }}px"></div>
                @endfor
            </div>
            <span class="text-[10px] font-mono text-cyan-400">TIXELLO AI ACTIVE</span>
            <div class="flex items-center gap-0.5">
                @for($i = 0; $i < 5; $i++)
                    <div class="w-0.5 bg-cyan-400 rounded-full animate-voice-bar" style="animation-delay: {{ ($i + 5) * 100 }}ms; height: {{ rand(6, 16) }}px"></div>
                @endfor
            </div>
        </div>
    </div>

    <style>
        @keyframes scan { 0% { top: 0%; } 100% { top: 100%; } }
        .animate-scan { animation: scan 4s linear infinite; }
        @keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 15px rgba(6, 182, 212, 0.4); } 50% { box-shadow: 0 0 30px rgba(6, 182, 212, 0.8); } }
        .animate-pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }
        @keyframes voice-bar { 0%, 100% { transform: scaleY(0.3); } 50% { transform: scaleY(1); } }
        .animate-voice-bar { animation: voice-bar 0.5s ease-in-out infinite; }
        .custom-scrollbar::-webkit-scrollbar { width: 3px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(6, 182, 212, 0.3); border-radius: 2px; }
    </style>
</div>
