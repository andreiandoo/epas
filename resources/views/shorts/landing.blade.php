{{--
    Landing pentru un short partajat (docs/plans/shorts.md D1).

    Singura suprafata web de care are nevoie o functionalitate mobile-only:
    preview OG pentru WhatsApp/Instagram + trecerea in app + fallback pe store.

    Zero CDN: fonturile si stilurile sunt inline, ca pagina sa se randeze la fel
    si in webview-urile care taie cererile externe.
--}}
<!DOCTYPE html>
<html lang="{{ $short->language ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $short->title ?? 'Tixello' }}</title>

    <meta property="og:type" content="video.other">
    <meta property="og:title" content="{{ $short->title ?? 'Tixello' }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($short->caption ?? 'Descoperă evenimente pe Tixello', 160) }}">
    @if ($cardUrl)
        <meta property="og:image" content="{{ $cardUrl }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $cardUrl }}">
    @endif
    <meta name="twitter:title" content="{{ $short->title ?? 'Tixello' }}">

    {{-- Nu indexam landing-urile de share: sunt tinte de deep-link, nu continut. --}}
    <meta name="robots" content="noindex">

    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #08060f;
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .poster {
            width: 100%;
            aspect-ratio: 9 / 16;
            max-height: 52vh;
            object-fit: cover;
            border-radius: 20px;
            background: #14101f;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .55);
        }
        h1 { font-size: 22px; line-height: 1.2; margin: 20px 0 6px; letter-spacing: -.02em; }
        p.sub { margin: 0; font-size: 14px; line-height: 1.45; opacity: .72; }
        .cta {
            display: block;
            margin: 22px 0 10px;
            padding: 15px 20px;
            border-radius: 15px;
            background: #fff;
            color: #141020;
            font-weight: 650;
            font-size: 15px;
            text-decoration: none;
        }
        .store { display: flex; gap: 10px; justify-content: center; }
        .store a {
            flex: 1;
            padding: 12px;
            border-radius: 13px;
            border: 1px solid rgba(255, 255, 255, .18);
            color: #fff;
            font-size: 13px;
            text-decoration: none;
        }
        footer { margin-top: 26px; font-size: 12px; opacity: .45; }
        a:focus-visible, .cta:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
    </style>
</head>
<body>
    <main class="card">
        @if ($short->poster_url)
            <img class="poster" src="{{ $short->poster_url }}" alt="{{ $short->title ?? 'Short' }}">
        @else
            <div class="poster" role="presentation"></div>
        @endif

        <h1>{{ $short->title ?? 'Vezi pe Tixello' }}</h1>

        @if ($short->caption)
            <p class="sub">{{ \Illuminate\Support\Str::limit($short->caption, 180) }}</p>
        @endif

        <a class="cta" href="{{ $deepLink }}">Deschide în aplicație</a>

        @if ($iosStoreUrl || $androidStoreUrl)
            <div class="store">
                @if ($iosStoreUrl)<a href="{{ $iosStoreUrl }}">App Store</a>@endif
                @if ($androidStoreUrl)<a href="{{ $androidStoreUrl }}">Google Play</a>@endif
            </div>
        @endif

        <footer>Tixello</footer>
    </main>

    <script>
        // Incercam deep-link-ul imediat: daca app-ul e instalat, pagina nu mai
        // apuca sa fie vazuta. Daca nu e, nu se intampla nimic si ramane
        // butonul — deci fara timere de redirect catre store, care trimit in
        // store si utilizatorii care AU app-ul, doar ca au raspuns incet.
        (function () {
            var link = @json($deepLink);
            if (document.visibilityState === 'visible') window.location.href = link;
        })();
    </script>
</body>
</html>
