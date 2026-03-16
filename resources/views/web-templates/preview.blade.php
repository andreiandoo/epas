<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $demo_data['site']['name'] ?? $template->name }} — Preview</title>

    {{-- OG Meta Tags for social sharing --}}
    @php
        $ogTitle = ($demo_data['site']['name'] ?? $template->name);
        $ogDescription = $demo_data['site']['tagline'] ?? $template->description ?? 'Template de prezentare powered by Tixello';
        $ogImage = $template->preview_image
            ? Storage::url($template->preview_image)
            : 'https://ui-avatars.com/api/?name=' . urlencode($ogTitle) . '&size=1200&background=' . ltrim($color_scheme['primary'] ?? '6366f1', '#') . '&color=fff&format=png&font-size=0.33';
        $ogUrl = request()->url();
    @endphp
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:site_name" content="Tixello">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="description" content="{{ $ogDescription }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '{{ $color_scheme["primary"] ?? "#6366f1" }}',
                        secondary: '{{ $color_scheme["secondary"] ?? "#8b5cf6" }}',
                        accent: '{{ $color_scheme["accent"] ?? "#f59e0b" }}',
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .seat { width: 18px; height: 18px; border-radius: 4px; cursor: pointer; transition: all 0.15s; }
        .seat:hover { transform: scale(1.3); }
        .seat-available { opacity: 1; }
        .seat-taken { opacity: 0.25; cursor: default; }
        .seat-selected { outline: 2px solid #000; outline-offset: 1px; }
    </style>
</head>
<body class="min-h-screen" style="background-color: {{ $color_scheme['background'] ?? '#ffffff' }}; color: {{ $color_scheme['text'] ?? '#1f2937' }};"
      x-data="templatePreview()" x-cloak>

    {{-- Demo Banner with Device Toggle & QR --}}
    <div class="sticky top-0 z-50" x-data="previewToolbar()">
        @if($is_demo)
        <div class="bg-amber-500 text-amber-900 py-2 text-sm font-medium flex items-center justify-between px-4">
            <span>Demo: „{{ $template->name }}"
                <a href="/admin/web-templates/{{ $template->id }}/edit" class="underline ml-1">Personalizează &rarr;</a>
            </span>
            <div class="flex items-center gap-2">
                {{-- Device toggle --}}
                <div class="flex bg-amber-600/30 rounded-lg p-0.5">
                    <button @click="setDevice('mobile')" :class="device === 'mobile' ? 'bg-white text-amber-900' : 'text-amber-800'" class="px-2 py-1 rounded text-xs transition" title="Mobile (375px)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </button>
                    <button @click="setDevice('tablet')" :class="device === 'tablet' ? 'bg-white text-amber-900' : 'text-amber-800'" class="px-2 py-1 rounded text-xs transition" title="Tablet (768px)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </button>
                    <button @click="setDevice('desktop')" :class="device === 'desktop' ? 'bg-white text-amber-900' : 'text-amber-800'" class="px-2 py-1 rounded text-xs transition" title="Desktop (100%)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </button>
                </div>
                {{-- QR Code button --}}
                <button @click="showQr = !showQr" class="bg-amber-600/30 hover:bg-amber-600/50 text-amber-800 px-2 py-1 rounded text-xs transition" title="QR Code">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                </button>
            </div>
        </div>
        @else
        <div class="bg-indigo-600 text-white py-2 text-sm font-medium flex items-center justify-between px-4">
            <span>Preview: {{ $customization->label ?? $customization->unique_token }}
                <span class="ml-2 opacity-70">{{ $customization->viewed_count }} vizualizări</span>
            </span>
            <div class="flex items-center gap-2">
                <div class="flex bg-indigo-700/50 rounded-lg p-0.5">
                    <button @click="setDevice('mobile')" :class="device === 'mobile' ? 'bg-white text-indigo-900' : 'text-indigo-200'" class="px-2 py-1 rounded text-xs transition" title="Mobile">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </button>
                    <button @click="setDevice('tablet')" :class="device === 'tablet' ? 'bg-white text-indigo-900' : 'text-indigo-200'" class="px-2 py-1 rounded text-xs transition" title="Tablet">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </button>
                    <button @click="setDevice('desktop')" :class="device === 'desktop' ? 'bg-white text-indigo-900' : 'text-indigo-200'" class="px-2 py-1 rounded text-xs transition" title="Desktop">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </button>
                </div>
                <button @click="showQr = !showQr" class="bg-indigo-700/50 hover:bg-indigo-700 text-indigo-200 px-2 py-1 rounded text-xs transition" title="QR Code">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                </button>
            </div>
        </div>
        @endif

        {{-- QR Code popup --}}
        <div x-show="showQr" x-transition @click.outside="showQr = false"
             class="absolute right-4 top-full mt-2 bg-white rounded-xl shadow-2xl border p-6 text-center z-50">
            <p class="text-sm font-medium text-gray-900 mb-3">Scanează pentru preview</p>
            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(window.location.href)"
                 alt="QR Code" class="w-48 h-48 mx-auto rounded-lg">
            <p class="text-xs text-gray-400 mt-3 max-w-[200px] break-all" x-text="window.location.href"></p>
            <button @click="navigator.clipboard.writeText(window.location.href); $el.textContent = 'Copiat!'; setTimeout(() => $el.textContent = 'Copiază link', 1500)"
                    class="mt-3 text-xs bg-indigo-600 text-white px-4 py-1.5 rounded-lg hover:bg-indigo-700 transition">
                Copiază link
            </button>
        </div>
    </div>

    {{-- Device preview wrapper --}}
    <div id="preview-wrapper" x-data x-ref="wrapper">

    {{-- HEADER --}}
    <header class="bg-primary text-white">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <template x-if="siteData.logo_url">
                    <img :src="siteData.logo_url" alt="Logo" class="h-10 w-auto">
                </template>
                <span class="text-xl font-bold" x-text="siteData.name || '{{ $template->name }}'"></span>
            </div>
            <nav class="hidden md:flex items-center gap-6 text-sm">
                <a href="#events" class="hover:opacity-80 transition">Evenimente</a>
                <a href="#about" class="hover:opacity-80 transition">Despre</a>
                <a href="#contact" class="hover:opacity-80 transition">Contact</a>
            </nav>
        </div>
    </header>

    {{-- HERO --}}
    <section class="relative overflow-hidden">
        <div class="bg-gradient-to-br from-primary to-secondary py-20 md:py-32">
            <div class="max-w-7xl mx-auto px-4 text-center text-white">
                <h1 class="text-4xl md:text-6xl font-extrabold mb-4" x-text="heroData.title || 'Bine ați venit'"></h1>
                <p class="text-lg md:text-xl opacity-90 mb-8 max-w-2xl mx-auto" x-text="heroData.subtitle || ''"></p>
                <template x-if="heroData.countdown_to">
                    <div class="mb-8">
                        <div class="inline-flex gap-4 text-center" x-data="countdown(heroData.countdown_to)">
                            <div class="bg-white/20 backdrop-blur rounded-lg px-4 py-3">
                                <div class="text-3xl font-bold" x-text="days"></div>
                                <div class="text-xs uppercase opacity-70">Zile</div>
                            </div>
                            <div class="bg-white/20 backdrop-blur rounded-lg px-4 py-3">
                                <div class="text-3xl font-bold" x-text="hours"></div>
                                <div class="text-xs uppercase opacity-70">Ore</div>
                            </div>
                            <div class="bg-white/20 backdrop-blur rounded-lg px-4 py-3">
                                <div class="text-3xl font-bold" x-text="mins"></div>
                                <div class="text-xs uppercase opacity-70">Min</div>
                            </div>
                        </div>
                    </div>
                </template>
                <a href="#events"
                   class="inline-block bg-white text-primary font-semibold px-8 py-3 rounded-full hover:shadow-lg transition"
                   x-text="heroData.cta_text || 'Descoperă'"></a>
            </div>
        </div>
    </section>

    {{-- STATS --}}
    <template x-if="statsData && Object.keys(statsData).length > 0">
        <section class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <template x-for="(value, key) in statsData" :key="key">
                        <div>
                            <div class="text-3xl font-bold text-primary" x-text="typeof value === 'number' ? value.toLocaleString() : value"></div>
                            <div class="text-sm text-gray-500 mt-1 capitalize" x-text="key.replace(/_/g, ' ')"></div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </template>

    {{-- EVENTS / REPERTOIRE / LINEUP --}}
    <section id="events" class="py-16">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-3xl font-bold mb-8 text-center" x-text="eventsTitle"></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="(event, idx) in eventsList" :key="idx">
                    <div class="bg-white rounded-xl shadow-sm border overflow-hidden hover:shadow-md transition group cursor-pointer"
                         @click="selectedEvent = event; showEventModal = true">
                        <div class="aspect-video bg-gradient-to-br from-primary/20 to-secondary/20 flex items-center justify-center relative overflow-hidden">
                            <template x-if="event.image && !event.image.startsWith('/demo/')">
                                <img :src="event.image" :alt="event.title" class="w-full h-full object-cover absolute inset-0 group-hover:scale-105 transition duration-300">
                            </template>
                            <template x-if="!event.image || event.image.startsWith('/demo/')">
                                <svg class="w-12 h-12 text-primary/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                                </svg>
                            </template>
                            {{-- Seating map indicator --}}
                            <div x-show="event.has_seating_map" class="absolute top-2 right-2 bg-white/90 backdrop-blur text-xs font-medium text-gray-700 px-2 py-1 rounded-md flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                Hartă locuri
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center gap-2 mb-2">
                                <span x-show="event.badge" class="text-xs bg-accent text-white px-2 py-0.5 rounded-full" x-text="event.badge"></span>
                                <span x-show="event.category || event.type" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded" x-text="event.category || event.type"></span>
                                <span x-show="event.duration" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded" x-text="event.duration"></span>
                            </div>
                            <h3 class="font-semibold text-lg mb-1" x-text="event.title"></h3>
                            <p class="text-sm text-gray-500 mb-1" x-text="event.author ? event.author + ' — regie: ' + event.director : ''"></p>
                            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                                <span x-text="formatDate(event.date || event.next_show)"></span>
                                <span x-show="event.venue || event.hall">&middot;</span>
                                <span x-text="event.venue || event.hall || ''"></span>
                            </div>

                            {{-- Availability bar --}}
                            <template x-if="event.availability_pct !== undefined">
                                <div class="mb-3">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-gray-500" x-text="event.availability_label"></span>
                                        <span class="font-medium" x-text="event.tickets_remaining ? event.tickets_remaining + ' bilete rămase' : event.availability_pct + '% disponibil'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full transition-all" :style="'width:' + event.availability_pct + '%'"
                                             :class="event.availability_pct < 15 ? 'bg-red-500' : event.availability_pct < 40 ? 'bg-amber-500' : 'bg-green-500'"></div>
                                    </div>
                                </div>
                            </template>

                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-primary" x-text="event.price_from !== undefined ? (event.price_from === 0 ? 'Gratuit' : 'de la ' + event.price_from + ' ' + (event.currency || 'RON')) : ''"></span>
                                    <span x-show="event.ticket_types && event.ticket_types.length > 1" class="text-xs text-gray-400 ml-1" x-text="'(' + event.ticket_types.length + ' tipuri)'"></span>
                                </div>
                                <button class="bg-primary text-white text-sm px-4 py-2 rounded-lg hover:opacity-90 transition">
                                    Bilete
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    {{-- EVENT DETAIL MODAL (with ticket types + seating map) --}}
    <template x-if="showEventModal && selectedEvent">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showEventModal = false">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showEventModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                {{-- Modal header --}}
                <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl">
                    <div>
                        <h3 class="text-xl font-bold" x-text="selectedEvent.title"></h3>
                        <p class="text-sm text-gray-500" x-text="formatDate(selectedEvent.date || selectedEvent.next_show) + (selectedEvent.time ? ' · ' + selectedEvent.time : '') + (selectedEvent.venue || selectedEvent.hall ? ' · ' + (selectedEvent.venue || selectedEvent.hall) : '')"></p>
                    </div>
                    <button @click="showEventModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6">
                    {{-- Cast (for theater) --}}
                    <template x-if="selectedEvent.cast && selectedEvent.cast.length > 0">
                        <div class="mb-6">
                            <span class="text-sm font-medium text-gray-700">Distribuție: </span>
                            <span class="text-sm text-gray-500" x-text="selectedEvent.cast.join(', ')"></span>
                        </div>
                    </template>

                    {{-- Seating Map --}}
                    <template x-if="selectedEvent.has_seating_map && selectedEvent.seating_config">
                        <div class="mb-8">
                            <h4 class="font-semibold text-lg mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                Selectează locul
                                <span x-show="selectedEvent.seating_config.hall_name" class="text-sm font-normal text-gray-500" x-text="'— ' + selectedEvent.seating_config.hall_name"></span>
                            </h4>

                            {{-- Stage indicator --}}
                            <div class="text-center mb-4">
                                <div class="inline-block bg-gray-800 text-white text-xs uppercase tracking-widest px-12 py-2 rounded-b-lg"
                                     x-text="selectedEvent.seating_config.layout === 'stadium-full' || selectedEvent.seating_config.layout === 'stadium-concert' ? 'TEREN' : 'SCENĂ'"></div>
                            </div>

                            {{-- Zones visual --}}
                            <div class="space-y-3 max-w-2xl mx-auto">
                                <template x-for="(zone, zi) in selectedEvent.seating_config.zones" :key="zi">
                                    <div class="relative">
                                        <div class="flex items-center gap-3 p-3 rounded-lg border-2 hover:shadow-sm transition cursor-pointer"
                                             :style="'border-color:' + zone.color + '20; background-color:' + zone.color + '08'"
                                             @click="selectedZone = zone">
                                            {{-- Zone color indicator --}}
                                            <div class="w-4 h-4 rounded-full flex-shrink-0" :style="'background-color:' + zone.color"></div>

                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium text-sm" x-text="zone.name"></div>
                                                <div class="text-xs text-gray-500">
                                                    <span x-text="zone.available + ' locuri disponibile'"></span>
                                                    <span x-show="zone.rows"> · <span x-text="zone.rows + ' rânduri × ' + zone.seats_per_row + ' locuri'"></span></span>
                                                    <span x-show="zone.capacity && !zone.rows"> · <span x-text="'din ' + zone.capacity + ' total'"></span></span>
                                                </div>
                                            </div>

                                            <div class="text-right flex-shrink-0">
                                                <div class="font-bold text-sm" x-text="zone.price ? zone.price + ' RON' : ''"></div>
                                                {{-- Occupancy indicator --}}
                                                <div class="w-20 bg-gray-200 rounded-full h-1 mt-1">
                                                    <div class="h-1 rounded-full" :style="'width:' + (zone.rows ? Math.round(zone.available / (zone.rows * zone.seats_per_row) * 100) : Math.round(zone.available / zone.capacity * 100)) + '%; background-color:' + zone.color"></div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Seat grid (shown when zone is selected and has rows) --}}
                                        <template x-if="selectedZone === zone && zone.rows">
                                            <div class="mt-2 p-4 bg-gray-50 rounded-lg border">
                                                <div class="text-xs text-gray-500 text-center mb-3">Click pe un loc pentru a selecta</div>
                                                <div class="flex flex-col items-center gap-1">
                                                    <template x-for="row in zone.rows" :key="'r'+row">
                                                        <div class="flex items-center gap-0.5">
                                                            <span class="text-[10px] text-gray-400 w-6 text-right mr-1" x-text="'R' + row"></span>
                                                            <template x-for="seat in zone.seats_per_row" :key="'s'+seat">
                                                                <div class="seat"
                                                                     :class="Math.random() > (zone.available / (zone.rows * zone.seats_per_row)) ? 'seat-taken' : 'seat-available'"
                                                                     :style="'background-color:' + zone.color"
                                                                     :title="'Rând ' + row + ', Loc ' + seat">
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div class="flex items-center justify-center gap-4 mt-3 text-xs text-gray-500">
                                                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm" :style="'background-color:' + zone.color"></span> Disponibil</span>
                                                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm opacity-25" :style="'background-color:' + zone.color"></span> Ocupat</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Ticket Types --}}
                    <template x-if="selectedEvent.ticket_types && selectedEvent.ticket_types.length > 0">
                        <div>
                            <h4 class="font-semibold text-lg mb-4">Tipuri de bilete</h4>
                            <div class="space-y-3">
                                <template x-for="(ticket, ti) in selectedEvent.ticket_types" :key="ti">
                                    <div class="flex items-center gap-4 p-4 rounded-xl border-2 transition"
                                         :class="ticket.available ? 'border-gray-200 hover:border-primary/50 cursor-pointer' : 'border-gray-100 opacity-50'">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium" x-text="ticket.name"></span>
                                                <span x-show="ticket.is_group" class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">Grup</span>
                                                <span x-show="ticket.has_seat_selection" class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded flex items-center gap-0.5">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                                    Alege loc
                                                </span>
                                            </div>
                                            <p x-show="ticket.description" class="text-sm text-gray-500 mt-0.5" x-text="ticket.description"></p>
                                            <div class="text-xs mt-1" :class="ticket.remaining <= 10 ? 'text-red-600 font-medium' : ticket.remaining <= 50 ? 'text-amber-600' : 'text-gray-400'"
                                                 x-text="!ticket.available ? 'Epuizat' : ticket.remaining <= 10 ? 'Doar ' + ticket.remaining + ' rămase!' : ticket.remaining + ' disponibile'">
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="text-xl font-bold text-primary" x-text="ticket.price === 0 ? 'Gratuit' : ticket.price + ' ' + (ticket.currency || 'RON')"></div>
                                            <button class="mt-2 text-sm px-4 py-1.5 rounded-lg font-medium transition"
                                                    :class="ticket.available ? 'bg-primary text-white hover:opacity-90' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                                    x-text="ticket.available ? 'Adaugă' : 'Epuizat'">
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- ARTISTS (for Agency) --}}
    <template x-if="artistsList && artistsList.length > 0">
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4">
                <h2 class="text-3xl font-bold mb-8 text-center">Artiștii Noștri</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <template x-for="(artist, idx) in artistsList" :key="idx">
                        <div class="bg-white rounded-xl shadow-sm border overflow-hidden text-center group hover:shadow-md transition">
                            <div class="aspect-square bg-gradient-to-br from-primary/20 to-accent/20 flex items-center justify-center relative overflow-hidden">
                                <template x-if="artist.image && !artist.image.startsWith('/demo/')">
                                    <img :src="artist.image" :alt="artist.name" class="w-full h-full object-cover absolute inset-0 group-hover:scale-105 transition duration-300">
                                </template>
                                <template x-if="!artist.image || artist.image.startsWith('/demo/')">
                                    <span class="text-5xl font-bold text-primary/20" x-text="artist.name.charAt(0)"></span>
                                </template>
                            </div>
                            <div class="p-5">
                                <h3 class="font-semibold text-lg" x-text="artist.name"></h3>
                                <p class="text-sm text-gray-500 mb-2" x-text="artist.genre"></p>
                                <p class="text-xs text-gray-400 line-clamp-2 mb-3" x-text="artist.bio"></p>
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded" x-show="artist.available_for_booking">Disponibil</span>
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded" x-text="artist.upcoming_shows + ' show-uri'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </template>

    {{-- TICKETS (for Festival) --}}
    <template x-if="ticketsList && ticketsList.length > 0">
        <section class="py-16">
            <div class="max-w-5xl mx-auto px-4">
                <h2 class="text-3xl font-bold mb-8 text-center">Bilete</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-for="(ticket, idx) in ticketsList" :key="idx">
                        <div class="bg-white rounded-xl border-2 p-6 hover:border-primary transition relative"
                             :class="idx === 1 ? 'border-primary shadow-lg' : 'border-gray-200'">
                            <template x-if="idx === 1">
                                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary text-white text-xs px-3 py-1 rounded-full font-medium">Popular</div>
                            </template>
                            <h3 class="font-bold text-lg mb-2" x-text="ticket.type"></h3>
                            <div class="text-3xl font-extrabold text-primary mb-1">
                                <span x-text="ticket.price"></span>
                                <span class="text-sm font-normal text-gray-500" x-text="ticket.currency || 'RON'"></span>
                            </div>
                            <div class="text-xs mb-4" :class="ticket.remaining <= 50 ? 'text-red-600 font-medium' : 'text-gray-400'"
                                 x-text="ticket.remaining ? ticket.remaining + ' rămase' : ''"></div>
                            <ul class="space-y-2 mb-6">
                                <template x-for="perk in (ticket.perks || [])" :key="perk">
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        <span x-text="perk"></span>
                                    </li>
                                </template>
                            </ul>
                            <button class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition"
                                    :class="!ticket.available ? 'opacity-50 cursor-not-allowed' : ''"
                                    x-text="ticket.available ? 'Cumpără' : 'Sold Out'"></button>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </template>

    {{-- CONTACT --}}
    <section id="contact" class="py-16 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Contact</h2>
            <div class="space-y-2 text-gray-600">
                <p x-show="siteData.phone"><span class="font-medium">Telefon:</span> <span x-text="siteData.phone"></span></p>
                <p x-show="siteData.email"><span class="font-medium">Email:</span> <span x-text="siteData.email"></span></p>
                <p x-show="siteData.address"><span class="font-medium">Adresă:</span> <span x-text="siteData.address"></span></p>
            </div>
        </div>
    </section>

    {{-- PROSPECT FEEDBACK --}}
    @if(!$is_demo && $customization)
    <section class="py-12 bg-white border-t" x-data="feedbackWidget()" id="feedback">
        <div class="max-w-xl mx-auto px-4 text-center">
            <template x-if="!submitted">
                <div>
                    <h3 class="text-xl font-bold mb-2">Ce părere ai despre acest site?</h3>
                    <p class="text-sm text-gray-500 mb-6">Feedback-ul tău ne ajută să îmbunătățim experiența.</p>

                    {{-- Star rating --}}
                    <div class="flex justify-center gap-2 mb-6">
                        <template x-for="star in 5" :key="star">
                            <button type="button" @click="rating = star" @mouseenter="hoverRating = star" @mouseleave="hoverRating = 0"
                                    class="focus:outline-none transition-transform hover:scale-110">
                                <svg class="w-10 h-10 transition-colors" :class="star <= (hoverRating || rating) ? 'text-amber-400' : 'text-gray-200'"
                                     fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </button>
                        </template>
                    </div>

                    {{-- Expandable form (shows after rating) --}}
                    <template x-if="rating > 0">
                        <div class="space-y-4 text-left" x-transition>
                            <textarea x-model="comment" rows="3" placeholder="Spune-ne mai multe (opțional)..."
                                      class="w-full px-4 py-3 border rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-none"></textarea>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <input type="text" x-model="name" placeholder="Numele tău"
                                       class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                <input type="email" x-model="email" placeholder="Email"
                                       class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                <input type="text" x-model="company" placeholder="Companie"
                                       class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>

                            <div class="text-center">
                                <button @click="submitFeedback()" :disabled="submitting"
                                        class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-indigo-700 transition disabled:opacity-50">
                                    <span x-show="!submitting">Trimite Feedback</span>
                                    <span x-show="submitting">Se trimite...</span>
                                </button>
                            </div>
                            <p x-show="error" class="text-red-600 text-sm text-center" x-text="error"></p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="submitted">
                <div class="py-4" x-transition>
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">Mulțumim!</h3>
                    <p class="text-sm text-gray-500" x-text="responseMessage"></p>
                    <div x-show="averageRating" class="mt-3 text-sm text-gray-400">
                        Rating mediu: <span class="font-semibold text-amber-500" x-text="averageRating + '/5'"></span>
                        (<span x-text="feedbackCount"></span> feedback-uri)
                    </div>
                </div>
            </template>
        </div>
    </section>
    @endif

    {{-- FOOTER --}}
    <footer class="py-8 text-center text-sm" style="background-color: {{ $color_scheme['footer_bg'] ?? '#111827' }};">
        <div class="max-w-7xl mx-auto px-4">
            <p class="text-gray-400">
                &copy; {{ date('Y') }} <span x-text="siteData.name || '{{ $template->name }}'"></span>. Powered by
                <a href="https://tixello.ro" class="text-indigo-400 hover:text-indigo-300">Tixello</a>
            </p>
            <p class="mt-2 text-gray-500 text-xs">
                Template: {{ $template->name }} v{{ $template->version }} · {{ $template->category->label() }}
            </p>
        </div>
    </footer>

    </div> {{-- /preview-wrapper --}}

    <script>
        function previewToolbar() {
            return {
                device: 'desktop',
                showQr: false,
                setDevice(d) {
                    this.device = d;
                    const wrapper = document.getElementById('preview-wrapper');
                    if (!wrapper) return;
                    wrapper.style.transition = 'max-width 0.3s ease, margin 0.3s ease';
                    switch(d) {
                        case 'mobile':
                            wrapper.style.maxWidth = '375px';
                            wrapper.style.margin = '0 auto';
                            wrapper.style.boxShadow = '0 0 0 1px #e5e7eb, 0 25px 50px -12px rgba(0,0,0,.25)';
                            wrapper.style.borderRadius = '0 0 24px 24px';
                            break;
                        case 'tablet':
                            wrapper.style.maxWidth = '768px';
                            wrapper.style.margin = '0 auto';
                            wrapper.style.boxShadow = '0 0 0 1px #e5e7eb, 0 25px 50px -12px rgba(0,0,0,.25)';
                            wrapper.style.borderRadius = '0 0 16px 16px';
                            break;
                        default:
                            wrapper.style.maxWidth = 'none';
                            wrapper.style.margin = '0';
                            wrapper.style.boxShadow = 'none';
                            wrapper.style.borderRadius = '0';
                    }
                }
            };
        }

        function countdown(targetDate) {
            return {
                days: '00', hours: '00', mins: '00',
                init() {
                    this.update();
                    setInterval(() => this.update(), 60000);
                },
                update() {
                    const diff = new Date(targetDate) - new Date();
                    if (diff <= 0) { this.days = '0'; this.hours = '0'; this.mins = '0'; return; }
                    this.days = String(Math.floor(diff / 86400000));
                    this.hours = String(Math.floor((diff % 86400000) / 3600000));
                    this.mins = String(Math.floor((diff % 3600000) / 60000));
                }
            };
        }

        function feedbackWidget() {
            return {
                rating: 0,
                hoverRating: 0,
                comment: '',
                name: '',
                email: '',
                company: '',
                submitting: false,
                submitted: false,
                error: '',
                responseMessage: '',
                averageRating: null,
                feedbackCount: 0,

                async submitFeedback() {
                    this.submitting = true;
                    this.error = '';
                    try {
                        const res = await fetch('/web-templates/feedback/{{ $customization?->unique_token ?? "" }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                rating: this.rating,
                                comment: this.comment,
                                name: this.name,
                                email: this.email,
                                company: this.company,
                            }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.submitted = true;
                            this.responseMessage = data.message;
                            this.averageRating = data.average_rating;
                            this.feedbackCount = data.feedback_count;
                        } else {
                            this.error = data.message || 'A apărut o eroare.';
                        }
                    } catch (e) {
                        this.error = 'Eroare de rețea. Încearcă din nou.';
                    }
                    this.submitting = false;
                }
            };
        }

        function templatePreview() {
            const rawData = @json($demo_data);

            let events = rawData.events
                || rawData.featured_events
                || rawData.repertoire
                || rawData.upcoming_events
                || [];

            let eventsTitle = '{{ $template->category->value }}' === 'theater' ? 'Repertoriu'
                : '{{ $template->category->value }}' === 'festival' ? 'Line-up & Program'
                : '{{ $template->category->value }}' === 'stadium' ? 'Evenimente Viitoare'
                : '{{ $template->category->value }}' === 'marketplace' ? 'Evenimente Populare'
                : 'Evenimente';

            return {
                siteData: rawData.site || {},
                heroData: rawData.hero || {},
                statsData: rawData.stats || {},
                eventsList: events,
                eventsTitle: eventsTitle,
                artistsList: rawData.artists || [],
                ticketsList: rawData.tickets || [],
                rawData: rawData,

                // Modal state
                showEventModal: false,
                selectedEvent: null,
                selectedZone: null,

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    if (isNaN(d.getTime())) return dateStr;
                    return d.toLocaleDateString('ro-RO', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });
                }
            };
        }
    </script>
</body>
</html>
