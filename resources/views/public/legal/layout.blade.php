@php($company = \App\Support\LegalInfo::company())
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') · Tixello</title>
    <meta name="description" content="@yield('description')">
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f6f6f4; --surface: #fff; --ink: #151619; --ink-2: #4c4e53; --ink-3: #8b8d91; --line: #e3e3df; --accent: #e8492a; --soft: rgba(232, 73, 42, .08); color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111214; --surface: #18191c; --ink: #f1f1ee; --ink-2: #b9bab6; --ink-3: #86878a; --line: #2a2b2f; --soft: rgba(232, 73, 42, .14); color-scheme: dark; } }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 24px; }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 400 16px/1.7 Geist, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-underline-offset: 3px; }
        a:focus-visible, button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }
        .top { border-bottom: 1px solid var(--line); background: var(--surface); }
        .top__in { max-width: 1120px; margin: 0 auto; padding: 0 16px; height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .logo { font-weight: 700; font-size: 20px; letter-spacing: -.02em; text-decoration: none; }
        .logo span { color: var(--accent); }
        .top nav { display: flex; gap: 20px; font-size: 14px; }
        .top nav a { color: var(--ink-2); text-decoration: none; }
        .top nav a[aria-current="page"] { color: var(--ink); font-weight: 600; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 40px 16px 80px; display: grid; gap: 48px; }
        @media (min-width: 960px) { .wrap { grid-template-columns: 260px minmax(0, 1fr); } }
        .toc { font-size: 14px; }
        @media (min-width: 960px) { .toc { position: sticky; top: 24px; align-self: start; max-height: calc(100vh - 48px); overflow: auto; scrollbar-width: thin; } }
        .toc p { font-weight: 600; margin: 0 0 8px; }
        .toc ol { list-style: none; margin: 0; padding: 0; counter-reset: t; }
        .toc li a { display: block; padding: 6px 10px; border-radius: 8px; color: var(--ink-2); text-decoration: none; }
        .toc li a:hover { background: var(--soft); color: var(--ink); }
        article { max-width: 760px; min-width: 0; }
        .eyebrow { color: var(--accent); font-weight: 600; font-size: 14px; margin: 0; }
        h1 { font-size: clamp(32px, 5vw, 44px); line-height: 1.1; letter-spacing: -.02em; margin: 8px 0 16px; }
        .meta { color: var(--ink-3); font-size: 14px; margin: 0 0 32px; }
        .summary { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 20px 24px; margin-bottom: 40px; }
        .summary h2 { margin-top: 0 !important; font-size: 18px !important; }
        .summary ul { margin: 8px 0 0; padding-left: 20px; }
        article h2 { font-size: 24px; letter-spacing: -.01em; margin: 48px 0 12px; padding-top: 8px; border-top: 1px solid var(--line); }
        article h3 { font-size: 18px; margin: 28px 0 8px; }
        article p, article li { color: var(--ink-2); }
        article strong { color: var(--ink); font-weight: 600; }
        article ul, article ol { padding-left: 22px; }
        article li { margin: 4px 0; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 16px 0; display: block; overflow-x: auto; scrollbar-width: thin; }
        .table th, .table td { text-align: left; vertical-align: top; padding: 10px 12px; border-bottom: 1px solid var(--line); }
        .table th { color: var(--ink); font-weight: 600; background: var(--surface); }
        .table td { color: var(--ink-2); }
        .note { background: var(--soft); border-radius: 12px; padding: 14px 18px; }
        .company { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 16px 20px; }
        .company p { margin: 2px 0; }
        footer { border-top: 1px solid var(--line); color: var(--ink-3); font-size: 14px; }
        footer div { max-width: 1120px; margin: 0 auto; padding: 24px 16px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; }
        .print { background: none; border: 1px solid var(--line); border-radius: 999px; padding: 8px 14px; font: inherit; font-size: 14px; color: var(--ink-2); cursor: pointer; }
        @media print { .top nav, .toc, .print, footer { display: none; } body { background: #fff; color: #000; } .wrap { display: block; padding: 0; } article p, article li, .table td { color: #000; } }
    </style>
</head>
<body>
    <header class="top">
        <div class="top__in">
            <a href="/" class="logo">tixello<span>.</span></a>
            <nav aria-label="Documente">
                <a href="/termeni-si-conditii" @if (request()->is('termeni-si-conditii')) aria-current="page" @endif>Termeni și condiții</a>
                <a href="/politica-confidentialitate" @if (request()->is('politica-confidentialitate')) aria-current="page" @endif>Confidențialitate</a>
            </nav>
        </div>
    </header>
    <div class="wrap">
        <aside class="toc" aria-label="Cuprins">
            <p>Cuprins</p>
            <ol>@yield('toc')</ol>
        </aside>
        <article>
            @yield('content')
            <p style="margin-top:48px"><button type="button" class="print" onclick="window.print()">Tipărește sau salvează PDF</button></p>
        </article>
    </div>
    <footer>
        <div>
            <span>{{ $company['name'] }}@if ($company['cui']) · CUI {{ $company['cui'] }}@endif</span>
            <span>Versiunea {{ config('legal.version') }}</span>
        </div>
    </footer>
</body>
</html>
