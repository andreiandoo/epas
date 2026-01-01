<div
    class="min-h-screen bg-gray-950 text-white overflow-hidden relative"
    wire:poll.2s="refreshData"
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
    {{-- Animated Background Particles --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <template x-for="(particle, index) in particles" :key="index">
            <div class="absolute rounded-full bg-cyan-400" :style="`left: ${particle.x}%; top: ${particle.y}%; width: ${particle.size}px; height: ${particle.size}px; opacity: ${particle.opacity};`"></div>
        </template>
    </div>

    {{-- Grid Lines Background --}}
    <div class="fixed inset-0 pointer-events-none opacity-10">
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(6, 182, 212, 0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.3) 1px, transparent 1px); background-size: 50px 50px;"></div>
    </div>

    {{-- Scan Line Effect --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute w-full h-1 bg-gradient-to-r from-transparent via-cyan-400 to-transparent opacity-30 animate-scan"></div>
    </div>

    {{-- Header --}}
    <header class="relative z-10 border-b border-cyan-900/50 bg-gray-950/80 backdrop-blur-xl">
        <div class="max-w-[1920px] mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    {{-- Logo/Title --}}
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center animate-pulse-glow">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="absolute -inset-1 bg-cyan-400/20 rounded-xl blur animate-pulse"></div>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold bg-gradient-to-r from-cyan-400 via-blue-400 to-purple-500 bg-clip-text text-transparent">
                                T.I.X.E.L.L.O
                            </h1>
                            <p class="text-xs text-cyan-400/60 font-mono tracking-wider">INTELLIGENCE MONITOR v2.0</p>
                        </div>
                    </div>

                    {{-- Status Indicator --}}
                    <div class="flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900/50 border border-cyan-900/30">
                        <div class="relative">
                            <div class="w-2 h-2 rounded-full {{ $isStreaming ? 'bg-emerald-400' : 'bg-amber-400' }}"></div>
                            <div class="absolute inset-0 w-2 h-2 rounded-full {{ $isStreaming ? 'bg-emerald-400' : 'bg-amber-400' }} animate-ping"></div>
                        </div>
                        <span class="text-xs font-mono {{ $isStreaming ? 'text-emerald-400' : 'text-amber-400' }}">
                            {{ $isStreaming ? 'STREAMING LIVE' : 'PAUSED' }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    {{-- Tenant Selector --}}
                    <div class="relative">
                        <select wire:model.live="selectedTenant" class="appearance-none bg-gray-900/50 border border-cyan-900/50 rounded-lg px-4 py-2 pr-10 text-cyan-100 text-sm font-mono focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 focus:outline-none">
                            <option value="all">ALL TENANTS</option>
                            @foreach($tenants as $id => $name)
                                <option value="{{ $id }}">{{ strtoupper($name) }}</option>
                            @endforeach
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    {{-- Stream Toggle --}}
                    <button wire:click="toggleStreaming" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-300 {{ $isStreaming ? 'bg-cyan-600 hover:bg-cyan-500 text-white' : 'bg-gray-800 hover:bg-gray-700 text-gray-300' }}">
                        @if($isStreaming)
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>
                            <span class="text-sm font-medium">PAUSE</span>
                        @else
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            <span class="text-sm font-medium">RESUME</span>
                        @endif
                    </button>

                    {{-- Fullscreen Link --}}
                    <a href="{{ route('admin.intelligence-monitor.fullscreen') }}" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 transition-colors" title="Open Fullscreen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </a>

                    {{-- Current Time --}}
                    <div class="text-right font-mono">
                        <div class="text-lg text-cyan-400" x-data x-text="new Date().toLocaleTimeString()" x-init="setInterval(() => $el.textContent = new Date().toLocaleTimeString(), 1000)"></div>
                        <div class="text-xs text-gray-500" x-data x-text="new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="relative z-10 max-w-[1920px] mx-auto px-6 py-6">
        {{-- Stats Overview --}}
        <div class="grid grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-cyan-600 to-cyan-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-cyan-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-cyan-400/70">EVENTS TODAY</span>
                        <div class="w-6 h-6 rounded bg-cyan-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ number_format($systemStats['events_today'] ?? 0) }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-600 to-blue-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-blue-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-blue-400/70">THIS HOUR</span>
                        <div class="w-6 h-6 rounded bg-blue-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ number_format($systemStats['events_this_hour'] ?? 0) }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-violet-600 to-violet-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-violet-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-violet-400/70">EVENTS/MIN</span>
                        <div class="w-6 h-6 rounded bg-violet-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ $systemStats['events_per_minute'] ?? 0 }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-600 to-emerald-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-emerald-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-emerald-400/70">VISITORS</span>
                        <div class="w-6 h-6 rounded bg-emerald-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ number_format($systemStats['visitors_today'] ?? 0) }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-green-600 to-green-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-green-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-green-400/70">CONVERSIONS</span>
                        <div class="w-6 h-6 rounded bg-green-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ number_format($systemStats['conversions_today'] ?? 0) }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-amber-600 to-amber-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-amber-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-amber-400/70">REVENUE</span>
                        <div class="w-6 h-6 rounded bg-amber-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">€{{ number_format($systemStats['revenue_today'] ?? 0, 0) }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-orange-600 to-orange-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-orange-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-orange-400/70">ALERTS</span>
                        <div class="w-6 h-6 rounded bg-orange-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ number_format($systemStats['active_alerts'] ?? 0) }}</div>
                </div>
            </div>

            <div class="relative group">
                <div class="absolute -inset-0.5 bg-gradient-to-r from-rose-600 to-rose-400 rounded-lg blur opacity-30 group-hover:opacity-50 transition duration-300"></div>
                <div class="relative bg-gray-900/80 backdrop-blur rounded-lg p-4 border border-rose-900/50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-mono text-rose-400/70">AT-RISK</span>
                        <div class="w-6 h-6 rounded bg-rose-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-white font-mono">{{ number_format($systemStats['at_risk_customers'] ?? 0) }}</div>
                </div>
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-12 gap-6">
            {{-- Live Event Stream --}}
            <div class="col-span-5">
                <div class="relative h-full">
                    <div class="absolute -inset-0.5 bg-gradient-to-b from-cyan-600/50 to-blue-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-cyan-900/50 h-full overflow-hidden">
                        <div class="px-4 py-3 border-b border-cyan-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></div>
                                <h2 class="text-sm font-bold text-cyan-400 font-mono tracking-wider">LIVE EVENT STREAM</h2>
                            </div>
                            <span class="text-xs text-gray-500 font-mono">{{ count($recentEvents) }} events</span>
                        </div>
                        <div class="h-[500px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($recentEvents as $index => $event)
                                <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-800/50 hover:bg-gray-800 transition-all duration-200 border border-transparent hover:border-cyan-500/30 animate-fade-in" style="animation-delay: {{ $index * 20 }}ms">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-{{ $event['color'] }}-500/20 flex items-center justify-center">
                                        @switch($event['type'])
                                            @case('purchase')
                                                <svg class="w-4 h-4 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @break
                                            @case('add_to_cart')
                                                <svg class="w-4 h-4 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                @break
                                            @case('view_item')
                                                <svg class="w-4 h-4 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                                @break
                                            @case('pageview')
                                                <svg class="w-4 h-4 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                @break
                                            @case('search')
                                                <svg class="w-4 h-4 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                @break
                                            @default
                                                <svg class="w-4 h-4 text-{{ $event['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        @endswitch
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono font-medium text-{{ $event['color'] }}-400">{{ strtoupper($event['type']) }}</span>
                                            @if($event['value'])
                                                <span class="text-xs font-mono text-emerald-400">€{{ number_format($event['value'], 2) }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-gray-500 truncate">
                                            @if($event['content'])
                                                <span class="truncate">{{ $event['content'] }}</span>
                                            @elseif($event['page'])
                                                <span class="truncate">/{{ $event['page'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <div class="text-[10px] font-mono text-gray-500">{{ $event['time_ago'] }}</div>
                                        <div class="flex items-center gap-1 text-[10px] text-gray-600">
                                            <span>{{ $event['location'] }}</span>
                                            <span>{{ $event['device'] === 'mobile' ? '📱' : ($event['device'] === 'tablet' ? '📟' : '💻') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-full text-gray-500">
                                    <svg class="w-12 h-12 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-sm font-mono">AWAITING DATA STREAM</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Middle Column --}}
            <div class="col-span-4 space-y-6">
                {{-- Alerts Panel --}}
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-b from-orange-600/50 to-red-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-orange-900/50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-orange-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></div>
                                <h2 class="text-sm font-bold text-orange-400 font-mono tracking-wider">INTELLIGENCE ALERTS</h2>
                            </div>
                            <span class="text-xs text-gray-500 font-mono">{{ count($recentAlerts) }} alerts</span>
                        </div>
                        <div class="h-[220px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($recentAlerts as $alert)
                                <div class="flex items-start gap-3 p-2 rounded-lg bg-gray-800/50 border-l-2 border-{{ $alert['color'] }}-500">
                                    <div class="flex-shrink-0 w-6 h-6 rounded bg-{{ $alert['color'] }}-500/20 flex items-center justify-center mt-0.5">
                                        <svg class="w-3 h-3 text-{{ $alert['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-medium text-{{ $alert['color'] }}-400">{{ $alert['message'] }}</div>
                                        <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-500">
                                            <span class="px-1.5 py-0.5 rounded bg-{{ $alert['color'] }}-500/10 text-{{ $alert['color'] }}-400 uppercase">{{ $alert['priority'] }}</span>
                                            <span>{{ $alert['tenant'] }}</span>
                                            <span>{{ $alert['time_ago'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-full text-gray-500">
                                    <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-xs font-mono">ALL SYSTEMS NOMINAL</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Event Distribution --}}
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-b from-violet-600/50 to-purple-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-violet-900/50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-violet-900/30">
                            <h2 class="text-sm font-bold text-violet-400 font-mono tracking-wider">EVENT DISTRIBUTION</h2>
                        </div>
                        <div class="p-4">
                            @php $maxCount = collect($eventTypeStats)->max('count') ?: 1; @endphp
                            <div class="space-y-2">
                                @foreach($eventTypeStats as $stat)
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 text-xs font-mono text-{{ $stat['color'] }}-400 truncate">{{ strtoupper($stat['type']) }}</div>
                                        <div class="flex-1 h-4 bg-gray-800 rounded-full overflow-hidden relative">
                                            <div class="h-full bg-gradient-to-r from-{{ $stat['color'] }}-600 to-{{ $stat['color'] }}-400 rounded-full transition-all duration-1000 ease-out relative" style="width: {{ ($stat['count'] / $maxCount) * 100 }}%">
                                                <div class="absolute inset-0 bg-white/20 animate-shimmer"></div>
                                            </div>
                                        </div>
                                        <div class="w-12 text-right text-xs font-mono text-gray-400">{{ number_format($stat['count']) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="col-span-3 space-y-6">
                {{-- AI Actions --}}
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-b from-emerald-600/50 to-green-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-emerald-900/50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-emerald-900/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                                <h2 class="text-sm font-bold text-emerald-400 font-mono tracking-wider">AI ACTIONS</h2>
                            </div>
                        </div>
                        <div class="h-[200px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($recentActions as $action)
                                <div class="p-2 rounded-lg bg-gray-800/50 border-l-2 border-{{ $action['color'] }}-500">
                                    <div class="flex items-center gap-2 mb-1">
                                        <svg class="w-4 h-4 text-{{ $action['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        <span class="text-xs font-medium text-{{ $action['color'] }}-400">{{ $action['action'] }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-400">{{ $action['description'] }}</div>
                                    <div class="text-[10px] text-gray-600 mt-1">{{ $action['tenant'] }} • {{ $action['time_ago'] }}</div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-full text-gray-500">
                                    <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span class="text-xs font-mono">AI ENGINE STANDBY</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Journey Transitions --}}
                <div class="relative">
                    <div class="absolute -inset-0.5 bg-gradient-to-b from-blue-600/50 to-indigo-600/50 rounded-xl blur opacity-20"></div>
                    <div class="relative bg-gray-900/80 backdrop-blur rounded-xl border border-blue-900/50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-blue-900/30">
                            <h2 class="text-sm font-bold text-blue-400 font-mono tracking-wider">JOURNEY TRANSITIONS</h2>
                        </div>
                        <div class="h-[220px] overflow-y-auto custom-scrollbar p-2 space-y-1">
                            @forelse($journeyTransitions as $transition)
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-800/50">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center text-[10px] font-mono text-gray-400">#{{ $transition['person_id'] }}</div>
                                    <div class="flex items-center gap-1 flex-1">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-gray-700 text-gray-300 uppercase">{{ $transition['from'] }}</span>
                                        <svg class="w-4 h-4 text-{{ $transition['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($transition['direction'] === 'up')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                            @endif
                                        </svg>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-{{ $transition['color'] }}-500/20 text-{{ $transition['color'] }}-400 uppercase">{{ $transition['to'] }}</span>
                                    </div>
                                    <span class="text-[10px] text-gray-600">{{ $transition['time_ago'] }}</span>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-full text-gray-500">
                                    <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <span class="text-xs font-mono">TRACKING JOURNEYS</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Voice Command Indicator --}}
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-20">
        <div class="flex items-center gap-3 px-6 py-3 rounded-full bg-gray-900/90 backdrop-blur border border-cyan-900/50">
            <div class="flex items-center gap-1">
                @for($i = 0; $i < 5; $i++)
                    <div class="w-1 bg-cyan-400 rounded-full animate-voice-bar" style="animation-delay: {{ $i * 100 }}ms; height: {{ rand(8, 24) }}px"></div>
                @endfor
            </div>
            <span class="text-xs font-mono text-cyan-400">TIXELLO INTELLIGENCE ACTIVE</span>
            <div class="flex items-center gap-1">
                @for($i = 0; $i < 5; $i++)
                    <div class="w-1 bg-cyan-400 rounded-full animate-voice-bar" style="animation-delay: {{ ($i + 5) * 100 }}ms; height: {{ rand(8, 24) }}px"></div>
                @endfor
            </div>
        </div>
    </div>

    <style>
        @keyframes scan { 0% { top: 0%; } 100% { top: 100%; } }
        .animate-scan { animation: scan 4s linear infinite; }

        @keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 20px rgba(6, 182, 212, 0.4); } 50% { box-shadow: 0 0 40px rgba(6, 182, 212, 0.8); } }
        .animate-pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }

        @keyframes fade-in { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        .animate-fade-in { animation: fade-in 0.3s ease-out forwards; }

        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .animate-shimmer { animation: shimmer 2s infinite; }

        @keyframes voice-bar { 0%, 100% { transform: scaleY(0.3); } 50% { transform: scaleY(1); } }
        .animate-voice-bar { animation: voice-bar 0.5s ease-in-out infinite; }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); border-radius: 2px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(6, 182, 212, 0.3); border-radius: 2px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(6, 182, 212, 0.5); }
    </style>
</div>
