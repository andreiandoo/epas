@php
    $sectionsByid = collect($variant['sections'] ?? [])->keyBy('id');
    $get = fn (string $id, string $key, $default = null) => $sectionsByid[$id]['data'][$key] ?? $default;
    $enabled = fn (string $id) => (bool) ($sectionsByid[$id]['enabled'] ?? false);
    $accent = $variant['accent_color'] ?? '#A51C30';
    $stageName = $get('hero', 'stage_name', $artist['name']);
    $tagline = $get('hero', 'tagline', '');
    $bioShort = $get('bio', 'bio_short', '');
    $bioLong = $get('bio', 'bio_long', '');
    $heroImage = $get('hero', 'cover_image') ?: $artist['main_image_url'];
    $gallery = array_slice((array) $get('gallery', 'images', []), 0, 6);
    $contactEmail = $get('contact', 'email', '');
    $contactPhone = $get('contact', 'phone', '');
    $statsShow = (array) $get('stats', 'show', []);
    $statsConfig = [
        'tickets_sold' => 'Bilete vândute',
        'events_played' => 'Concerte',
        'cities' => 'Orașe',
        'countries' => 'Țări',
        'peak_audience' => 'Audiență max',
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>EPK — {{ $stageName }}</title>
    <style>
        @page { margin: 25mm 15mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1a1a1a; font-size: 11pt; line-height: 1.5; }
        h1, h2, h3 { font-family: DejaVu Sans, Arial, sans-serif; }
        h1 { font-size: 32pt; font-weight: 900; margin: 0 0 6pt 0; line-height: 1; }
        h2 { font-size: 16pt; font-weight: 700; color: {{ $accent }}; border-bottom: 2px solid {{ $accent }}; padding-bottom: 3pt; margin: 20pt 0 10pt 0; }
        .tagline { font-size: 13pt; color: #666; margin-bottom: 20pt; }
        .stats-grid { width: 100%; margin: 15pt 0; }
        .stats-grid td { text-align: center; padding: 8pt; vertical-align: top; }
        .stat-value { font-size: 24pt; font-weight: 900; color: {{ $accent }}; }
        .stat-label { font-size: 8pt; color: #666; text-transform: uppercase; letter-spacing: 1pt; }
        .gallery { width: 100%; margin: 10pt 0; }
        .gallery td { padding: 3pt; vertical-align: top; }
        .gallery img { width: 100%; height: auto; max-height: 90pt; object-fit: cover; }
        .hero-img { width: 100%; max-height: 200pt; object-fit: cover; margin-bottom: 15pt; }
        .past-events td { padding: 5pt; border-bottom: 1pt solid #eee; font-size: 10pt; }
        .footer { margin-top: 30pt; padding-top: 10pt; border-top: 1pt solid #ccc; font-size: 9pt; color: #666; }
        .accent { color: {{ $accent }}; }
    </style>
</head>
<body>

@if ($heroImage)
    <img src="{{ $heroImage }}" class="hero-img" alt="{{ $stageName }}">
@endif

<h1>{{ $stageName }}</h1>
@if ($tagline)
    <p class="tagline">{{ $tagline }}</p>
@endif

@if ($enabled('stats'))
<table class="stats-grid">
    <tr>
        @foreach ($statsConfig as $key => $label)
            @if (!empty($statsShow[$key]) && isset($live_stats[$key]))
                <td>
                    <div class="stat-value">{{ $live_stats[$key]['display'] }}</div>
                    <div class="stat-label">{{ $label }}</div>
                </td>
            @endif
        @endforeach
    </tr>
</table>
@endif

@if ($enabled('bio') && ($bioShort || $bioLong))
    <h2>Biografie</h2>
    @if ($bioShort)<p><strong>{{ $bioShort }}</strong></p>@endif
    @if ($bioLong)<p>{{ $bioLong }}</p>@endif
@endif

@if (!empty($gallery))
    <h2>Galerie</h2>
    <table class="gallery">
        <tr>
            @foreach ($gallery as $img)
                <td><img src="{{ $img }}" alt=""></td>
            @endforeach
        </tr>
    </table>
@endif

@if ($enabled('past_events') && !empty($past_events))
    <h2>Concerte memorabile</h2>
    <table class="past-events" width="100%">
        @foreach (array_slice($past_events, 0, 10) as $ev)
            <tr>
                <td width="20%"><strong>{{ $ev['day'] }} {{ $ev['month'] }} {{ $ev['year'] }}</strong></td>
                <td width="50%">{{ $ev['title'] }}</td>
                <td width="30%">{{ $ev['venue'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if ($enabled('contact') && ($contactEmail || $contactPhone))
    <h2>Contact</h2>
    @if ($contactEmail)<p><strong>Email:</strong> {{ $contactEmail }}</p>@endif
    @if ($contactPhone)<p><strong>Telefon:</strong> {{ $contactPhone }}</p>@endif
@endif

<div class="footer">
    Electronic Press Kit · {{ $stageName }} · Generat din EventPilot
</div>

</body>
</html>
