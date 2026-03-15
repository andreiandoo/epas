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
                    <div class="bg-white rounded-xl shadow-sm border overflow-hidden hover:shadow-md transition group">
                        <div class="aspect-video bg-gradient-to-br from-primary/20 to-secondary/20 flex items-center justify-center">
                            <svg class="w-12 h-12 text-primary/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                            </svg>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center gap-2 mb-2">
                                <span x-show="event.badge" class="text-xs bg-accent text-white px-2 py-0.5 rounded-full" x-text="event.badge"></span>
                                <span x-show="event.category || event.type" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded" x-text="event.category || event.type"></span>
                            </div>
                            <h3 class="font-semibold text-lg mb-1" x-text="event.title"></h3>
                            <p class="text-sm text-gray-500 mb-1" x-text="event.author ? event.author + ' — regie: ' + event.director : ''"></p>
                            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                                <span x-text="formatDate(event.date || event.next_show)"></span>
                                <span x-show="event.venue || event.hall">&middot;</span>
                                <span x-text="event.venue || event.hall || ''"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-primary" x-text="event.price_from !== undefined ? (event.price_from === 0 ? 'Gratuit' : 'de la ' + event.price_from + ' ' + (event.currency || 'RON')) : ''"></span>
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <template x-for="(ticket, idx) in ticketsList" :key="idx">
                        <div class="bg-white rounded-xl border-2 p-6 hover:border-primary transition" :class="idx === 1 ? 'border-primary shadow-lg' : 'border-gray-200'">
                            <h3 class="font-bold text-lg mb-2" x-text="ticket.type"></h3>
                            <div class="text-3xl font-extrabold text-primary mb-4">
                                <span x-text="ticket.price"></span>
                                <span class="text-sm font-normal text-gray-500" x-text="ticket.currency || 'RON'"></span>
                            </div>
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
    <footer class="py-8 text-center text-sm text-gray-400" style="background-color: {{ $color_scheme['footer_bg'] ?? '#111827' }};">
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
        function templatePreview() {
            const rawData = @json($demo_data);

            // Normalize event lists based on template category
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

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('ro-RO', {
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
