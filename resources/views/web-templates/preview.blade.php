<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $demo_data['site']['name'] ?? $template->name }} — Preview</title>
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

    {{-- Demo Banner --}}
    @if($is_demo)
    <div class="bg-amber-500 text-amber-900 text-center py-2 text-sm font-medium sticky top-0 z-50">
        Acesta este un demo al template-ului „{{ $template->name }}".
        <a href="/admin/web-templates/{{ $template->id }}/edit" class="underline ml-2">Personalizează &rarr;</a>
    </div>
    @else
    <div class="bg-indigo-600 text-white text-center py-2 text-sm font-medium sticky top-0 z-50">
        Preview personalizat: {{ $customization->label ?? $customization->unique_token }}
        <span class="ml-4 opacity-70">Vizualizări: {{ $customization->viewed_count }}</span>
    </div>
    @endif

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
                        <div class="aspect-video bg-gradient-to-br from-primary/20 to-secondary/20 flex items-center justify-center relative">
                            <svg class="w-12 h-12 text-primary/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                            </svg>
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
                            <div class="aspect-square bg-gradient-to-br from-primary/20 to-accent/20 flex items-center justify-center">
                                <span class="text-5xl font-bold text-primary/20" x-text="artist.name.charAt(0)"></span>
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

    <script>
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
