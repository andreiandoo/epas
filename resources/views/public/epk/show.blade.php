@php
    /** @var array $artist */
    /** @var array $variant */
    /** @var array $live_stats */
    /** @var array $past_events */
    /** @var string $marketplace_name */

    $sectionsByid = collect($variant['sections'] ?? [])->keyBy('id');
    // Defensive: $sectionsByid[$id] poate fi null pentru variante incomplete.
    // PHP 8 aruncă TypeError pe null['key'], deci dezambiguăm înainte de chain.
    $get = function (string $id, string $key, $default = null) use ($sectionsByid) {
        $section = $sectionsByid[$id] ?? null;
        if (!is_array($section)) return $default;
        $data = $section['data'] ?? null;
        if (!is_array($data)) return $default;
        return $data[$key] ?? $default;
    };
    $enabled = fn (string $id) => (bool) (($sectionsByid[$id] ?? [])['enabled'] ?? false);

    $accent = $variant['accent_color'] ?? '#A51C30';
    $heroImage = $get('hero', 'cover_image') ?: ($artist['main_image_url'] ?: $artist['portrait_url']);
    $stageName = $get('hero', 'stage_name', $artist['name']);
    $tagline = $get('hero', 'tagline', '');
    $bioShort = $get('bio', 'bio_short', '');
    $bioLong = $get('bio', 'bio_long', '');
    $gallery = (array) $get('gallery', 'images', []);
    $achievements = (array) $get('achievements', 'items', []);
    $pressQuotes = (array) $get('press_quotes', 'quotes', []);
    $youtubeVideos = (array) $get('youtube', 'videos', []);
    $spotifyUrl = $get('spotify', 'spotify_url', '');
    $statsShow = (array) $get('stats', 'show', []);
    $rider = is_array($sectionsByid['rider'] ?? null) ? ($sectionsByid['rider']['data'] ?? []) : [];
    $social = is_array($sectionsByid['social'] ?? null) ? ($sectionsByid['social']['data'] ?? []) : [];
    $contactEmail = $get('contact', 'email', '');
    $contactPhone = $get('contact', 'phone', '');
    $showBookingCta = (bool) $get('contact', 'show_booking_cta', true);

    $statsConfig = [
        'tickets_sold' => 'Bilete vândute',
        'events_played' => 'Concerte',
        'cities' => 'Orașe',
        'countries' => 'Țări',
        'peak_audience' => 'Audiență max',
    ];
    $visibleStats = collect($statsConfig)->filter(fn ($label, $key) => !empty($statsShow[$key]) && isset($live_stats[$key]));

    $publicUrl = url()->current();
    $ogTitle = $stageName . ($variant['name'] !== 'Default' ? ' — ' . $variant['name'] : '') . ' EPK';
    $ogDescription = trim(strip_tags($bioShort)) ?: ('Electronic Press Kit pentru ' . $stageName);
    $ogImage = $heroImage ?: ($artist['main_image_url'] ?? null);

    $visiblePastEvents = collect($past_events ?? [])->take(9);
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ogTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($ogDescription, 160) }}">

    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($ogDescription, 200) }}">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="{{ $publicUrl }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    <script type="application/ld+json">
    @json([
        '@context' => 'https://schema.org',
        '@type' => 'MusicGroup',
        'name' => $stageName,
        'description' => $ogDescription,
        'url' => $publicUrl,
        'image' => $ogImage,
        'sameAs' => array_values(array_filter([
            $social['website'] ?? null,
            $social['facebook'] ?? null,
            $social['instagram'] ?? null,
            $social['tiktok'] ?? null,
            $social['youtube'] ?? null,
            $spotifyUrl,
        ])),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Plus Jakarta Sans', 'sans-serif'],
                        'display': ['Playfair Display', 'serif'],
                    },
                    colors: {
                        'primary': '{{ $accent }}',
                        'primary-dark': '#8B1728',
                        'secondary': '#1E293B',
                        'accent': '{{ $accent }}',
                        'success': '#10B981',
                        'warning': '#F59E0B',
                        'error': '#EF4444',
                    }
                }
            }
        }
    </script>
    <style>
        :root { --accent: {{ $accent }}; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0a0a0f; color: white; }
        .epk-display { font-family: 'Playfair Display', serif; }
        .text-accent { color: var(--accent); }
        .bg-accent { background-color: var(--accent); }
        .border-accent { border-color: var(--accent); }
        .epk-stat-glow { background: radial-gradient(circle at 50% 0%, var(--accent), transparent 50%); opacity: 0.15; }
        .epk-public { background: #0a0a0f; color: white; }
        .modal-backdrop { background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); }
    </style>
</head>
<body class="bg-[#0a0a0f] text-white epk-public min-h-screen">

{{-- ============================ HERO ============================ --}}
@if ($enabled('hero'))
<section class="relative min-h-[80vh] flex items-end overflow-hidden">
    @if ($heroImage)
        <div class="absolute inset-0" style="background: url('{{ $heroImage }}') center/cover"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-black/20"></div>
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-[#1E293B] via-[#8B1728] to-black"></div>
        <div class="absolute inset-0" style="background: radial-gradient(circle at 30% 50%, var(--accent), transparent 60%); opacity: 0.4"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent"></div>
    @endif

    {{-- Top bar --}}
    <div class="absolute top-0 left-0 right-0 z-10 p-6 flex items-center justify-between">
        <span class="text-white/60 text-xs uppercase tracking-[0.2em]">Electronic Press Kit</span>
        <span class="text-white/60 text-xs flex items-center gap-2">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3.707 7.293a1 1 0 010 1.414l-3 3a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 111.414-1.414l.793.793 2.293-2.293a1 1 0 011.414 0z"/></svg>
            Verified by {{ $marketplace_name }}
        </span>
    </div>

    <div class="relative z-10 px-6 lg:px-12 pb-12 lg:pb-20 max-w-5xl">
        <p class="text-white/60 text-sm uppercase tracking-[0.3em] mb-4">{{ $variant['target'] ?? 'Press Kit' }}</p>
        <h1 class="epk-display text-6xl lg:text-9xl font-black text-white leading-[0.85] mb-6">{{ $stageName }}</h1>
        @if ($tagline)
            <p class="text-xl lg:text-2xl text-white/80 max-w-2xl font-light">{{ $tagline }}</p>
        @endif

        @if (!empty($artist['city']) || !empty($achievements))
        <div class="flex flex-wrap gap-2 mt-8">
            @if (!empty($artist['city']))
                <span class="px-3 py-1 bg-white/10 backdrop-blur rounded-full text-xs text-white">📍 {{ $artist['city'] }}</span>
            @endif
            @if (count($achievements) > 0)
                @php $earliest = collect($achievements)->pluck('year')->filter()->min(); @endphp
                @if ($earliest)
                    <span class="px-3 py-1 bg-white/10 backdrop-blur rounded-full text-xs text-white">⭐ Activ din {{ $earliest }}</span>
                @endif
            @endif
        </div>
        @endif
    </div>
</section>
@endif

{{-- ============================ STATS — KILLER FEATURE ============================ --}}
@if ($enabled('stats') && $visibleStats->isNotEmpty())
<section class="relative py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="absolute inset-0 epk-stat-glow"></div>
    <div class="relative max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Date Verificate · Din Platformă</p>
            <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white">Cifre care nu mint</h2>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-{{ $visibleStats->count() }} gap-6 lg:gap-8">
            @foreach ($visibleStats as $key => $label)
                <div class="text-center">
                    <p class="epk-display text-5xl lg:text-7xl font-black text-white mb-2">{{ $live_stats[$key]['display'] }}</p>
                    <p class="text-white/60 text-xs lg:text-sm uppercase tracking-wider">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================ BIO + ACHIEVEMENTS ============================ --}}
@if ($enabled('bio') && ($bioShort || $bioLong))
<section class="py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="max-w-3xl mx-auto">
        <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Biografie</p>
        <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-8">Cine suntem</h2>
        @if ($bioShort)
            <p class="text-xl text-white/80 leading-relaxed mb-6 font-light">{{ $bioShort }}</p>
        @endif
        @if ($bioLong)
            <p class="text-white/70 leading-relaxed whitespace-pre-line">{{ $bioLong }}</p>
        @endif

        @if ($enabled('achievements') && !empty($achievements))
            <div class="mt-12 space-y-4 pt-8 border-t border-white/10">
                <p class="text-white/60 text-xs uppercase tracking-[0.2em] font-bold mb-4">Realizări</p>
                @foreach ($achievements as $a)
                    <div class="flex gap-6 py-3 border-b border-white/5">
                        <span class="epk-display text-2xl font-bold text-accent w-20 flex-shrink-0">{{ $a['year'] ?? '' }}</span>
                        <p class="text-white/80">{{ $a['text'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif

{{-- ============================ GALLERY ============================ --}}
@if ($enabled('gallery') && !empty($gallery))
<section class="py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="max-w-6xl mx-auto">
        <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Imagini</p>
        <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-8">Live on stage</h2>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($gallery as $i => $img)
                <div class="aspect-square rounded-xl overflow-hidden bg-white/5 {{ $i === 0 ? 'lg:col-span-2 lg:row-span-2 lg:aspect-auto' : '' }}"
                     style="background-image: url('{{ $img }}'); background-size: cover; background-position: center;"></div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================ SPOTIFY ============================ --}}
@if ($enabled('spotify') && $spotifyUrl)
<section class="py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="max-w-3xl mx-auto">
        <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Muzică</p>
        <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-8">Asculta-ne</h2>
        <div class="bg-[#1DB954]/10 border border-[#1DB954]/30 rounded-2xl p-8 flex items-center gap-6">
            <svg class="w-16 h-16 text-[#1DB954] flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.42 1.56-.299.421-1.02.599-1.56.3z"/></svg>
            <div class="flex-1">
                <p class="text-white font-bold text-lg">{{ $stageName }}</p>
                <a href="{{ $spotifyUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-[#1DB954] hover:text-white transition-colors text-sm font-medium mt-2">
                    Deschide pe Spotify →
                </a>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ============================ YOUTUBE ============================ --}}
@if ($enabled('youtube') && !empty($youtubeVideos))
<section class="py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="max-w-5xl mx-auto">
        <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Video</p>
        <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-8">Vezi-ne live</h2>
        <div class="grid {{ count($youtubeVideos) > 1 ? 'lg:grid-cols-2' : '' }} gap-6">
            @foreach (array_slice($youtubeVideos, 0, 3) as $video)
                @php
                    $videoUrl = is_string($video) ? $video : ($video['url'] ?? '');
                    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/', $videoUrl, $m);
                    $videoId = $m[1] ?? null;
                @endphp
                @if ($videoId)
                    <div class="aspect-video rounded-xl overflow-hidden bg-black">
                        <iframe src="https://www.youtube.com/embed/{{ $videoId }}" frameborder="0" allowfullscreen class="w-full h-full"></iframe>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================ PRESS QUOTES ============================ --}}
@if ($enabled('press_quotes') && !empty($pressQuotes))
<section class="py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="max-w-4xl mx-auto">
        <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Au scris despre noi</p>
        <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-12">Ce zice presa</h2>
        <div class="grid lg:grid-cols-2 gap-6">
            @foreach ($pressQuotes as $q)
                <blockquote class="border-l-2 border-accent pl-6 py-2">
                    <p class="text-xl text-white/90 font-light italic mb-3 leading-relaxed">"{{ $q['text'] ?? '' }}"</p>
                    <footer class="text-sm text-white/50">
                        @if (!empty($q['url']))
                            <a href="{{ $q['url'] }}" target="_blank" rel="noopener" class="hover:text-accent">— {{ $q['source'] ?? '' }}</a>
                        @else
                            — {{ $q['source'] ?? '' }}
                        @endif
                    </footer>
                </blockquote>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================ PAST EVENTS SHOWCASE ============================ --}}
@if ($enabled('past_events') && $visiblePastEvents->isNotEmpty())
<section class="py-20 px-6 lg:px-12 border-t border-white/5">
    <div class="max-w-5xl mx-auto">
        <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Istoric</p>
        <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-12">Concerte memorabile</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($visiblePastEvents as $ev)
                <div class="bg-white/5 backdrop-blur rounded-xl p-5 hover:bg-white/10 transition-colors">
                    <p class="text-white/40 text-xs mb-2">{{ $ev['day'] }} {{ $ev['month'] }} {{ $ev['year'] }}</p>
                    <p class="text-white font-bold text-lg leading-tight mb-2">{{ $ev['title'] }}</p>
                    <p class="text-white/60 text-sm">{{ $ev['venue'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================ BOOKING CTA + RIDER + CONTACT ============================ --}}
@if ($enabled('contact') || $enabled('rider'))
<section class="py-32 px-6 lg:px-12 border-t border-white/5 relative overflow-hidden">
    <div class="absolute inset-0" style="background: radial-gradient(circle at 50% 50%, var(--accent), transparent 70%); opacity: 0.3"></div>
    <div class="relative z-10 max-w-3xl mx-auto text-center">
        @if ($showBookingCta)
            <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Disponibili pentru</p>
            <h2 class="epk-display text-5xl lg:text-7xl font-black text-white mb-6">Booking</h2>
            <p class="text-xl text-white/70 mb-10">Concerte · Festivaluri · Evenimente private · Corporate</p>
        @endif

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            @if ($showBookingCta && $contactEmail)
                <a href="mailto:{{ $contactEmail }}" class="inline-flex items-center justify-center gap-2 bg-accent text-white hover:opacity-90 px-8 py-4 rounded-xl font-semibold text-base transition-opacity">
                    Cere booking →
                </a>
            @endif
            @if ($enabled('rider') && !empty($rider['rider_pdf_url']))
                @if (!empty($rider['gated']))
                    <button type="button" onclick="document.getElementById('rider-modal').classList.remove('hidden'); document.getElementById('rider-modal').classList.add('flex')" class="inline-flex items-center justify-center gap-2 bg-white/10 backdrop-blur text-white hover:bg-white/20 px-8 py-4 rounded-xl font-semibold text-base transition-colors">
                        Descarcă press kit (PDF)
                    </button>
                @else
                    <a href="{{ $rider['rider_pdf_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 bg-white/10 backdrop-blur text-white hover:bg-white/20 px-8 py-4 rounded-xl font-semibold text-base transition-colors">
                        Descarcă press kit (PDF)
                    </a>
                @endif
            @endif
        </div>

        @if ($enabled('contact') && ($contactEmail || $contactPhone))
            <div class="mt-12 pt-12 border-t border-white/10 grid grid-cols-2 gap-8 text-left max-w-md mx-auto">
                @if ($contactEmail)
                    <div>
                        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Email</p>
                        <p class="text-white text-sm break-all">{{ $contactEmail }}</p>
                    </div>
                @endif
                @if ($contactPhone)
                    <div>
                        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Telefon</p>
                        <p class="text-white text-sm">{{ $contactPhone }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
@endif

{{-- ============================ FOOTER ============================ --}}
<footer class="py-12 px-6 lg:px-12 border-t border-white/10 text-center">
    @if ($enabled('social'))
        <div class="flex items-center justify-center gap-4 mb-6">
            @if (!empty($social['facebook']))
                <a href="{{ $social['facebook'] }}" target="_blank" rel="noopener" class="w-10 h-10 bg-white/5 hover:bg-white/15 rounded-full flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.04C6.5 2.04 2 6.53 2 12.06c0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.23.19 2.23.19v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7a10 10 0 0 0 8.44-9.9c0-5.53-4.5-10.02-10-10.02z"/></svg>
                </a>
            @endif
            @if (!empty($social['instagram']))
                <a href="{{ $social['instagram'] }}" target="_blank" rel="noopener" class="w-10 h-10 bg-white/5 hover:bg-white/15 rounded-full flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
                </a>
            @endif
            @if (!empty($social['tiktok']))
                <a href="{{ $social['tiktok'] }}" target="_blank" rel="noopener" class="w-10 h-10 bg-white/5 hover:bg-white/15 rounded-full flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-.88-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.84-.1z"/></svg>
                </a>
            @endif
            @if (!empty($social['youtube']))
                <a href="{{ $social['youtube'] }}" target="_blank" rel="noopener" class="w-10 h-10 bg-white/5 hover:bg-white/15 rounded-full flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                </a>
            @endif
            @if (!empty($social['website']))
                <a href="{{ $social['website'] }}" target="_blank" rel="noopener" class="w-10 h-10 bg-white/5 hover:bg-white/15 rounded-full flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18M12 3a17 17 0 010 18M12 3a17 17 0 000 18"/></svg>
                </a>
            @endif
        </div>
    @endif
    <p class="text-white/40 text-xs mb-2">Powered by <span class="text-white/70">{{ $marketplace_name }}</span></p>
    <p class="text-white/30 text-xs font-mono">{{ str_replace(['https://', 'http://'], '', $publicUrl) }}</p>
</footer>

{{-- ============================ RIDER LEAD CAPTURE MODAL ============================ --}}
@if ($enabled('rider') && !empty($rider['gated']) && !empty($rider['rider_pdf_url']))
<div id="rider-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="bg-[#0f0f14] border border-white/10 rounded-2xl max-w-md w-full p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-xl font-bold text-white">Descarcă press kit</h3>
                <p class="text-sm text-white/60 mt-1">Lasă-ne datele tale și primești instant link-ul de download.</p>
            </div>
            <button type="button" onclick="document.getElementById('rider-modal').classList.add('hidden'); document.getElementById('rider-modal').classList.remove('flex')" class="text-white/40 hover:text-white">✕</button>
        </div>

        <form id="rider-form" class="space-y-3" data-variant-id="{{ $variant['id'] }}">
            <div>
                <label class="text-xs text-white/60 uppercase">Nume *</label>
                <input type="text" name="name" required class="w-full mt-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white">
            </div>
            <div>
                <label class="text-xs text-white/60 uppercase">Email *</label>
                <input type="email" name="email" required class="w-full mt-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white">
            </div>
            <div>
                <label class="text-xs text-white/60 uppercase">Companie / venue (opțional)</label>
                <input type="text" name="company" class="w-full mt-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white">
            </div>
            <div>
                <label class="text-xs text-white/60 uppercase">Telefon (opțional)</label>
                <input type="tel" name="phone" class="w-full mt-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white">
            </div>
            <button type="submit" class="w-full bg-accent text-white py-3 rounded-xl font-semibold mt-4">
                Descarcă rider →
            </button>
            <p id="rider-error" class="text-xs text-red-400 hidden"></p>
        </form>
    </div>
</div>
<script>
    document.getElementById('rider-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        const errorEl = document.getElementById('rider-error');
        errorEl.classList.add('hidden');

        const data = {
            variant_id: parseInt(form.dataset.variantId, 10),
            name: form.name.value,
            email: form.email.value,
            company: form.company.value || null,
            phone: form.phone.value || null,
        };

        try {
            const res = await fetch('/api/proxy.php?action=epk.rider_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data),
            });
            const payload = await res.json();
            if (payload?.success && payload?.data?.download_url) {
                window.location.href = payload.data.download_url;
                document.getElementById('rider-modal').classList.add('hidden');
                document.getElementById('rider-modal').classList.remove('flex');
            } else {
                errorEl.textContent = payload?.message || 'Eroare. Reincearca.';
                errorEl.classList.remove('hidden');
            }
        } catch (err) {
            errorEl.textContent = 'Conexiune esuata. Reincearca.';
            errorEl.classList.remove('hidden');
        }
    });
</script>
@endif

</body>
</html>
