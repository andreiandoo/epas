@php
    $sectionsByid = collect($variant['sections'] ?? [])->keyBy('id');
    $get = function (string $id, string $key, $default = null) use ($sectionsByid) {
        $section = $sectionsByid[$id] ?? null;
        if (!is_array($section)) return $default;
        $data = $section['data'] ?? null;
        if (!is_array($data)) return $default;
        return $data[$key] ?? $default;
    };
    $enabled = fn (string $id) => (bool) (($sectionsByid[$id] ?? [])['enabled'] ?? false);

    $accent = $variant['accent_color'] ?? '#A51C30';
    $accentDark = '#1E293B';
    $stageName = $get('hero', 'stage_name', $artist['name']);
    $tagline = $get('hero', 'tagline', '');
    $bioShort = $get('bio', 'bio_short', '');
    $bioLong = $get('bio', 'bio_long', '');
    $heroImage = $get('hero', 'cover_image') ?: ($artist['main_image_url'] ?? null);
    $gallery = array_values(array_filter((array) $get('gallery', 'images', []), fn ($x) => is_string($x) && $x));
    $galleryHero = $gallery[0] ?? null;
    $gallerySmall = array_slice($gallery, 1, 6);
    $contactEmail = $get('contact', 'email', '');
    $contactPhone = $get('contact', 'phone', '');
    $achievements = (array) $get('achievements', 'items', []);
    $pressQuotes = (array) $get('press_quotes', 'quotes', []);

    $statsShow = (array) $get('stats', 'show', []);
    $statsConfig = [
        'tickets_sold' => 'Bilete vândute',
        'events_played' => 'Concerte',
        'cities' => 'Orașe',
        'countries' => 'Țări',
        'peak_audience' => 'Audiență max',
        'instagram_followers' => 'Instagram',
        'facebook_followers' => 'Facebook',
        'youtube_followers' => 'YouTube',
        'spotify_followers' => 'Spotify',
        'spotify_monthly_listeners' => 'Spotify lunar',
        'tiktok_followers' => 'TikTok',
    ];
    $allStats = [];
    foreach ($statsConfig as $key => $label) {
        if (!empty($statsShow[$key]) && isset($live_stats[$key]) && (int) ($live_stats[$key]['raw'] ?? 0) > 0) {
            $allStats[] = ['label' => $label, 'value' => $live_stats[$key]['display']];
        }
    }
    foreach ((array) $get('stats', 'custom', []) as $cs) {
        if (is_array($cs) && !empty($cs['label']) && !empty($cs['value'])) {
            $allStats[] = ['label' => $cs['label'], 'value' => $cs['value']];
        }
    }

    $social = is_array($sectionsByid['social'] ?? null) ? ($sectionsByid['social']['data'] ?? []) : [];
    $publicUrl = $marketplace_name ? 'https://' . strtolower($marketplace_name) . '.ro/epk/' . ($artist['slug'] ?? '') : '';
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>EPK — {{ $stageName }}</title>
    <style>
        @page { margin: 0; size: A4; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1a1a1a; font-size: 10pt; line-height: 1.5; background: #fff; }
        .page { padding: 0; }

        /* HERO — full-width image with overlay */
        .hero {
            position: relative;
            height: 360pt;
            background: {{ $accent }} url('{{ $heroImage ?: '' }}') center / cover no-repeat;
            color: #fff;
        }
        .hero-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 60%, rgba(0,0,0,0.5) 100%);
        }
        .hero-content {
            position: absolute;
            bottom: 30pt;
            left: 30pt;
            right: 30pt;
            color: #fff;
        }
        .hero-eyebrow { font-size: 8pt; letter-spacing: 4pt; text-transform: uppercase; opacity: 0.8; margin-bottom: 8pt; color: #fff; }
        .hero-title {
            font-size: 50pt;
            font-weight: 900;
            line-height: 1;
            color: #fff;
            margin-bottom: 8pt;
            text-shadow: 0 2pt 8pt rgba(0,0,0,0.5);
        }
        .hero-tagline { font-size: 14pt; color: rgba(255,255,255,0.85); font-weight: 300; max-width: 380pt; }

        .hero-badge {
            position: absolute;
            top: 20pt;
            right: 20pt;
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-size: 7pt;
            padding: 4pt 10pt;
            border-radius: 99pt;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
        }

        /* Section padding */
        .section { padding: 30pt; }
        .section-eyebrow {
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 3pt;
            text-transform: uppercase;
            color: {{ $accent }};
            margin-bottom: 6pt;
        }
        .section-title {
            font-size: 22pt;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 16pt;
        }

        /* STATS — grid via table */
        .stats-section { background: #f8fafc; padding: 30pt; }
        .stats-grid { width: 100%; }
        .stats-grid td { text-align: center; padding: 12pt 6pt; vertical-align: top; }
        .stat-value { font-size: 26pt; font-weight: 900; color: {{ $accent }}; line-height: 1; margin-bottom: 4pt; }
        .stat-label { font-size: 7pt; color: #64748B; text-transform: uppercase; letter-spacing: 1pt; }

        /* BIO */
        .bio-short { font-size: 12pt; color: #1a1a1a; font-weight: 600; margin-bottom: 12pt; line-height: 1.5; }
        .bio-long { font-size: 10pt; color: #475569; line-height: 1.7; }

        /* ACHIEVEMENTS */
        .achievement { padding: 6pt 0; border-bottom: 0.5pt solid #e2e8f0; }
        .achievement-year { display: inline-block; width: 50pt; font-weight: 800; color: {{ $accent }}; font-size: 11pt; }
        .achievement-text { display: inline-block; color: #1a1a1a; font-size: 10pt; }

        /* PRESS QUOTES */
        .quote { padding: 12pt 0; border-left: 2pt solid {{ $accent }}; padding-left: 16pt; margin-bottom: 12pt; }
        .quote-text { font-style: italic; color: #1a1a1a; font-size: 11pt; line-height: 1.6; margin-bottom: 6pt; }
        .quote-source { color: #64748B; font-size: 8pt; }

        /* GALLERY */
        .gallery-hero { width: 100%; max-height: 220pt; object-fit: cover; margin-bottom: 8pt; }
        .gallery-grid { width: 100%; }
        .gallery-grid td { padding: 4pt; vertical-align: top; }
        .gallery-grid img { width: 100%; height: 80pt; object-fit: cover; }

        /* PAST EVENTS */
        .events-table { width: 100%; border-collapse: collapse; }
        .events-table td { padding: 8pt 4pt; border-bottom: 0.5pt solid #e2e8f0; font-size: 10pt; }
        .event-date { width: 70pt; font-weight: 700; color: {{ $accent }}; }
        .event-title { font-weight: 600; color: #1a1a1a; }
        .event-venue { width: 35%; color: #64748B; font-size: 9pt; }

        /* FOOTER — Booking CTA + contact + social */
        .footer-section {
            background: {{ $accent }};
            color: #fff;
            padding: 30pt;
            text-align: center;
        }
        .footer-eyebrow { font-size: 7pt; letter-spacing: 3pt; text-transform: uppercase; opacity: 0.85; margin-bottom: 6pt; color: #fff; }
        .footer-title { font-size: 36pt; font-weight: 900; color: #fff; margin-bottom: 12pt; }
        .footer-subtitle { font-size: 11pt; color: rgba(255,255,255,0.85); margin-bottom: 20pt; }
        .footer-contacts { width: 100%; margin-top: 16pt; }
        .footer-contacts td { text-align: center; padding: 8pt; vertical-align: top; color: #fff; }
        .footer-contact-label { font-size: 7pt; letter-spacing: 1.5pt; text-transform: uppercase; opacity: 0.8; margin-bottom: 4pt; color: rgba(255,255,255,0.75); }
        .footer-contact-value { font-size: 10pt; color: #fff; font-weight: 600; }

        .powered-by {
            background: #1a1a1a;
            color: rgba(255,255,255,0.5);
            font-size: 8pt;
            padding: 12pt 30pt;
            text-align: center;
        }
        .powered-by .url { font-family: DejaVu Sans Mono, monospace; color: rgba(255,255,255,0.7); margin-top: 4pt; display: block; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<div class="page">

    {{-- ============================ HERO ============================ --}}
    <div class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-badge">Electronic Press Kit</div>
        <div class="hero-content">
            @if ($variant['target'] ?? '')
                <div class="hero-eyebrow">{{ $variant['target'] }}</div>
            @endif
            <div class="hero-title">{{ $stageName }}</div>
            @if ($tagline)
                <div class="hero-tagline">{{ $tagline }}</div>
            @endif
        </div>
    </div>

    {{-- ============================ STATS ============================ --}}
    @if ($enabled('stats') && !empty($allStats))
    <div class="stats-section">
        <div style="text-align: center; margin-bottom: 16pt;">
            <div class="section-eyebrow">Date Verificate · Din Platformă</div>
            <div style="font-size: 18pt; font-weight: 800; color: #1a1a1a;">Cifre care nu mint</div>
        </div>
        <table class="stats-grid">
            @php
                $statsChunks = array_chunk($allStats, 4);
            @endphp
            @foreach ($statsChunks as $chunk)
                <tr>
                    @foreach ($chunk as $stat)
                        <td>
                            <div class="stat-value">{{ $stat['value'] }}</div>
                            <div class="stat-label">{{ $stat['label'] }}</div>
                        </td>
                    @endforeach
                    @for ($i = count($chunk); $i < 4; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>
    @endif

    {{-- ============================ BIO ============================ --}}
    @if ($enabled('bio') && ($bioShort || $bioLong))
    <div class="section">
        <div class="section-eyebrow">Biografie</div>
        <div class="section-title">Cine suntem</div>
        @if ($bioShort)
            <p class="bio-short">{{ $bioShort }}</p>
        @endif
        @if ($bioLong)
            <div class="bio-long">{!! is_string($bioLong) ? $bioLong : '' !!}</div>
        @endif
    </div>
    @endif

    {{-- ============================ GALLERY ============================ --}}
    @if ($enabled('gallery') && $galleryHero)
    <div class="section" style="padding-top: 0;">
        <div class="section-eyebrow">Imagini</div>
        <div class="section-title">Live on stage</div>
        <img src="{{ $galleryHero }}" class="gallery-hero" alt="">
        @if (!empty($gallerySmall))
            <table class="gallery-grid">
                @foreach (array_chunk($gallerySmall, 3) as $row)
                    <tr>
                        @foreach ($row as $img)
                            <td><img src="{{ $img }}" alt=""></td>
                        @endforeach
                        @for ($i = count($row); $i < 3; $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
    @endif

    {{-- Page break dacă avem realizări/quotes/events ca să nu se taie urât --}}
    @if ($enabled('achievements') && !empty($achievements))
    <div class="page-break"></div>
    <div class="section">
        <div class="section-eyebrow">Realizări</div>
        <div class="section-title">Momente cheie</div>
        @foreach ($achievements as $a)
            <div class="achievement">
                <span class="achievement-year">{{ $a['year'] ?? '' }}</span>
                <span class="achievement-text">{{ $a['text'] ?? '' }}</span>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ============================ PRESS QUOTES ============================ --}}
    @if ($enabled('press_quotes') && !empty($pressQuotes))
    <div class="section" style="padding-top: 0;">
        <div class="section-eyebrow">Au scris despre noi</div>
        <div class="section-title">Ce zice presa</div>
        @foreach ($pressQuotes as $q)
            <div class="quote">
                <p class="quote-text">"{{ $q['text'] ?? '' }}"</p>
                <p class="quote-source">— {{ $q['source'] ?? '' }}</p>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ============================ PAST EVENTS ============================ --}}
    @if ($enabled('past_events') && !empty($past_events))
    <div class="section" style="padding-top: 0;">
        <div class="section-eyebrow">Istoric</div>
        <div class="section-title">Concerte memorabile</div>
        <table class="events-table">
            @foreach (array_slice($past_events, 0, 12) as $ev)
                <tr>
                    <td class="event-date">{{ $ev['day'] }} {{ $ev['month'] }} {{ $ev['year'] }}</td>
                    <td class="event-title">{{ $ev['title'] }}</td>
                    <td class="event-venue">{{ $ev['venue'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>
    @endif

    {{-- ============================ FOOTER (Booking CTA + Contact) ============================ --}}
    <div class="footer-section">
        @if ((bool) $get('contact', 'show_booking_cta', true))
            <div class="footer-eyebrow">Disponibili pentru</div>
            <div class="footer-title">Booking</div>
            <div class="footer-subtitle">Concerte · Festivaluri · Evenimente private · Corporate</div>
        @endif

        @if ($enabled('contact') && ($contactEmail || $contactPhone))
            <table class="footer-contacts">
                <tr>
                    @if ($contactEmail)
                        <td>
                            <div class="footer-contact-label">Email</div>
                            <div class="footer-contact-value">{{ $contactEmail }}</div>
                        </td>
                    @endif
                    @if ($contactPhone)
                        <td>
                            <div class="footer-contact-label">Telefon</div>
                            <div class="footer-contact-value">{{ $contactPhone }}</div>
                        </td>
                    @endif
                </tr>
            </table>
        @endif

        @if ($enabled('social'))
            @php
                $socialLinks = array_values(array_filter([
                    'Web' => $social['website'] ?? null,
                    'FB' => $social['facebook'] ?? null,
                    'IG' => $social['instagram'] ?? null,
                    'TT' => $social['tiktok'] ?? null,
                    'YT' => $social['youtube'] ?? null,
                ]));
            @endphp
            @if (!empty($socialLinks))
                <div style="margin-top: 16pt; font-size: 8pt; color: rgba(255,255,255,0.85); word-break: break-all; line-height: 1.7;">
                    @foreach (array_filter([
                        $social['website'] ?? null,
                        $social['facebook'] ?? null,
                        $social['instagram'] ?? null,
                        $social['tiktok'] ?? null,
                        $social['youtube'] ?? null,
                    ]) as $link)
                        <div>{{ $link }}</div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    <div class="powered-by">
        Powered by {{ $marketplace_name ?? 'Tixello' }} · Electronic Press Kit
        @if ($publicUrl)
            <span class="url">{{ $publicUrl }}</span>
        @endif
    </div>

</div>

</body>
</html>
