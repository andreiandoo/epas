@php
    /** @var array $artist */
    /** @var array $variant */
    /** @var array $live_stats */
    /** @var array $past_events */
    /** @var string $marketplace_name */

    $sectionsByid = collect($variant['sections'] ?? [])->keyBy('id');
    $get = fn (string $id, string $key, $default = null) => $sectionsByid[$id]['data'][$key] ?? $default;
    $enabled = fn (string $id) => (bool) ($sectionsByid[$id]['enabled'] ?? false);

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
    $rider = $sectionsByid['rider']['data'] ?? [];
    $social = $sectionsByid['social']['data'] ?? [];
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

    $publicUrl = url()->current();
    $ogTitle = $stageName . ($variant['name'] !== 'Default' ? ' — ' . $variant['name'] : '') . ' EPK';
    $ogDescription = trim(strip_tags($bioShort)) ?: ('Electronic Press Kit pentru ' . $stageName);
    $ogImage = $heroImage ?: ($artist['main_image_url'] ?? null);
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

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&family=Space+Grotesk:wght@500;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --accent: {{ $accent }}; }
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #fff; }
        .epk-display { font-family: 'Space Grotesk', sans-serif; }
        .text-accent { color: var(--accent); }
        .bg-accent { background-color: var(--accent); }
        .border-accent { border-color: var(--accent); }
        .hero-bg-image { background-position: center; background-size: cover; }
        .modal-backdrop { background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); }
    </style>
</head>
<body class="bg-black text-white min-h-screen">

<div class="epk-public">
    {{-- ============================ HERO ============================ --}}
    @if ($enabled('hero'))
    <section class="relative min-h-[80vh] flex items-end overflow-hidden">
        @if ($heroImage)
            <div class="absolute inset-0 hero-bg-image" style="background-image: url('{{ $heroImage }}')"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-black/30"></div>
        @else
            <div class="absolute inset-0" style="background: linear-gradient(135deg, var(--accent) 0%, #1a1a1a 100%)"></div>
            <div class="absolute inset-0" style="background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.08), transparent 60%)"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent"></div>
        @endif

        <div class="absolute top-0 left-0 right-0 z-10 p-6 flex items-center justify-between">
            <span class="text-white/60 text-xs uppercase tracking-[0.2em]">Electronic Press Kit</span>
            <span class="text-white/60 text-xs flex items-center gap-2">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3.707 7.293a1 1 0 010 1.414l-3 3a1 1 0 01-1.414 0l-1.5-1.5a1 1 0 111.414-1.414l.793.793 2.293-2.293a1 1 0 011.414 0z"/></svg>
                Verified by {{ $marketplace_name }}
            </span>
        </div>

        <div class="relative z-10 px-6 lg:px-12 pb-12 lg:pb-20 max-w-5xl">
            <h1 class="epk-display text-5xl lg:text-9xl font-black text-white leading-[0.85] mb-6">{{ $stageName }}</h1>
            @if ($tagline)
                <p class="text-xl lg:text-2xl text-white/80 max-w-2xl font-light">{{ $tagline }}</p>
            @endif
        </div>
    </section>
    @endif

    {{-- ============================ STATS ============================ --}}
    @if ($enabled('stats'))
    <section class="py-20 px-6 lg:px-12 border-t border-white/5">
        <div class="max-w-5xl mx-auto">
            <div class="text-center mb-12">
                <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Date Verificate · Din Platformă</p>
                <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white">Cifre care nu mint</h2>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-{{ count(array_filter($statsShow)) ?: 4 }} gap-6 lg:gap-8">
                @foreach ($statsConfig as $key => $label)
                    @if (!empty($statsShow[$key]) && isset($live_stats[$key]))
                        <div class="text-center">
                            <p class="epk-display text-5xl lg:text-7xl font-black text-white mb-2">{{ $live_stats[$key]['display'] }}</p>
                            <p class="text-white/60 text-xs lg:text-sm uppercase tracking-wider">{{ $label }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ============================ BIO ============================ --}}
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
                    <a href="{{ $spotifyUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-[#1DB954] hover:text-white transition-colors text-sm font-medium">
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
                @foreach (array_slice($youtubeVideos, 0, 3) as $videoUrl)
                    @php
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

    {{-- ============================ PAST EVENTS ============================ --}}
    @if ($enabled('past_events') && !empty($past_events))
    <section class="py-20 px-6 lg:px-12 border-t border-white/5">
        <div class="max-w-5xl mx-auto">
            <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Istoric</p>
            <h2 class="epk-display text-4xl lg:text-5xl font-bold text-white mb-12">Concerte memorabile</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($past_events as $ev)
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

    {{-- ============================ RIDER + BOOKING CTA ============================ --}}
    @if ($enabled('contact') || $enabled('rider'))
    <section class="py-32 px-6 lg:px-12 border-t border-white/5 relative overflow-hidden">
        <div class="absolute inset-0" style="background: radial-gradient(circle at 50% 50%, var(--accent), transparent 70%); opacity: 0.2"></div>
        <div class="relative z-10 max-w-3xl mx-auto text-center">
            @if ($showBookingCta)
                <p class="text-accent text-xs uppercase tracking-[0.3em] font-bold mb-3">Disponibili pentru</p>
                <h2 class="epk-display text-5xl lg:text-7xl font-black text-white mb-6">Booking</h2>
                <p class="text-xl text-white/70 mb-10">Concerte · Festivaluri · Evenimente private · Corporate</p>
            @endif

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @if ($showBookingCta && $contactEmail)
                    <a href="mailto:{{ $contactEmail }}" class="inline-flex items-center justify-center bg-accent text-white px-8 py-4 rounded-xl font-semibold hover:opacity-90 transition-opacity">
                        Cere booking →
                    </a>
                @endif
                @if ($enabled('rider') && !empty($rider['rider_pdf_url']))
                    @if (!empty($rider['gated']))
                        <button type="button" onclick="document.getElementById('rider-modal').classList.remove('hidden')" class="inline-flex items-center justify-center bg-white/10 backdrop-blur text-white px-8 py-4 rounded-xl font-semibold hover:bg-white/20 transition-colors">
                            Descarcă rider tehnic
                        </button>
                    @else
                        <a href="{{ $rider['rider_pdf_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center bg-white/10 backdrop-blur text-white px-8 py-4 rounded-xl font-semibold hover:bg-white/20 transition-colors">
                            Descarcă rider tehnic
                        </a>
                    @endif
                @endif
            </div>

            @if ($enabled('contact') && ($contactEmail || $contactPhone))
                <div class="mt-12 pt-12 border-t border-white/10 grid grid-cols-2 gap-8 text-left max-w-md mx-auto">
                    @if ($contactEmail)
                        <div>
                            <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Email</p>
                            <p class="text-white text-sm">{{ $contactEmail }}</p>
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
                @foreach (['facebook' => 'f', 'instagram' => 'i', 'tiktok' => 't', 'youtube' => 'y', 'website' => 'w'] as $key => $label)
                    @if (!empty($social[$key]))
                        <a href="{{ $social[$key] }}" target="_blank" rel="noopener" class="w-10 h-10 bg-white/5 hover:bg-white/15 rounded-full flex items-center justify-center text-white text-sm font-bold uppercase transition-colors">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
        @endif
        <p class="text-white/40 text-xs mb-2">Powered by <span class="text-white/70">{{ $marketplace_name }}</span></p>
        <p class="text-white/30 text-xs font-mono">{{ str_replace(['https://', 'http://'], '', $publicUrl) }}</p>
    </footer>
</div>

{{-- ============================ RIDER LEAD CAPTURE MODAL ============================ --}}
@if ($enabled('rider') && !empty($rider['gated']) && !empty($rider['rider_pdf_url']))
<div id="rider-modal" class="modal-backdrop fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-[#0f0f0f] border border-white/10 rounded-2xl max-w-md w-full p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-xl font-bold text-white">Descarcă rider tehnic</h3>
                <p class="text-sm text-white/60 mt-1">Lasă-ne datele tale și primești instant link-ul de download.</p>
            </div>
            <button type="button" onclick="document.getElementById('rider-modal').classList.add('hidden')" class="text-white/40 hover:text-white">✕</button>
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
